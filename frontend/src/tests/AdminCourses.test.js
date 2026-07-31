import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
}))

import api from '../services/api.js'
import AdminCourses from '../views/admin/AdminCourses.vue'
import { resolveGuard } from '../router/index.js'

// ---------------------------------------------------------------------------
// Router stub
// ---------------------------------------------------------------------------
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/admin/courses', component: AdminCourses, name: 'AdminCourses' },
    { path: '/admin/courses/new', component: { template: '<div/>' }, name: 'AdminCourseCreate' },
    { path: '/admin/courses/:id/edit', component: { template: '<div/>' }, name: 'AdminCourseEdit' },
    {
      path: '/instructor/courses/:slug/edit',
      component: { template: '<div/>' },
      name: 'InstructorCourseEdit',
    },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------
const fakeCourses = [
  {
    id: 1,
    title: 'Maquillaje de Novias',
    slug: 'maquillaje-de-novias',
    price: '99.00',
    is_published: true,
    instructor: { id: 3, name: 'Ana Ruiz' },
    sections_count: 3,
    lessons_count: 12,
    students_count: 40,
  },
  {
    id: 2,
    title: 'Cejas Perfectas',
    slug: 'cejas-perfectas',
    price: '49.00',
    is_published: false,
    instructor: { id: 5, name: 'Luis Paz' },
    sections_count: 0,
    lessons_count: 0,
    students_count: 0,
  },
]

const fakeInstructors = [
  { id: 3, name: 'Ana Ruiz', role: 'instructor' },
  { id: 5, name: 'Luis Paz', role: 'instructor' },
]

/** Routes GETs by URL so the view's parallel loads both resolve. */
function mockGets({ courses = fakeCourses, instructors = fakeInstructors } = {}) {
  api.get.mockImplementation((url) => {
    if (url === '/admin/instructors') {
      return Promise.resolve({ data: { data: instructors } })
    }
    return Promise.resolve({ data: { data: courses, meta: { total: courses.length } } })
  })
}

async function mountView() {
  router.push('/admin/courses')
  await router.isReady()
  const wrapper = mount(AdminCourses, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

describe('AdminCourses', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  // ---------------------------------------------------------------------------
  // Listing
  // ---------------------------------------------------------------------------

  it('renders one row per course in the catalog', async () => {
    mockGets()
    const wrapper = await mountView()

    expect(wrapper.findAll('[data-course-row]')).toHaveLength(2)
  })

  /**
   * The whole point of the admin catalog: it spans instructors, so every row
   * has to say who owns the course.
   */
  it('shows the owning instructor on each row', async () => {
    mockGets()
    const wrapper = await mountView()

    const names = wrapper.findAll('[data-instructor-name]').map((n) => n.text())
    expect(names).toEqual(['Ana Ruiz', 'Luis Paz'])
  })

  it('distinguishes published courses from drafts', async () => {
    mockGets()
    const wrapper = await mountView()

    const badges = wrapper.findAll('[data-status-badge]').map((b) => b.text())
    expect(badges).toEqual(['Publicado', 'Borrador'])
  })

  it('shows an empty state when nothing matches', async () => {
    mockGets({ courses: [] })
    const wrapper = await mountView()

    expect(wrapper.find('[data-empty-state]').exists()).toBe(true)
    expect(wrapper.findAll('[data-course-row]')).toHaveLength(0)
  })

  it('populates the instructor filter from the picker endpoint', async () => {
    mockGets()
    const wrapper = await mountView()

    const options = wrapper.find('[data-filter-instructor]').findAll('option')
    expect(options.map((o) => o.text())).toEqual(['Todos los instructores', 'Ana Ruiz', 'Luis Paz'])
  })

  it('refetches with the instructor filter applied', async () => {
    mockGets()
    const wrapper = await mountView()

    await wrapper.find('[data-filter-instructor]').setValue('5')
    await flushPromises()

    // Bound via :value="i.id", so v-model yields the number, not the string.
    expect(api.get).toHaveBeenLastCalledWith('/admin/courses', {
      params: { instructor_id: 5 },
    })
  })

  it('refetches drafts when the status filter is set to borradores', async () => {
    mockGets()
    const wrapper = await mountView()

    await wrapper.find('[data-filter-status]').setValue('0')
    await flushPromises()

    expect(api.get).toHaveBeenLastCalledWith('/admin/courses', {
      params: { is_published: '0' },
    })
  })

  // ---------------------------------------------------------------------------
  // Actions
  // ---------------------------------------------------------------------------

  it('publishes a draft course', async () => {
    mockGets()
    api.post.mockResolvedValue({ data: { data: { ...fakeCourses[1], is_published: true } } })
    const wrapper = await mountView()

    await wrapper.findAll('[data-publish-btn]')[1].trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/admin/courses/2/publish')
  })

  it('unpublishes a published course', async () => {
    mockGets()
    api.post.mockResolvedValue({ data: { data: { ...fakeCourses[0], is_published: false } } })
    const wrapper = await mountView()

    await wrapper.findAll('[data-publish-btn]')[0].trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/admin/courses/1/unpublish')
  })

  /**
   * "Cannot publish a course with no lessons" is a product rule, so the admin
   * must see that exact reason rather than a generic failure.
   */
  it('surfaces the server reason when publishing is refused', async () => {
    mockGets()
    api.post.mockRejectedValue({
      response: { status: 422, data: { message: 'Cannot publish a course with no lessons.' } },
    })
    const wrapper = await mountView()

    await wrapper.findAll('[data-publish-btn]')[1].trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-action-error]').text()).toContain(
      'Cannot publish a course with no lessons.',
    )
  })

  it('keeps the badge as Borrador when publishing is refused', async () => {
    mockGets()
    api.post.mockRejectedValue({
      response: { status: 422, data: { message: 'Cannot publish a course with no lessons.' } },
    })
    const wrapper = await mountView()

    await wrapper.findAll('[data-publish-btn]')[1].trigger('click')
    await flushPromises()

    expect(wrapper.findAll('[data-status-badge]')[1].text()).toBe('Borrador')
  })

  it('deletes a course after confirmation', async () => {
    mockGets()
    api.delete.mockResolvedValue({})
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const wrapper = await mountView()

    await wrapper.findAll('[data-delete-btn]')[0].trigger('click')
    await flushPromises()

    expect(api.delete).toHaveBeenCalledWith('/admin/courses/1')
  })

  it('does not delete when the confirmation is dismissed', async () => {
    mockGets()
    vi.spyOn(window, 'confirm').mockReturnValue(false)
    const wrapper = await mountView()

    await wrapper.findAll('[data-delete-btn]')[0].trigger('click')
    await flushPromises()

    expect(api.delete).not.toHaveBeenCalled()
  })

  // ---------------------------------------------------------------------------
  // Navigation
  // ---------------------------------------------------------------------------

  it('routes to the metadata editor', async () => {
    mockGets()
    const wrapper = await mountView()

    await wrapper.findAll('[data-edit-btn]')[0].trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/admin/courses/1/edit')
  })

  /**
   * Deep authoring is delegated to the instructor editor rather than cloned,
   * so this hand-off is the seam that keeps the two surfaces in one place.
   */
  it('hands off content editing to the instructor editor', async () => {
    mockGets()
    const wrapper = await mountView()

    await wrapper.findAll('[data-content-btn]')[0].trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/instructor/courses/maquillaje-de-novias/edit')
  })

  it('routes to the create view', async () => {
    mockGets()
    const wrapper = await mountView()

    await wrapper.find('[data-new-course-btn]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/admin/courses/new')
  })
})

// ---------------------------------------------------------------------------
// Route guard
// ---------------------------------------------------------------------------

describe('admin course route guard', () => {
  const target = { meta: { requiresAdmin: true }, fullPath: '/admin/courses' }

  it('sends guests to login', () => {
    const result = resolveGuard(target, { isAuthenticated: false, user: null })

    expect(result).toEqual({ name: 'Login', query: { redirect: '/admin/courses' } })
  })

  it('sends students home', () => {
    const result = resolveGuard(target, { isAuthenticated: true, user: { role: 'student' } })

    expect(result).toEqual({ name: 'Home' })
  })

  /** Catalog governance is admin-only — an instructor is not a catalog admin. */
  it('sends instructors home', () => {
    const result = resolveGuard(target, { isAuthenticated: true, user: { role: 'instructor' } })

    expect(result).toEqual({ name: 'Home' })
  })

  it('lets admins through', () => {
    const result = resolveGuard(target, { isAuthenticated: true, user: { role: 'admin' } })

    expect(result).toBeNull()
  })
})
