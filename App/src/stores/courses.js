import { defineStore } from 'pinia'
import api from '../services/api.js'
import { buildParams } from './shared/buildParams.js'

// Trimmed port of frontend/src/stores/courses.js: fetchCourses() (Home's
// "3 most-recent courses" section) plus fetchCourse()/fetchCategories() added
// for the Courses.vue/CourseDetail.vue catalog surface -- same extension
// pattern already used by stores/products.js and stores/services.js (see
// their file-level comments for the identical fetchProduct/fetchService +
// fetchCategories precedent). Enroll/lesson/review/certificate actions are
// still NOT ported: this app has no /learn player, no checkout flow for
// courses, and no review UI, so those actions would have no caller (see
// views/CourseDetail.vue's file-level comment for the full boundary).
export const useCoursesStore = defineStore('courses', {
  state: () => ({
    courses: [],
    meta: null,
    categories: [],
    currentCourse: null,
    loading: false,
    error: null,
  }),

  actions: {
    async fetchCourses(filters = {}) {
      this.loading = true
      this.error = null
      try {
        // Build a local params object -- do NOT mutate any shared filters
        // state (this store has none, unlike the full web version, but the
        // pattern is kept consistent with posts/services/products.js).
        const params = buildParams(filters)
        const response = await api.get('/courses', { params })
        this.courses = response.data.data
        this.meta = response.data.meta
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar los cursos'
      } finally {
        this.loading = false
      }
    },

    async fetchCourse(slug) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get(`/courses/${slug}`)
        this.currentCourse = response.data.data
        return this.currentCourse
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar el curso'
        throw err
      } finally {
        this.loading = false
      }
    },

    async fetchCategories() {
      // Same "fetch once" guard as products.js/services.js -- categories are
      // shared/static reference data, not filtered per-request.
      if (this.categories.length > 0) return
      try {
        const { data } = await api.get('/categories')
        this.categories = data.data ?? data
      } catch {
        // Leave categories empty — non-critical
      }
    },
  },
})
