import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

vi.mock('@capacitor/browser', () => ({
  Browser: { open: vi.fn().mockResolvedValue(undefined) },
}))

import api from '../services/api.js'
import { Browser } from '@capacitor/browser'
import { useMyCoursesStore } from '../stores/myCourses.js'

const enrolledCourse = {
  id: 4,
  title: 'Maquillaje Nupcial',
  slug: 'maquillaje-nupcial',
  thumbnail: null,
  instructor: { id: 3, name: 'Ana Torres' },
  total_lessons: 10,
  completed_lessons: 4,
  progress_percentage: 40,
  web_url: 'https://ikena.test/learn/maquillaje-nupcial',
}

describe('myCourses store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetches the enrolled courses from GET /my-courses', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [enrolledCourse] } })

    const store = useMyCoursesStore()
    await store.fetchMyCourses()

    expect(api.get).toHaveBeenCalledWith('/my-courses')
    expect(store.courses).toHaveLength(1)
    expect(store.courses[0].progress_percentage).toBe(40)
    expect(store.error).toBeNull()
    expect(store.loading).toBe(false)
  })

  it('surfaces the backend message on failure instead of a silent empty list', async () => {
    const failure = new Error('Server Error')
    failure.response = { status: 500, data: { message: 'Algo salió mal' } }
    api.get.mockRejectedValueOnce(failure)

    const store = useMyCoursesStore()
    await store.fetchMyCourses()

    expect(store.error).toBe('Algo salió mal')
    expect(store.courses).toEqual([])
  })

  it('falls back to a generic Spanish message when the failure has no body', async () => {
    api.get.mockRejectedValueOnce(new Error('Network Error'))

    const store = useMyCoursesStore()
    await store.fetchMyCourses()

    expect(store.error).toBe('Error al cargar tus cursos')
  })

  describe('openCourse', () => {
    it('opens the API-supplied web player URL in the system browser', async () => {
      const store = useMyCoursesStore()

      expect(await store.openCourse(enrolledCourse)).toBe(true)
      expect(Browser.open).toHaveBeenCalledWith({
        url: 'https://ikena.test/learn/maquillaje-nupcial',
      })
      expect(store.openError).toBeNull()
    })

    it('refuses to open a course with no web_url', async () => {
      const store = useMyCoursesStore()

      expect(await store.openCourse({ ...enrolledCourse, web_url: undefined })).toBe(false)
      expect(Browser.open).not.toHaveBeenCalled()
      expect(store.openError).toBeTruthy()
    })

    it.each([
      ['javascript:', 'javascript:alert(1)'],
      ['data:', 'data:text/html,<script>alert(1)</script>'],
      ['a relative path', '/learn/maquillaje-nupcial'],
    ])('refuses a %s web_url', async (_label, url) => {
      const store = useMyCoursesStore()

      expect(await store.openCourse({ ...enrolledCourse, web_url: url })).toBe(false)
      expect(Browser.open).not.toHaveBeenCalled()
      expect(store.openError).toBeTruthy()
    })

    it('records an error rather than throwing when the browser bridge fails', async () => {
      Browser.open.mockRejectedValueOnce(new Error('Bridge unavailable'))
      const store = useMyCoursesStore()

      expect(await store.openCourse(enrolledCourse)).toBe(false)
      expect(store.openError).toBeTruthy()
    })

    it('clears a previous openError on the next attempt', async () => {
      const store = useMyCoursesStore()
      await store.openCourse({ ...enrolledCourse, web_url: null })
      expect(store.openError).toBeTruthy()

      await store.openCourse(enrolledCourse)
      expect(store.openError).toBeNull()
    })
  })
})
