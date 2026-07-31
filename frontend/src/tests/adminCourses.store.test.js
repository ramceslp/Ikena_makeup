import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

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
import { useAdminCoursesStore } from '../stores/adminCourses.js'

const courseFixture = {
  id: 7,
  title: 'Maquillaje de Novias',
  slug: 'maquillaje-de-novias',
  price: '99.00',
  is_published: false,
  instructor_id: 3,
  instructor: { id: 3, name: 'Ana Ruiz' },
  sections_count: 2,
  lessons_count: 5,
  students_count: 0,
}

describe('adminCourses store', () => {
  let store

  beforeEach(() => {
    setActivePinia(createPinia())
    store = useAdminCoursesStore()
    vi.clearAllMocks()
  })

  // ---------------------------------------------------------------------------
  // fetchCourses
  // ---------------------------------------------------------------------------

  it('loads the catalog from the admin endpoint', async () => {
    api.get.mockResolvedValue({ data: { data: [courseFixture], meta: { total: 1 } } })

    await store.fetchCourses()

    expect(api.get).toHaveBeenCalledWith('/admin/courses', { params: {} })
    expect(store.courses).toHaveLength(1)
    expect(store.meta.total).toBe(1)
    expect(store.loading).toBe(false)
  })

  it('forwards search, instructor and status filters as params', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: null } })

    await store.fetchCourses({ search: 'novias', instructor_id: 3, is_published: '0' })

    expect(api.get).toHaveBeenCalledWith('/admin/courses', {
      params: { search: 'novias', instructor_id: 3, is_published: '0' },
    })
  })

  it('drops empty filter values instead of sending blanks', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: null } })

    await store.fetchCourses({ search: '', instructor_id: '', is_published: '' })

    expect(api.get).toHaveBeenCalledWith('/admin/courses', { params: {} })
  })

  it('does not leak filters from one call into the next', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: null } })

    await store.fetchCourses({ search: 'novias' })
    await store.fetchCourses()

    expect(api.get).toHaveBeenLastCalledWith('/admin/courses', { params: {} })
  })

  it('records a readable error when the catalog request fails', async () => {
    api.get.mockRejectedValue({ response: { status: 500, data: { message: 'Boom' } } })

    await store.fetchCourses()

    expect(store.error).toBe('Boom')
    expect(store.loading).toBe(false)
  })

  // ---------------------------------------------------------------------------
  // create / update / delete
  // ---------------------------------------------------------------------------

  it('creates a course and returns it', async () => {
    api.post.mockResolvedValue({ data: { data: courseFixture } })

    const created = await store.createCourse({ title: 'X', instructor_id: 3 })

    expect(api.post).toHaveBeenCalledWith('/admin/courses', { title: 'X', instructor_id: 3 })
    expect(created.id).toBe(7)
  })

  it('captures 422 field errors on create instead of a generic message', async () => {
    api.post.mockRejectedValue({
      response: { status: 422, data: { errors: { instructor_id: ['The selected user is not an instructor.'] } } },
    })

    await expect(store.createCourse({})).rejects.toBeTruthy()

    expect(store.validationErrors.instructor_id).toEqual([
      'The selected user is not an instructor.',
    ])
    expect(store.error).toBeNull()
  })

  /**
   * The store outlives the view, so a stale currentCourse would render the
   * previously opened course while the new one is still loading.
   */
  it('clears the previous course before loading another', async () => {
    store.currentCourse = { id: 99, title: 'Anterior' }
    let seenDuringFlight
    api.get.mockImplementation(() => {
      seenDuringFlight = store.currentCourse
      return Promise.resolve({ data: { data: courseFixture } })
    })

    await store.fetchCourse(7)

    expect(seenDuringFlight).toBeNull()
    expect(store.currentCourse.id).toBe(7)
  })

  it('updates a course through the admin endpoint', async () => {
    api.patch.mockResolvedValue({ data: { data: { ...courseFixture, price: '150.00' } } })

    await store.updateCourse(7, { price: 150 })

    expect(api.patch).toHaveBeenCalledWith('/admin/courses/7', { price: 150 })
    expect(store.currentCourse.price).toBe('150.00')
  })

  it('removes the course from the list after deleting it', async () => {
    store.courses = [courseFixture, { ...courseFixture, id: 8 }]
    api.delete.mockResolvedValue({})

    await store.deleteCourse(7)

    expect(api.delete).toHaveBeenCalledWith('/admin/courses/7')
    expect(store.courses.map((c) => c.id)).toEqual([8])
  })

  // ---------------------------------------------------------------------------
  // publish / unpublish
  // ---------------------------------------------------------------------------

  it('merges the published state into the row after publishing', async () => {
    store.courses = [courseFixture]
    api.post.mockResolvedValue({ data: { data: { ...courseFixture, is_published: true } } })

    await store.publish(7)

    expect(api.post).toHaveBeenCalledWith('/admin/courses/7/publish')
    expect(store.courses[0].is_published).toBe(true)
  })

  /**
   * Publish is intentionally NOT optimistic: a course with no lessons is
   * refused with 422, and showing "Publicado" before the server agrees would
   * misreport the catalog.
   */
  it('leaves the row untouched when publishing is refused', async () => {
    store.courses = [courseFixture]
    api.post.mockRejectedValue({
      response: { status: 422, data: { message: 'Cannot publish a course with no lessons.' } },
    })

    await expect(store.publish(7)).rejects.toBeTruthy()

    expect(store.courses[0].is_published).toBe(false)
  })

  it('merges the unpublished state into the row', async () => {
    store.courses = [{ ...courseFixture, is_published: true }]
    api.post.mockResolvedValue({ data: { data: { ...courseFixture, is_published: false } } })

    await store.unpublish(7)

    expect(api.post).toHaveBeenCalledWith('/admin/courses/7/unpublish')
    expect(store.courses[0].is_published).toBe(false)
  })

  // ---------------------------------------------------------------------------
  // Reference data
  // ---------------------------------------------------------------------------

  it('loads instructors for the picker', async () => {
    api.get.mockResolvedValue({ data: { data: [{ id: 3, name: 'Ana Ruiz', role: 'instructor' }] } })

    await store.fetchInstructors()

    expect(api.get).toHaveBeenCalledWith('/admin/instructors')
    expect(store.instructors).toHaveLength(1)
  })

  it('does not refetch instructors once loaded', async () => {
    store.instructors = [{ id: 3, name: 'Ana Ruiz' }]

    await store.fetchInstructors()

    expect(api.get).not.toHaveBeenCalled()
  })

  it('keeps working when the instructor picker request fails', async () => {
    api.get.mockRejectedValue(new Error('offline'))

    await store.fetchInstructors()

    expect(store.instructors).toEqual([])
    expect(store.error).toBeNull()
  })
})
