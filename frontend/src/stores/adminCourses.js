import { defineStore } from 'pinia'
import api from '../services/api.js'

/**
 * Catalog governance for courses.
 *
 * Deliberately separate from the `instructor` store: that one models "my
 * courses" and owns the sections/lessons authoring tree, while this one models
 * the academy-wide catalog. Merging them would force one `courses` array to
 * mean two different things depending on who is logged in.
 *
 * Deep authoring is not duplicated here — the list links out to the instructor
 * editor, which admins may open for any course.
 */
export const useAdminCoursesStore = defineStore('adminCourses', {
  state: () => ({
    courses: [],
    meta: null,
    currentCourse: null,
    instructors: [],
    categories: [],
    loading: false,
    error: null,
    validationErrors: {},
  }),

  actions: {
    _clearErrors() {
      this.error = null
      this.validationErrors = {}
    },

    _handleError(err) {
      if (err.response?.status === 422) {
        this.validationErrors = err.response.data?.errors ?? {}
      } else {
        this.error = err.response?.data?.message ?? err.message ?? 'Error inesperado'
      }
    },

    // ── Catalog ──────────────────────────────────────────────────────────────

    async fetchCourses(filters = {}) {
      this._clearErrors()
      this.loading = true
      try {
        // Build a local params object — never mutate shared state here, or a
        // later call with no args silently inherits the previous filters.
        const params = {}
        for (const [key, value] of Object.entries(filters)) {
          if (value !== '' && value !== null && value !== undefined) {
            params[key] = value
          }
        }
        const { data } = await api.get('/admin/courses', { params })
        this.courses = data.data ?? []
        this.meta = data.meta ?? null
      } catch (err) {
        this._handleError(err)
      } finally {
        this.loading = false
      }
    },

    async fetchCourse(id) {
      this._clearErrors()
      // Dropped before the request, not after: the store outlives the view, so
      // opening a second course would otherwise render the previous one's data
      // while the new fetch is still in flight.
      this.currentCourse = null
      this.loading = true
      try {
        const { data } = await api.get(`/admin/courses/${id}`)
        this.currentCourse = data.data ?? data
        return this.currentCourse
      } catch (err) {
        this._handleError(err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async createCourse(payload) {
      this._clearErrors()
      this.loading = true
      try {
        const { data } = await api.post('/admin/courses', payload)
        return data.data ?? data
      } catch (err) {
        this._handleError(err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async updateCourse(id, payload) {
      this._clearErrors()
      this.loading = true
      try {
        const { data } = await api.patch(`/admin/courses/${id}`, payload)
        this.currentCourse = data.data ?? data
        return this.currentCourse
      } catch (err) {
        this._handleError(err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async deleteCourse(id) {
      this._clearErrors()
      try {
        await api.delete(`/admin/courses/${id}`)
        this.courses = this.courses.filter((c) => c.id !== id)
      } catch (err) {
        this._handleError(err)
        throw err
      }
    },

    // ── Publish state ────────────────────────────────────────────────────────
    //
    // Not optimistic, unlike the instructor store: publish can legitimately be
    // refused with 422 when the course has no lessons, and flashing "Publicado"
    // before that answer arrives would misreport the catalog to the admin.

    async publish(id) {
      this._clearErrors()
      try {
        const { data } = await api.post(`/admin/courses/${id}/publish`)
        this._mergeCourse(id, data.data ?? data)
      } catch (err) {
        this._handleError(err)
        throw err
      }
    },

    async unpublish(id) {
      this._clearErrors()
      try {
        const { data } = await api.post(`/admin/courses/${id}/unpublish`)
        this._mergeCourse(id, data.data ?? data)
      } catch (err) {
        this._handleError(err)
        throw err
      }
    },

    _mergeCourse(id, updated) {
      this.courses = this.courses.map((c) => (c.id === id ? { ...c, ...updated } : c))
      if (this.currentCourse?.id === id) {
        this.currentCourse = { ...this.currentCourse, ...updated }
      }
    },

    // ── Form reference data ──────────────────────────────────────────────────

    async fetchInstructors() {
      if (this.instructors.length > 0) return
      try {
        const { data } = await api.get('/admin/instructors')
        this.instructors = data.data ?? data
      } catch {
        // Leave empty — the form surfaces its own "no instructors" state.
      }
    },

    async fetchCategories() {
      if (this.categories.length > 0) return
      try {
        const { data } = await api.get('/categories')
        this.categories = data.data ?? data
      } catch {
        // Leave categories empty — non-critical, the field is optional.
      }
    },
  },
})
