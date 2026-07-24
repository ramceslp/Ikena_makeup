import { defineStore } from 'pinia'
import api from '../services/api.js'

// Trimmed port of frontend/src/stores/courses.js: only fetchCourses(),
// needed by FeaturedCourses.vue for Home's "3 most-recent courses" section.
// Course detail/enroll/lesson/review/certificate actions belong to a full
// course-catalog surface that is out of scope for this app -- the spec's
// Requirements list a Product Catalog and a Service Catalog, but no Course
// Catalog; courses only ever appear as a Home "featured" teaser here.
export const useCoursesStore = defineStore('courses', {
  state: () => ({
    courses: [],
    meta: null,
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
        const params = {}
        for (const [key, value] of Object.entries(filters)) {
          if (value !== '' && value !== null && value !== undefined) {
            params[key] = value
          }
        }
        const response = await api.get('/courses', { params })
        this.courses = response.data.data
        this.meta = response.data.meta
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar los cursos'
      } finally {
        this.loading = false
      }
    },
  },
})
