import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

import api from '../services/api.js'
import Courses from '../views/Courses.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/cursos', component: Courses, name: 'courses' },
      { path: '/cursos/:slug', component: { template: '<div/>' }, name: 'course-detail' },
      { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
    ],
  })
}

const fakeCourses = [
  {
    id: 1,
    slug: 'maquillaje-nupcial',
    title: 'Maquillaje Nupcial',
    price: '120.00',
    thumbnail: null,
    lessons_count: 12,
    sections_count: 3,
    category: null,
    description: 'x',
    average_rating: null,
    reviews_count: 0,
    is_bestseller: false,
  },
]

describe('Courses.vue (App) — catalog browse + connectivity [Spec: catalog browses / catalog unreachable]', () => {
  let router

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    router = makeRouter()
    await router.push('/cursos')
  })

  it('renders courses from the API when connectivity is present [Spec: catalog browses successfully]', async () => {
    api.get.mockResolvedValue({ data: { data: fakeCourses, meta: { current_page: 1, last_page: 1 } } })

    const wrapper = mount(Courses, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-course-card]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Maquillaje Nupcial')
    expect(wrapper.find('[data-catalog-error]').exists()).toBe(false)
  })

  it('shows an explicit connectivity error instead of stale/cached data when offline [Spec: catalog unreachable]', async () => {
    const networkError = new Error('Network Error')
    api.get.mockRejectedValue(networkError)

    const wrapper = mount(Courses, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-catalog-error]').exists()).toBe(true)
    expect(wrapper.find('[data-catalog-retry]').exists()).toBe(true)
    expect(wrapper.find('[data-course-card]').exists()).toBe(false)
  })

  it('shows a loading indicator while the connectivity probe is pending (no blank screen)', async () => {
    let resolveProbe
    api.get.mockImplementationOnce(
      () => new Promise((resolve) => { resolveProbe = resolve }),
    )
    // Default for the calls made AFTER the probe resolves (fetchCategories,
    // fetchCourses) — the probe itself only consumes the `Once` queue entry.
    api.get.mockResolvedValue({ data: { data: fakeCourses, meta: {} } })

    const wrapper = mount(Courses, { global: { plugins: [router] } })
    await Promise.resolve()
    await Promise.resolve()

    expect(wrapper.find('[data-catalog-checking]').exists()).toBe(true)
    expect(wrapper.find('[data-course-card]').exists()).toBe(false)

    resolveProbe({ data: { data: fakeCourses, meta: {} } })
    await flushPromises()

    expect(wrapper.find('[data-catalog-checking]').exists()).toBe(false)
    expect(wrapper.find('[data-course-card]').exists()).toBe(true)
  })

  it('does NOT show the connectivity error for a real server error response (server reachable)', async () => {
    const serverError = new Error('Internal Server Error')
    serverError.response = { status: 500 }
    api.get.mockRejectedValue(serverError)

    const wrapper = mount(Courses, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-catalog-error]').exists()).toBe(false)
  })

  it('shows an empty state when no courses match the filters', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: {} } })

    const wrapper = mount(Courses, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toContain('No se encontraron cursos')
  })

  it('tapping retry re-checks connectivity and renders the catalog on success', async () => {
    api.get.mockRejectedValueOnce(new Error('Network Error'))

    const wrapper = mount(Courses, { global: { plugins: [router] } })
    await flushPromises()
    expect(wrapper.find('[data-catalog-error]').exists()).toBe(true)

    api.get.mockResolvedValue({ data: { data: fakeCourses, meta: {} } })
    await wrapper.find('[data-catalog-retry]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-catalog-error]').exists()).toBe(false)
    expect(wrapper.find('[data-course-card]').exists()).toBe(true)
  })

  // [DEFECT 1 fix] CourseFilters.vue now hides price/sort behind a
  // "Filtros" toggle. Collapsing is purely visual -- a filter set while the
  // panel is open must still reach the store/API.
  it('a filter hidden behind the "Filtros" toggle still reaches the API once the panel is opened and changed', async () => {
    api.get.mockResolvedValue({ data: { data: fakeCourses, meta: { current_page: 1, last_page: 1 } } })

    const wrapper = mount(Courses, { global: { plugins: [router] } })
    await flushPromises()

    await wrapper.find('[data-filters-toggle]').trigger('click')
    await wrapper.find('select[aria-label="Ordenar"]').setValue('price_asc')
    await flushPromises()

    const lastCall = api.get.mock.calls.at(-1)
    expect(lastCall[0]).toBe('/courses')
    expect(lastCall[1].params).toMatchObject({ sort: 'price_asc' })
  })
})
