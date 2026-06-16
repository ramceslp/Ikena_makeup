import { describe, it, expect, beforeEach, vi } from 'vitest'
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
import { useInstructorStore } from '../stores/instructor.js'

// Helpers
const makeCard = (overrides = {}) => ({
  id: 1,
  title: 'Test Course',
  slug: 'test-course',
  price: 0,
  thumbnail: null,
  is_published: false,
  sections_count: 0,
  lessons_count: 0,
  students_count: 0,
  created_at: '2024-01-01',
  ...overrides,
})

const makeDetail = (overrides = {}) => ({
  id: 1,
  title: 'Test Course',
  slug: 'test-course',
  description: 'Desc',
  price: 0,
  thumbnail: null,
  is_published: false,
  total_lessons: 0,
  sections: [],
  ...overrides,
})

const makeSection = (overrides = {}) => ({
  id: 10,
  title: 'Section 1',
  position: 1,
  lessons: [],
  ...overrides,
})

const makeLesson = (overrides = {}) => ({
  id: 100,
  section_id: 10,
  title: 'Lesson 1',
  description: '',
  video_url: null,
  duration: null,
  position: 1,
  is_free: false,
  ...overrides,
})

describe('instructor store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  // ── fetchMyCourses ────────────────────────────────────────────────────────

  it('fetchMyCourses: calls GET /instructor/courses and populates myCourses', async () => {
    const cards = [makeCard()]
    api.get.mockResolvedValueOnce({ data: { data: cards } })

    const store = useInstructorStore()
    await store.fetchMyCourses()

    expect(api.get).toHaveBeenCalledWith('/instructor/courses')
    expect(store.myCourses).toEqual(cards)
    expect(store.loading).toBe(false)
  })

  // ── createCourse ──────────────────────────────────────────────────────────

  it('createCourse: calls POST /instructor/courses with payload and returns new course', async () => {
    const newCourse = makeCard({ id: 2, title: 'New', slug: 'new' })
    api.post.mockResolvedValueOnce({ data: { data: newCourse } })

    const store = useInstructorStore()
    const result = await store.createCourse({ title: 'New', description: 'D', price: 0 })

    expect(api.post).toHaveBeenCalledWith('/instructor/courses', { title: 'New', description: 'D', price: 0 })
    expect(result).toEqual(newCourse)
    expect(store.myCourses).toContainEqual(newCourse)
  })

  // ── fetchCourse ───────────────────────────────────────────────────────────

  it('fetchCourse: calls GET /instructor/courses/{slug} and sets currentCourse', async () => {
    const detail = makeDetail()
    api.get.mockResolvedValueOnce({ data: { data: detail } })

    const store = useInstructorStore()
    await store.fetchCourse('test-course')

    expect(api.get).toHaveBeenCalledWith('/instructor/courses/test-course')
    expect(store.currentCourse).toEqual(detail)
  })

  // ── updateCourse ──────────────────────────────────────────────────────────

  it('updateCourse: calls PATCH /instructor/courses/{slug} and updates currentCourse', async () => {
    const updated = makeDetail({ title: 'Updated' })
    api.patch.mockResolvedValueOnce({ data: { data: updated } })

    const store = useInstructorStore()
    store.currentCourse = makeDetail()
    await store.updateCourse('test-course', { title: 'Updated' })

    expect(api.patch).toHaveBeenCalledWith('/instructor/courses/test-course', { title: 'Updated' })
    expect(store.currentCourse.title).toBe('Updated')
  })

  // ── publish (optimistic) ──────────────────────────────────────────────────

  it('publish: optimistically sets is_published=true before API call', async () => {
    const card = makeCard({ is_published: false })
    const detail = makeDetail({ is_published: false })
    api.post.mockImplementationOnce(
      () => new Promise((resolve) => setTimeout(() => resolve({ data: { data: { ...card, is_published: true } } }), 50))
    )

    const store = useInstructorStore()
    store.myCourses = [card]
    store.currentCourse = detail

    const promise = store.publish('test-course')
    // Check optimistic update happened synchronously before await
    expect(store.myCourses[0].is_published).toBe(true)
    expect(store.currentCourse.is_published).toBe(true)

    await promise
    expect(api.post).toHaveBeenCalledWith('/instructor/courses/test-course/publish')
  })

  it('publish: rolls back is_published on error', async () => {
    const card = makeCard({ is_published: false })
    api.post.mockRejectedValueOnce({ response: { status: 500, data: { message: 'Server error' } } })

    const store = useInstructorStore()
    store.myCourses = [card]

    await expect(store.publish('test-course')).rejects.toBeDefined()
    expect(store.myCourses[0].is_published).toBe(false)
  })

  // ── unpublish (optimistic) ────────────────────────────────────────────────

  it('unpublish: optimistically sets is_published=false before API call', async () => {
    const card = makeCard({ is_published: true })
    const detail = makeDetail({ is_published: true })
    api.post.mockImplementationOnce(
      () => new Promise((resolve) => setTimeout(() => resolve({ data: { data: { ...card, is_published: false } } }), 50))
    )

    const store = useInstructorStore()
    store.myCourses = [card]
    store.currentCourse = detail

    const promise = store.unpublish('test-course')
    expect(store.myCourses[0].is_published).toBe(false)
    expect(store.currentCourse.is_published).toBe(false)

    await promise
    expect(api.post).toHaveBeenCalledWith('/instructor/courses/test-course/unpublish')
  })

  it('unpublish: rolls back is_published on error', async () => {
    const card = makeCard({ is_published: true })
    api.post.mockRejectedValueOnce({ response: { status: 500, data: { message: 'Error' } } })

    const store = useInstructorStore()
    store.myCourses = [card]

    await expect(store.unpublish('test-course')).rejects.toBeDefined()
    expect(store.myCourses[0].is_published).toBe(true)
  })

  // ── createSection ─────────────────────────────────────────────────────────

  it('createSection: calls POST /instructor/courses/{slug}/sections and appends to currentCourse', async () => {
    const section = makeSection()
    api.post.mockResolvedValueOnce({ data: { data: section } })

    const store = useInstructorStore()
    store.currentCourse = makeDetail()
    await store.createSection('test-course', 'Section 1')

    expect(api.post).toHaveBeenCalledWith('/instructor/courses/test-course/sections', { title: 'Section 1' })
    expect(store.currentCourse.sections).toContainEqual(section)
  })

  // ── deleteSection ─────────────────────────────────────────────────────────

  it('deleteSection: calls DELETE /instructor/sections/{id} and removes section from currentCourse', async () => {
    const section = makeSection({ id: 10 })
    api.delete.mockResolvedValueOnce({})

    const store = useInstructorStore()
    store.currentCourse = makeDetail({ sections: [section] })
    await store.deleteSection(10)

    expect(api.delete).toHaveBeenCalledWith('/instructor/sections/10')
    expect(store.currentCourse.sections).not.toContainEqual(section)
  })

  // ── reorderSections (optimistic) ──────────────────────────────────────────

  it('reorderSections: reorders locally before API call and rolls back on error', async () => {
    const s1 = makeSection({ id: 1, title: 'S1' })
    const s2 = makeSection({ id: 2, title: 'S2' })
    api.patch.mockRejectedValueOnce({ response: { status: 500, data: {} } })

    const store = useInstructorStore()
    store.currentCourse = makeDetail({ sections: [s1, s2] })

    await expect(store.reorderSections('test-course', [2, 1])).rejects.toBeDefined()
    // After rollback, order restored
    expect(store.currentCourse.sections[0].id).toBe(1)
    expect(store.currentCourse.sections[1].id).toBe(2)
  })

  it('reorderSections: applies optimistic reorder before API resolves', async () => {
    const s1 = makeSection({ id: 1 })
    const s2 = makeSection({ id: 2 })
    const reordered = [{ ...s2, position: 1 }, { ...s1, position: 2 }]
    api.patch.mockImplementationOnce(
      () => new Promise((resolve) => setTimeout(() => resolve({ data: { data: reordered } }), 50))
    )

    const store = useInstructorStore()
    store.currentCourse = makeDetail({ sections: [s1, s2] })

    const promise = store.reorderSections('test-course', [2, 1])
    // Optimistic check
    expect(store.currentCourse.sections[0].id).toBe(2)

    await promise
    expect(api.patch).toHaveBeenCalledWith('/instructor/courses/test-course/sections/reorder', { ordered_ids: [2, 1] })
  })

  // ── createLesson ──────────────────────────────────────────────────────────

  it('createLesson: calls POST /instructor/sections/{id}/lessons and appends to section', async () => {
    const section = makeSection({ id: 10 })
    const lesson = makeLesson()
    api.post.mockResolvedValueOnce({ data: { data: lesson } })

    const store = useInstructorStore()
    store.currentCourse = makeDetail({ sections: [section] })
    await store.createLesson(10, { title: 'Lesson 1' })

    expect(api.post).toHaveBeenCalledWith('/instructor/sections/10/lessons', { title: 'Lesson 1' })
    expect(store.currentCourse.sections[0].lessons).toContainEqual(lesson)
  })

  // ── updateLesson ──────────────────────────────────────────────────────────

  it('updateLesson: calls PATCH /instructor/lessons/{id} and updates lesson in section', async () => {
    const lesson = makeLesson({ id: 100, title: 'Old' })
    const updated = { ...lesson, title: 'New' }
    api.patch.mockResolvedValueOnce({ data: { data: updated } })

    const store = useInstructorStore()
    store.currentCourse = makeDetail({ sections: [makeSection({ id: 10, lessons: [lesson] })] })
    await store.updateLesson(100, { title: 'New' })

    expect(api.patch).toHaveBeenCalledWith('/instructor/lessons/100', { title: 'New' })
    expect(store.currentCourse.sections[0].lessons[0].title).toBe('New')
  })

  // ── deleteLesson ──────────────────────────────────────────────────────────

  it('deleteLesson: calls DELETE /instructor/lessons/{id} and removes from section', async () => {
    const lesson = makeLesson({ id: 100 })
    api.delete.mockResolvedValueOnce({})

    const store = useInstructorStore()
    store.currentCourse = makeDetail({ sections: [makeSection({ id: 10, lessons: [lesson] })] })
    await store.deleteLesson(100)

    expect(api.delete).toHaveBeenCalledWith('/instructor/lessons/100')
    expect(store.currentCourse.sections[0].lessons).toHaveLength(0)
  })

  // ── reorderLessons (optimistic) ───────────────────────────────────────────

  it('reorderLessons: reorders locally before API call', async () => {
    const l1 = makeLesson({ id: 101, position: 1 })
    const l2 = makeLesson({ id: 102, position: 2 })
    const reordered = [{ ...l2, position: 1 }, { ...l1, position: 2 }]
    api.patch.mockImplementationOnce(
      () => new Promise((resolve) => setTimeout(() => resolve({ data: { data: reordered } }), 50))
    )

    const store = useInstructorStore()
    store.currentCourse = makeDetail({ sections: [makeSection({ id: 10, lessons: [l1, l2] })] })

    const promise = store.reorderLessons(10, [102, 101])
    // Optimistic check
    expect(store.currentCourse.sections[0].lessons[0].id).toBe(102)

    await promise
    expect(api.patch).toHaveBeenCalledWith('/instructor/sections/10/lessons/reorder', { ordered_ids: [102, 101] })
  })

  it('reorderLessons: rolls back on error', async () => {
    const l1 = makeLesson({ id: 101 })
    const l2 = makeLesson({ id: 102 })
    api.patch.mockRejectedValueOnce({ response: { status: 500, data: {} } })

    const store = useInstructorStore()
    store.currentCourse = makeDetail({ sections: [makeSection({ id: 10, lessons: [l1, l2] })] })

    await expect(store.reorderLessons(10, [102, 101])).rejects.toBeDefined()
    // Rollback: original order
    expect(store.currentCourse.sections[0].lessons[0].id).toBe(101)
    expect(store.currentCourse.sections[0].lessons[1].id).toBe(102)
  })

  // ── 422 validation errors ─────────────────────────────────────────────────

  it('stores validationErrors on 422 response', async () => {
    api.post.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { errors: { title: ['El título es requerido.'] } },
      },
    })

    const store = useInstructorStore()
    await expect(store.createCourse({ title: '' })).rejects.toBeDefined()
    expect(store.validationErrors).toEqual({ title: ['El título es requerido.'] })
    expect(store.error).toBeNull()
  })

  it('clears validationErrors at the start of each action', async () => {
    const card = makeCard()
    api.get.mockResolvedValueOnce({ data: { data: [card] } })

    const store = useInstructorStore()
    store.validationErrors = { title: ['Error previo'] }
    await store.fetchMyCourses()

    expect(store.validationErrors).toEqual({})
  })

  // ── fetchDashboard ────────────────────────────────────────────────────────

  it('fetchDashboard: populates dashboard with kpis and sales_over_time on success', async () => {
    const kpis = {
      total_revenue_cents: 12345,
      currency: 'USD',
      total_sales: 7,
      total_students: 5,
      total_courses: 3,
      published_courses: 2,
    }
    const sales_over_time = [
      { period: '2026-01', revenue_cents: 0, sales: 0 },
      { period: '2026-02', revenue_cents: 5000, sales: 2 },
      { period: '2026-03', revenue_cents: 0, sales: 0 },
      { period: '2026-04', revenue_cents: 0, sales: 0 },
      { period: '2026-05', revenue_cents: 0, sales: 0 },
      { period: '2026-06', revenue_cents: 7000, sales: 1 },
    ]
    api.get.mockResolvedValueOnce({ data: { data: { kpis, sales_over_time } } })

    const store = useInstructorStore()
    await store.fetchDashboard()

    expect(api.get).toHaveBeenCalledWith('/instructor/dashboard')
    expect(store.dashboard).toEqual({ kpis, sales_over_time })
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  it('fetchDashboard: sets error and leaves dashboard null on failure', async () => {
    api.get.mockRejectedValueOnce({
      response: { status: 500, data: { message: 'Error del servidor' } },
    })

    const store = useInstructorStore()
    await store.fetchDashboard()

    expect(store.dashboard).toBeNull()
    expect(store.error).toBe('Error del servidor')
    expect(store.loading).toBe(false)
  })
})

describe('instructor store — submissions', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  const makeSubmission = (overrides = {}) => ({
    id: 1,
    lesson_id: 5,
    status: 'pending',
    feedback: null,
    before_url: 'http://ex.com/b.jpg',
    after_url: 'http://ex.com/a.jpg',
    created_at: '2026-06-16T00:00:00Z',
    graded_at: null,
    user: { id: 2, name: 'Carla López', avatar: null },
    lesson: { id: 5, title: 'Contorno básico' },
    ...overrides,
  })

  it('fetchSubmissions: GETs /instructor/submissions without params when status is null', async () => {
    const submissions = [makeSubmission()]
    api.get.mockResolvedValueOnce({ data: { data: submissions, meta: { current_page: 1, last_page: 1, total: 1 } } })

    const store = useInstructorStore()
    await store.fetchSubmissions()

    expect(api.get).toHaveBeenCalledWith('/instructor/submissions', { params: {} })
    expect(store.submissions).toEqual(submissions)
    expect(store.submissionsMeta).toMatchObject({ current_page: 1, total: 1 })
    expect(store.submissionsLoading).toBe(false)
  })

  it('fetchSubmissions: passes status param when provided', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: null } })

    const store = useInstructorStore()
    await store.fetchSubmissions('pending')

    expect(api.get).toHaveBeenCalledWith('/instructor/submissions', { params: { status: 'pending' } })
  })

  it('fetchSubmissions: sets error on failure via _handleError', async () => {
    api.get.mockRejectedValueOnce({
      response: { status: 500, data: { message: 'Error del servidor' } },
    })

    const store = useInstructorStore()
    await store.fetchSubmissions()

    expect(store.error).toBe('Error del servidor')
    expect(store.submissionsLoading).toBe(false)
  })

  it('gradeSubmission: PATCHes /instructor/submissions/{id} and updates matching item in submissions', async () => {
    const original = makeSubmission({ id: 10, status: 'pending' })
    const updated = { ...original, status: 'approved', feedback: 'Excelente trabajo', graded_at: '2026-06-16T01:00:00Z' }
    api.patch.mockResolvedValueOnce({ data: { data: updated } })

    const store = useInstructorStore()
    store.submissions = [original]

    const result = await store.gradeSubmission(10, { status: 'approved', feedback: 'Excelente trabajo' })

    expect(api.patch).toHaveBeenCalledWith('/instructor/submissions/10', { status: 'approved', feedback: 'Excelente trabajo' })
    expect(store.submissions[0].status).toBe('approved')
    expect(store.submissions[0].feedback).toBe('Excelente trabajo')
    expect(result).toEqual(updated)
    expect(store.grading).toBe(false)
  })

  it('gradeSubmission: _handleError and rethrows on failure', async () => {
    api.patch.mockRejectedValueOnce({
      response: { status: 403, data: { message: 'Acceso denegado' } },
    })

    const store = useInstructorStore()
    store.submissions = [makeSubmission({ id: 10 })]

    await expect(store.gradeSubmission(10, { status: 'approved', feedback: null })).rejects.toBeDefined()
    expect(store.error).toBe('Acceso denegado')
    expect(store.grading).toBe(false)
  })
})
