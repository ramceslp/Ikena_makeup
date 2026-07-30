import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

import api from '../services/api.js'
import { useCoursesStore } from '../stores/courses.js'

// Trimmed store: only fetchCourses(), needed by FeaturedCourses.vue for
// Home's "3 most-recent courses" section (see stores/courses.js for why the
// full course-catalog/enroll/lesson/review surface is not ported here).
describe('courses store (trimmed: fetchCourses only, see Home.vue)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchCourses GETs /courses with the given filters as query params and populates courses/meta', async () => {
    const fakeCourses = [{ id: 1, title: 'Maquillaje Nupcial' }]
    api.get.mockResolvedValueOnce({ data: { data: fakeCourses, meta: { total: 1 } } })

    const store = useCoursesStore()
    await store.fetchCourses({ page: 1, per_page: 3, sort: 'newest' })

    expect(api.get).toHaveBeenCalledWith('/courses', { params: { page: 1, per_page: 3, sort: 'newest' } })
    expect(store.courses).toEqual(fakeCourses)
    expect(store.meta).toEqual({ total: 1 })
  })

  it('fetchCourses strips empty/null/undefined filter values from the query params', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = useCoursesStore()
    await store.fetchCourses({ page: 1, search: '', category: null, sort: undefined })

    expect(api.get).toHaveBeenCalledWith('/courses', { params: { page: 1 } })
  })

  it('sets a Spanish error message and leaves courses untouched when the request fails', async () => {
    api.get.mockRejectedValueOnce(new Error('Network Error'))

    const store = useCoursesStore()
    await store.fetchCourses({})

    expect(store.error).toBe('Error al cargar los cursos')
    expect(store.courses).toEqual([])
  })
})

// fetchCourse (detail) + fetchCategories, needed by CourseDetail.vue and
// Courses.vue's category filter. Same extension precedent as
// products.store.test.js's "Phase 7" block and services.store.test.js's
// equivalent -- see stores/courses.js's file-level comment. Enroll/lesson/
// review/certificate actions are intentionally NOT ported -- no /learn
// player, no checkout, no review UI exist in this app.
describe('courses store — fetchCourse + fetchCategories', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchCourse GETs /courses/:slug and populates currentCourse', async () => {
    const fakeCourse = { id: 1, slug: 'maquillaje-nupcial', title: 'Maquillaje Nupcial' }
    api.get.mockResolvedValueOnce({ data: { data: fakeCourse } })

    const store = useCoursesStore()
    const result = await store.fetchCourse('maquillaje-nupcial')

    expect(api.get).toHaveBeenCalledWith('/courses/maquillaje-nupcial')
    expect(store.currentCourse).toEqual(fakeCourse)
    expect(result).toEqual(fakeCourse)
  })

  it('fetchCourse sets a Spanish error message and re-throws on failure', async () => {
    const notFound = new Error('Not Found')
    notFound.response = { status: 404 }
    api.get.mockRejectedValueOnce(notFound)

    const store = useCoursesStore()
    await expect(store.fetchCourse('missing')).rejects.toThrow('Not Found')

    expect(store.error).toBe('Error al cargar el curso')
  })

  it('fetchCategories GETs /categories and populates categories once, skipping subsequent calls', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [{ id: 1, name: 'Ojos', slug: 'ojos' }] } })

    const store = useCoursesStore()
    await store.fetchCategories()
    await store.fetchCategories()

    expect(api.get).toHaveBeenCalledTimes(1)
    expect(store.categories).toEqual([{ id: 1, name: 'Ojos', slug: 'ojos' }])
  })
})
