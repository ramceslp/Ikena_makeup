/**
 * Course enrollment from the app.
 *
 * The app could not enroll in a course at all before this: free enrollment was
 * never ported to the app's store, and paid enrollment had no server support —
 * the checkout handoff only understood product_cart and appointment, because
 * CheckoutController::checkout kept its logic inline instead of in a reusable
 * Action. Both halves are covered here: the store's price branch, and the
 * CourseDetail CTA that drives it.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

vi.mock('@capacitor/browser', () => ({
  Browser: { open: vi.fn().mockResolvedValue(undefined) },
}))

import api from '../services/api.js'
import { Browser } from '@capacitor/browser'
import { useCoursesStore } from '../stores/courses.js'
import CourseDetail from '../views/CourseDetail.vue'

const paidCourse = {
  id: 1,
  slug: 'maquillaje-nupcial',
  title: 'Maquillaje Nupcial Profesional',
  description: 'Domina el maquillaje de novias paso a paso.',
  price: '120.00',
  thumbnail: null,
  instructor: { id: 3, name: 'Ana Torres' },
  category: null,
  total_lessons: 3,
  is_enrolled: false,
  sections: [],
}

const freeCourse = { ...paidCourse, id: 2, slug: 'intro-gratis', price: '0.00' }

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

describe('courses store — enroll()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('enrolls a FREE course in-app, without opening a browser', async () => {
    api.post.mockResolvedValueOnce({ data: { data: {} } })

    const store = useCoursesStore()
    const outcome = await store.enroll(freeCourse)

    expect(outcome).toBe('enrolled')
    expect(api.post).toHaveBeenCalledWith('/courses/intro-gratis/enroll')
    expect(Browser.open).not.toHaveBeenCalled()
    expect(store.enrollError).toBeNull()
    expect(store.enrolling).toBe(false)
  })

  it('hands a PAID course off to the web checkout instead of enrolling locally', async () => {
    api.post.mockResolvedValueOnce({
      data: { data: { url: 'https://app.ikena.com/checkout/resume#token=abc' } },
    })

    const store = useCoursesStore()
    const outcome = await store.enroll(paidCourse)

    expect(outcome).toBe('handoff')
    expect(api.post).toHaveBeenCalledWith('/checkout/handoff', { type: 'course', course_id: 1 })
    // Never the free-enrollment endpoint — that would grant a paid course for
    // nothing if the server guard were ever relaxed.
    expect(api.post).not.toHaveBeenCalledWith('/courses/maquillaje-nupcial/enroll')
    expect(Browser.open).toHaveBeenCalledWith({
      url: 'https://app.ikena.com/checkout/resume#token=abc',
    })
    expect(store.enrollHandoffCourseId).toBe(1)
  })

  it('marks the open course as enrolled after a free enrollment so the CTA updates', async () => {
    api.post.mockResolvedValueOnce({ data: { data: {} } })

    const store = useCoursesStore()
    store.currentCourse = { ...freeCourse }
    await store.enroll(freeCourse)

    expect(store.currentCourse.is_enrolled).toBe(true)
  })

  it('does not touch currentCourse when enrolling a DIFFERENT course', async () => {
    api.post.mockResolvedValueOnce({ data: { data: {} } })

    const store = useCoursesStore()
    store.currentCourse = { ...paidCourse }
    await store.enroll(freeCourse)

    expect(store.currentCourse.is_enrolled).toBe(false)
    expect(store.currentCourse.id).toBe(1)
  })

  it('never optimistically marks a PAID course as enrolled — payment has not happened yet', async () => {
    api.post.mockResolvedValueOnce({
      data: { data: { url: 'https://app.ikena.com/checkout/resume#token=abc' } },
    })

    const store = useCoursesStore()
    store.currentCourse = { ...paidCourse }
    await store.enroll(paidCourse)

    expect(store.currentCourse.is_enrolled).toBe(false)
  })

  it('short-circuits when the course is already enrolled', async () => {
    const store = useCoursesStore()
    const outcome = await store.enroll({ ...freeCourse, is_enrolled: true })

    expect(outcome).toBe('enrolled')
    expect(api.post).not.toHaveBeenCalled()
  })

  it('treats a missing price as free rather than crashing on parseFloat(undefined)', async () => {
    api.post.mockResolvedValueOnce({ data: { data: {} } })

    const store = useCoursesStore()
    await store.enroll({ id: 9, slug: 'sin-precio', is_enrolled: false })

    expect(api.post).toHaveBeenCalledWith('/courses/sin-precio/enroll')
  })

  it('surfaces the backend message on failure and leaves enrolling false', async () => {
    const failure = new Error('Unprocessable')
    failure.response = { status: 422, data: { message: 'Este curso ya no está disponible.' } }
    api.post.mockRejectedValueOnce(failure)

    const store = useCoursesStore()
    const outcome = await store.enroll(paidCourse)

    expect(outcome).toBeNull()
    expect(store.enrollError).toBe('Este curso ya no está disponible.')
    expect(store.enrolling).toBe(false)
    expect(store.enrollHandoffCourseId).toBeNull()
  })

  it('maps a 409 to the already-enrolled message', async () => {
    const conflict = new Error('Conflict')
    conflict.response = { status: 409, data: { message: 'You are already enrolled in this course.' } }
    api.post.mockRejectedValueOnce(conflict)

    const store = useCoursesStore()
    await store.enroll(paidCourse)

    expect(store.enrollError).toBe('Ya estás inscrito en este curso.')
  })

  it('maps a 401 to a sign-in message', async () => {
    const unauthorized = new Error('Unauthorized')
    unauthorized.response = { status: 401, data: {} }
    api.post.mockRejectedValueOnce(unauthorized)

    const store = useCoursesStore()
    await store.enroll(freeCourse)

    expect(store.enrollError).toBe('Debes iniciar sesión para inscribirte.')
  })

  it('does not blank out the catalog error/loading state when enrollment fails', async () => {
    api.post.mockRejectedValueOnce(new Error('Network Error'))

    const store = useCoursesStore()
    store.currentCourse = { ...paidCourse }
    await store.enroll(paidCourse)

    expect(store.currentCourse).not.toBeNull()
    expect(store.error).toBeNull()
    expect(store.loading).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// View
// ---------------------------------------------------------------------------

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/cursos', component: { template: '<div/>' }, name: 'courses' },
      { path: '/cursos/:slug', component: CourseDetail, name: 'course-detail' },
      { path: '/profile', component: { template: '<div/>' }, name: 'profile' },
      { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
    ],
  })
}

async function mountDetail(course) {
  api.get.mockResolvedValueOnce({ data: { data: course } })
  const router = makeRouter()
  await router.push(`/cursos/${course.slug}`)
  const wrapper = mount(CourseDetail, { global: { plugins: [router] } })
  await flushPromises()
  return { wrapper, router }
}

describe('CourseDetail.vue — enrollment CTA', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('offers "Inscribirme" on a paid course and hands off to the browser', async () => {
    const { wrapper } = await mountDetail(paidCourse)

    expect(wrapper.find('[data-enroll-btn]').text()).toContain('Inscribirme')

    api.post.mockResolvedValueOnce({
      data: { data: { url: 'https://app.ikena.com/checkout/resume#token=abc' } },
    })
    await wrapper.find('[data-enroll-btn]').trigger('click')
    await flushPromises()

    expect(Browser.open).toHaveBeenCalled()
    expect(wrapper.find('[data-course-handed-off]').exists()).toBe(true)
    // The CTA is gone, so a second tap cannot mint a second handoff token.
    expect(wrapper.find('[data-enroll-btn]').exists()).toBe(false)
  })

  it('labels a free course "Inscribirme gratis" and routes to the profile on success', async () => {
    const { wrapper, router } = await mountDetail(freeCourse)

    expect(wrapper.find('[data-enroll-btn]').text()).toContain('Inscribirme gratis')

    api.post.mockResolvedValueOnce({ data: { data: {} } })
    await wrapper.find('[data-enroll-btn]').trigger('click')
    await flushPromises()

    expect(Browser.open).not.toHaveBeenCalled()
    expect(router.currentRoute.value.path).toBe('/profile')
  })

  it('shows the way in instead of a purchase CTA when already enrolled', async () => {
    const { wrapper } = await mountDetail({ ...paidCourse, is_enrolled: true })

    expect(wrapper.find('[data-course-enrolled]').exists()).toBe(true)
    expect(wrapper.find('[data-enroll-btn]').exists()).toBe(false)
    expect(wrapper.find('[data-go-to-my-courses]').exists()).toBe(true)
  })

  it('renders an enrollment failure inline and keeps the CTA available for a retry', async () => {
    const { wrapper } = await mountDetail(paidCourse)

    const failure = new Error('Unprocessable')
    failure.response = { status: 422, data: { message: 'Este curso ya no está disponible.' } }
    api.post.mockRejectedValueOnce(failure)

    await wrapper.find('[data-enroll-btn]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-enroll-error]').text()).toBe('Este curso ya no está disponible.')
    expect(wrapper.find('[data-enroll-btn]').exists()).toBe(true)
    expect(wrapper.find('[data-course-handed-off]').exists()).toBe(false)
  })

  it('lets the customer retry after the browser was opened', async () => {
    const { wrapper } = await mountDetail(paidCourse)

    api.post.mockResolvedValueOnce({
      data: { data: { url: 'https://app.ikena.com/checkout/resume#token=abc' } },
    })
    await wrapper.find('[data-enroll-btn]').trigger('click')
    await flushPromises()

    await wrapper.find('[data-enroll-retry]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-course-handed-off]').exists()).toBe(false)
    expect(wrapper.find('[data-enroll-btn]').exists()).toBe(true)
  })
})

// ---------------------------------------------------------------------------
// Regression: handoff state leaking between courses
// ---------------------------------------------------------------------------
// Found on the emulator, not by any unit test: enrollHandoffOpened was a plain
// boolean on the store, so after handing off course A, opening course B showed
// A's "Abrimos el pago en tu navegador" panel and NO enroll button — course B
// could not be bought at all without restarting the app. Every test passed,
// because none of them looked at a second course after a handoff.

describe('enrollment state is scoped to the course that produced it', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('does not show course B the handed-off panel after course A was handed off', async () => {
    api.post.mockResolvedValueOnce({
      data: { data: { url: 'https://app.ikena.com/checkout/resume#token=abc' } },
    })

    const store = useCoursesStore()
    await store.enroll(paidCourse)
    expect(store.enrollHandoffCourseId).toBe(paidCourse.id)

    // Now view a different course.
    api.get.mockResolvedValueOnce({ data: { data: freeCourse } })
    const router = makeRouter()
    await router.push('/cursos/intro-gratis')
    const wrapper = mount(CourseDetail, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-course-handed-off]').exists()).toBe(false)
    expect(wrapper.find('[data-enroll-btn]').exists()).toBe(true)
  })

  it('still shows the handed-off panel when returning to the same course', async () => {
    api.post.mockResolvedValueOnce({
      data: { data: { url: 'https://app.ikena.com/checkout/resume#token=abc' } },
    })

    const store = useCoursesStore()
    await store.enroll(paidCourse)

    api.get.mockResolvedValueOnce({ data: { data: paidCourse } })
    const router = makeRouter()
    await router.push('/cursos/maquillaje-nupcial')
    const wrapper = mount(CourseDetail, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-course-handed-off]').exists()).toBe(true)
  })

  it('clears a previous course enrollment error when a new course is opened', async () => {
    const store = useCoursesStore()
    api.post.mockRejectedValueOnce(new Error('Network Error'))
    await store.enroll(paidCourse)
    expect(store.enrollError).not.toBeNull()

    api.get.mockResolvedValueOnce({ data: { data: freeCourse } })
    await store.fetchCourse('intro-gratis')

    expect(store.enrollError).toBeNull()
  })
})
