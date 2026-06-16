import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
}))

import api from '../services/api.js'
import { useCoursesStore } from '../stores/courses.js'

describe('courses store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  // -------------------------------------------------------------------------
  // fetchCourses
  // -------------------------------------------------------------------------

  it('fetchCourses populates the courses list from the API response', async () => {
    const fakeCourses = [
      { id: 1, title: 'PHP Mastery', slug: 'php-mastery', price: '49.99' },
      { id: 2, title: 'Vue Basics',  slug: 'vue-basics',  price: '0.00' },
    ]

    api.get.mockResolvedValueOnce({
      data: { data: fakeCourses, meta: { current_page: 1, last_page: 1, total: 2 } },
    })

    const store = useCoursesStore()
    await store.fetchCourses()

    expect(store.courses).toEqual(fakeCourses)
    expect(store.meta.total).toBe(2)
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  it('fetchCourses sets error state when API call fails', async () => {
    api.get.mockRejectedValueOnce({
      response: { data: { message: 'Server error' } },
    })

    const store = useCoursesStore()
    await store.fetchCourses()

    expect(store.courses).toEqual([])
    expect(store.error).toBe('Server error')
    expect(store.loading).toBe(false)
  })

  it('fetchCourses passes merged filters as query params', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = useCoursesStore()
    await store.fetchCourses({ search: 'makeup', sort: 'price_asc' })

    expect(api.get).toHaveBeenCalledWith('/courses', {
      params: expect.objectContaining({ search: 'makeup', sort: 'price_asc' }),
    })
  })

  // -------------------------------------------------------------------------
  // toggleComplete — optimistic update + rollback
  // -------------------------------------------------------------------------

  it('toggleComplete optimistically flips completed flag before API call', async () => {
    // Set up currentCourse with one lesson that is NOT yet completed
    const store = useCoursesStore()
    store.currentCourse = {
      id: 1,
      sections: [
        {
          id: 10,
          lessons: [{ id: 42, title: 'Lesson A', completed: false }],
        },
      ],
    }

    // Make the API call hang so we can check optimistic state first
    let resolveApi
    api.post.mockReturnValueOnce(
      new Promise((res) => { resolveApi = res })
    )

    const promise = store.toggleComplete(42)

    // Before API resolves: optimistic flip should have happened
    const lesson = store.currentCourse.sections[0].lessons.find((l) => l.id === 42)
    expect(lesson.completed).toBe(true)

    // Resolve the API with the server's confirmed state
    resolveApi({
      data: {
        data: {
          lesson_id: 42,
          completed: true,
          progress: { completed_lessons: 1, total_lessons: 5, percentage: 20 },
        },
      },
    })

    await promise

    // After resolution the lesson should remain true (server confirmed)
    expect(lesson.completed).toBe(true)
  })

  it('toggleComplete rolls back optimistic update on rejected request', async () => {
    const store = useCoursesStore()
    store.currentCourse = {
      id: 1,
      sections: [
        {
          id: 10,
          lessons: [{ id: 55, title: 'Lesson B', completed: false }],
        },
      ],
    }

    api.post.mockRejectedValueOnce(new Error('Network error'))

    await expect(store.toggleComplete(55)).rejects.toThrow('Network error')

    // Rollback: lesson should be back to false
    const lesson = store.currentCourse.sections[0].lessons.find((l) => l.id === 55)
    expect(lesson.completed).toBe(false)
  })

  it('toggleComplete syncs lesson completed flag with server response', async () => {
    const store = useCoursesStore()
    store.currentCourse = {
      id: 1,
      sections: [
        {
          id: 10,
          lessons: [{ id: 77, title: 'Lesson C', completed: true }],
        },
      ],
    }

    // Server says lesson is now false (toggled off)
    api.post.mockResolvedValueOnce({
      data: {
        data: {
          lesson_id: 77,
          completed: false,
          progress: { completed_lessons: 0, total_lessons: 3, percentage: 0 },
        },
      },
    })

    await store.toggleComplete(77)

    const lesson = store.currentCourse.sections[0].lessons.find((l) => l.id === 77)
    expect(lesson.completed).toBe(false)
  })

  it('toggleComplete updates progress in myCourses when course is present', async () => {
    const store = useCoursesStore()
    store.currentCourse = {
      id: 3,
      sections: [{ id: 10, lessons: [{ id: 99, completed: false }] }],
    }
    store.myCourses = [
      { id: 3, title: 'My Course', completed_lessons: 0, progress_percentage: 0 },
    ]

    api.post.mockResolvedValueOnce({
      data: {
        data: {
          lesson_id: 99,
          completed: true,
          progress: { completed_lessons: 2, total_lessons: 4, percentage: 50 },
        },
      },
    })

    await store.toggleComplete(99)

    const myCourse = store.myCourses.find((c) => c.id === 3)
    expect(myCourse.completed_lessons).toBe(2)
    expect(myCourse.progress_percentage).toBe(50)
  })
})

// ---------------------------------------------------------------------------
// Reviews: fetchReviews, submitReview, deleteReview
// ---------------------------------------------------------------------------

describe('courses store — reviews', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  const slug = 'test-course'

  const makeReview = (overrides = {}) => ({
    id: 1,
    rating: 4,
    body: 'Excelente curso',
    created_at: '2026-06-01T10:00:00Z',
    user: { id: 5, name: 'María López', avatar: null },
    ...overrides,
  })

  // ── fetchReviews ────────────────────────────────────────────────────────────

  it('fetchReviews: calls GET /courses/{slug}/reviews and populates reviews', async () => {
    const reviews = [makeReview()]
    api.get.mockResolvedValueOnce({
      data: { data: reviews, meta: { current_page: 1, last_page: 1, total: 1 } },
    })

    const store = useCoursesStore()
    await store.fetchReviews(slug)

    expect(api.get).toHaveBeenCalledWith(`/courses/${slug}/reviews`)
    expect(store.reviews).toEqual(reviews)
    expect(store.reviewsMeta).toMatchObject({ current_page: 1, total: 1 })
    expect(store.reviewsLoading).toBe(false)
  })

  it('fetchReviews: sets reviewError on failure', async () => {
    api.get.mockRejectedValueOnce({
      response: { data: { message: 'Error al cargar valoraciones' } },
    })

    const store = useCoursesStore()
    await store.fetchReviews(slug)

    expect(store.reviewError).toBe('Error al cargar valoraciones')
    expect(store.reviews).toEqual([])
  })

  it('fetchReviews: reviewsLoading toggles true → false', async () => {
    let loadingDuringCall = false
    api.get.mockImplementationOnce(async () => {
      loadingDuringCall = true // would be true during the await
      return { data: { data: [], meta: null } }
    })

    const store = useCoursesStore()
    await store.fetchReviews(slug)

    expect(loadingDuringCall).toBe(true)
    expect(store.reviewsLoading).toBe(false)
  })

  // ── submitReview ────────────────────────────────────────────────────────────

  it('submitReview: POSTs to /courses/{slug}/reviews with payload', async () => {
    const created = makeReview({ rating: 5, body: 'Perfecto' })
    api.post.mockResolvedValueOnce({ data: { data: created } })
    // fetchReviews refetch
    api.get.mockResolvedValueOnce({ data: { data: [created], meta: null } })
    // fetchCourse refetch
    api.get.mockResolvedValueOnce({ data: { data: { id: 1, slug } } })

    const store = useCoursesStore()
    const result = await store.submitReview(slug, { rating: 5, body: 'Perfecto' })

    expect(api.post).toHaveBeenCalledWith(`/courses/${slug}/reviews`, { rating: 5, body: 'Perfecto' })
    expect(result).toEqual(created)
  })

  it('submitReview: calls fetchReviews AND fetchCourse after success', async () => {
    const created = makeReview()
    api.post.mockResolvedValueOnce({ data: { data: created } })
    // fetchReviews
    api.get.mockResolvedValueOnce({ data: { data: [created], meta: null } })
    // fetchCourse
    api.get.mockResolvedValueOnce({ data: { data: { id: 1, slug, average_rating: 4.0, reviews_count: 1, my_review: created } } })

    const store = useCoursesStore()
    await store.submitReview(slug, { rating: 4, body: null })

    // GET should have been called twice: reviews + course
    const getCalls = api.get.mock.calls.map((c) => c[0])
    expect(getCalls).toContain(`/courses/${slug}/reviews`)
    expect(getCalls).toContain(`/courses/${slug}`)
  })

  it('submitReview: sets reviewError and rethrows on failure', async () => {
    api.post.mockRejectedValueOnce({
      response: { data: { message: 'No autorizado' } },
    })

    const store = useCoursesStore()
    await expect(store.submitReview(slug, { rating: 3, body: null })).rejects.toBeDefined()
    expect(store.reviewError).toBe('No autorizado')
    expect(store.reviewSubmitting).toBe(false)
  })

  it('submitReview: reviewSubmitting toggles false after completion', async () => {
    const created = makeReview()
    api.post.mockResolvedValueOnce({ data: { data: created } })
    api.get.mockResolvedValueOnce({ data: { data: [created], meta: null } })
    api.get.mockResolvedValueOnce({ data: { data: { id: 1, slug } } })

    const store = useCoursesStore()
    await store.submitReview(slug, { rating: 4, body: null })
    expect(store.reviewSubmitting).toBe(false)
  })

  // ── deleteReview ────────────────────────────────────────────────────────────

  it('deleteReview: calls DELETE /courses/{slug}/reviews', async () => {
    api.delete.mockResolvedValueOnce({})
    api.get.mockResolvedValueOnce({ data: { data: [], meta: null } })
    api.get.mockResolvedValueOnce({ data: { data: { id: 1, slug } } })

    const store = useCoursesStore()
    await store.deleteReview(slug)

    expect(api.delete).toHaveBeenCalledWith(`/courses/${slug}/reviews`)
  })

  it('deleteReview: refetches reviews and course after success', async () => {
    api.delete.mockResolvedValueOnce({})
    api.get.mockResolvedValueOnce({ data: { data: [], meta: null } })
    api.get.mockResolvedValueOnce({ data: { data: { id: 1, slug } } })

    const store = useCoursesStore()
    await store.deleteReview(slug)

    const getCalls = api.get.mock.calls.map((c) => c[0])
    expect(getCalls).toContain(`/courses/${slug}/reviews`)
    expect(getCalls).toContain(`/courses/${slug}`)
  })

  it('deleteReview: sets reviewError and rethrows on failure', async () => {
    api.delete.mockRejectedValueOnce({
      response: { data: { message: 'Valoración no encontrada' } },
    })

    const store = useCoursesStore()
    await expect(store.deleteReview(slug)).rejects.toBeDefined()
    expect(store.reviewError).toBe('Valoración no encontrada')
    expect(store.reviewDeleting).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// Certificate: fetchCertificate
// ---------------------------------------------------------------------------

describe('courses store — certificate', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  const slug = 'makeup-avanzado'

  const fakeCert = {
    code: 'CERT-ABC-123',
    issued_at: '2026-06-16T00:00:00Z',
    student_name: 'María López',
    course_title: 'Makeup Avanzado',
    instructor_name: 'Ana García',
  }

  it('fetchCertificate: GETs /courses/{slug}/certificate and populates certificate', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeCert } })

    const store = useCoursesStore()
    const result = await store.fetchCertificate(slug)

    expect(api.get).toHaveBeenCalledWith(`/courses/${slug}/certificate`)
    expect(store.certificate).toEqual(fakeCert)
    expect(result).toEqual(fakeCert)
    expect(store.certificateLoading).toBe(false)
    expect(store.certificateError).toBeNull()
  })

  it('fetchCertificate: handles response without nested data key', async () => {
    api.get.mockResolvedValueOnce({ data: fakeCert })

    const store = useCoursesStore()
    await store.fetchCertificate(slug)

    expect(store.certificate).toEqual(fakeCert)
  })

  it('fetchCertificate: on 403 sets certificateError and rethrows', async () => {
    const err = {
      response: { status: 403, data: { message: 'Debes tener todas las prácticas aprobadas.' } },
    }
    api.get.mockRejectedValueOnce(err)

    const store = useCoursesStore()

    await expect(store.fetchCertificate(slug)).rejects.toBeDefined()
    expect(store.certificateError).toBe('Debes tener todas las prácticas aprobadas.')
    expect(store.certificateLoading).toBe(false)
    expect(store.certificate).toBeNull()
  })

  it('fetchCertificate: sets generic error when response has no message', async () => {
    api.get.mockRejectedValueOnce(new Error('Network error'))

    const store = useCoursesStore()

    await expect(store.fetchCertificate(slug)).rejects.toBeDefined()
    expect(store.certificateError).toBe('No se pudo obtener el certificado')
    expect(store.certificateLoading).toBe(false)
  })

  it('fetchCertificate: certificateLoading toggles true → false on success', async () => {
    let loadingDuringCall = false
    api.get.mockImplementationOnce(async () => {
      loadingDuringCall = true
      return { data: { data: fakeCert } }
    })

    const store = useCoursesStore()
    await store.fetchCertificate(slug)

    expect(loadingDuringCall).toBe(true)
    expect(store.certificateLoading).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// Categories: fetchCategories
// ---------------------------------------------------------------------------

describe('courses store — categories', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchCategories: GETs /categories and populates categories from data.data', async () => {
    const fakeCategories = [
      { id: 1, name: 'Editorial', slug: 'editorial' },
      { id: 2, name: 'Novias', slug: 'novias' },
    ]
    api.get.mockResolvedValueOnce({ data: { data: fakeCategories } })

    const store = useCoursesStore()
    await store.fetchCategories()

    expect(api.get).toHaveBeenCalledWith('/categories')
    expect(store.categories).toEqual(fakeCategories)
  })

  it('fetchCategories: handles flat response (no nested data key)', async () => {
    const fakeCategories = [{ id: 1, name: 'Noche', slug: 'noche' }]
    api.get.mockResolvedValueOnce({ data: fakeCategories })

    const store = useCoursesStore()
    await store.fetchCategories()

    expect(store.categories).toEqual(fakeCategories)
  })

  it('fetchCategories: leaves categories empty on error (no throw)', async () => {
    api.get.mockRejectedValueOnce(new Error('Network error'))

    const store = useCoursesStore()
    await expect(store.fetchCategories()).resolves.toBeUndefined()
    expect(store.categories).toEqual([])
  })

  it('fetchCourses passes category slug in params when category filter is set', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = useCoursesStore()
    await store.fetchCourses({ category: 'novias' })

    expect(api.get).toHaveBeenCalledWith('/courses', {
      params: expect.objectContaining({ category: 'novias' }),
    })
  })

  it('fetchCourses omits category param when category is empty string', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = useCoursesStore()
    await store.fetchCourses({ category: '' })

    const callArgs = api.get.mock.calls[0]
    expect(callArgs[1].params).not.toHaveProperty('category')
  })
})

describe('courses store — practice submissions', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('submitPractice: POSTs FormData to /lessons/{id}/submissions with multipart header', async () => {
    const submission = {
      id: 1, lesson_id: 5, status: 'pending', feedback: null,
      before_url: 'http://ex.com/b.jpg', after_url: 'http://ex.com/a.jpg',
      created_at: '2026-06-16T00:00:00Z', graded_at: null,
      user: { id: 1, name: 'Ana', avatar: null },
      lesson: { id: 5, title: 'Contorno básico' },
    }
    api.post.mockResolvedValueOnce({ data: { data: submission } })

    const store = useCoursesStore()
    const before = new File(['b'], 'before.jpg', { type: 'image/jpeg' })
    const after = new File(['a'], 'after.jpg', { type: 'image/jpeg' })

    const result = await store.submitPractice(5, { before, after })

    expect(api.post).toHaveBeenCalledWith(
      '/lessons/5/submissions',
      expect.any(FormData),
      expect.objectContaining({
        headers: expect.objectContaining({ 'Content-Type': 'multipart/form-data' }),
      })
    )
    expect(result).toEqual(submission)
    expect(store.submissionSubmitting).toBe(false)
  })

  it('submitPractice: updates currentLesson.my_submission on success', async () => {
    const submission = { id: 2, status: 'pending', feedback: null, before_url: 'b', after_url: 'a', lesson_id: 5, created_at: '', graded_at: null, user: { id: 1, name: 'A', avatar: null }, lesson: { id: 5, title: 'L' } }
    api.post.mockResolvedValueOnce({ data: { data: submission } })

    const store = useCoursesStore()
    store.currentLesson = { id: 5, title: 'Contorno', is_practice: true, my_submission: null }

    const before = new File(['b'], 'b.jpg', { type: 'image/jpeg' })
    const after = new File(['a'], 'a.jpg', { type: 'image/jpeg' })
    await store.submitPractice(5, { before, after })

    expect(store.currentLesson.my_submission).toEqual(submission)
  })

  it('submitPractice: sets submissionError and rethrows on failure', async () => {
    api.post.mockRejectedValueOnce({
      response: { data: { message: 'No autorizado' } },
    })

    const store = useCoursesStore()
    const before = new File(['b'], 'b.jpg', { type: 'image/jpeg' })
    const after = new File(['a'], 'a.jpg', { type: 'image/jpeg' })

    await expect(store.submitPractice(5, { before, after })).rejects.toBeDefined()
    expect(store.submissionError).toBe('No autorizado')
    expect(store.submissionSubmitting).toBe(false)
  })
})
