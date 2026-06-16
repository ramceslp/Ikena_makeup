import { defineStore } from 'pinia'
import api from '../services/api.js'

export const useInstructorStore = defineStore('instructor', {
  state: () => ({
    myCourses: [],       // InstructorCourseCard[]
    currentCourse: null, // InstructorCourseDetail
    dashboard: null,     // { kpis, sales_over_time }
    loading: false,
    error: null,
    validationErrors: {},
    // Practice submissions
    submissions: [],
    submissionsMeta: null,
    submissionsLoading: false,
    grading: false,
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

    // ── Dashboard ─────────────────────────────────────────────────────────────

    async fetchDashboard() {
      this._clearErrors()
      this.loading = true
      try {
        const { data } = await api.get('/instructor/dashboard')
        this.dashboard = data.data ?? data
      } catch (err) {
        this._handleError(err)
      } finally {
        this.loading = false
      }
    },

    // ── Courses ──────────────────────────────────────────────────────────────

    async fetchMyCourses() {
      this._clearErrors()
      this.loading = true
      try {
        const { data } = await api.get('/instructor/courses')
        this.myCourses = data.data ?? data
      } catch (err) {
        this._handleError(err)
      } finally {
        this.loading = false
      }
    },

    async createCourse(payload) {
      this._clearErrors()
      this.loading = true
      try {
        const { data } = await api.post('/instructor/courses', payload)
        const course = data.data ?? data
        this.myCourses.push(course)
        return course
      } catch (err) {
        this._handleError(err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async fetchCourse(slug) {
      this._clearErrors()
      this.loading = true
      try {
        const { data } = await api.get(`/instructor/courses/${slug}`)
        this.currentCourse = data.data ?? data
      } catch (err) {
        this._handleError(err)
      } finally {
        this.loading = false
      }
    },

    async updateCourse(slug, payload) {
      this._clearErrors()
      this.loading = true
      try {
        const { data } = await api.patch(`/instructor/courses/${slug}`, payload)
        this.currentCourse = data.data ?? data
      } catch (err) {
        this._handleError(err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async deleteCourse(slug) {
      this._clearErrors()
      try {
        await api.delete(`/instructor/courses/${slug}`)
        this.myCourses = this.myCourses.filter((c) => c.slug !== slug)
      } catch (err) {
        this._handleError(err)
        throw err
      }
    },

    // Optimistic publish
    async publish(slug) {
      this._clearErrors()
      const prevMyCourses = this.myCourses.map((c) => ({ ...c }))
      const prevCurrent = this.currentCourse ? { ...this.currentCourse } : null

      // Optimistic update
      this.myCourses = this.myCourses.map((c) =>
        c.slug === slug ? { ...c, is_published: true } : c
      )
      if (this.currentCourse?.slug === slug) {
        this.currentCourse = { ...this.currentCourse, is_published: true }
      }

      try {
        const { data } = await api.post(`/instructor/courses/${slug}/publish`)
        const updated = data.data ?? data
        this.myCourses = this.myCourses.map((c) => (c.slug === slug ? { ...c, ...updated } : c))
        if (this.currentCourse?.slug === slug) {
          this.currentCourse = { ...this.currentCourse, ...updated }
        }
      } catch (err) {
        // Rollback
        this.myCourses = prevMyCourses
        this.currentCourse = prevCurrent
        this._handleError(err)
        throw err
      }
    },

    // Optimistic unpublish
    async unpublish(slug) {
      this._clearErrors()
      const prevMyCourses = this.myCourses.map((c) => ({ ...c }))
      const prevCurrent = this.currentCourse ? { ...this.currentCourse } : null

      // Optimistic update
      this.myCourses = this.myCourses.map((c) =>
        c.slug === slug ? { ...c, is_published: false } : c
      )
      if (this.currentCourse?.slug === slug) {
        this.currentCourse = { ...this.currentCourse, is_published: false }
      }

      try {
        const { data } = await api.post(`/instructor/courses/${slug}/unpublish`)
        const updated = data.data ?? data
        this.myCourses = this.myCourses.map((c) => (c.slug === slug ? { ...c, ...updated } : c))
        if (this.currentCourse?.slug === slug) {
          this.currentCourse = { ...this.currentCourse, ...updated }
        }
      } catch (err) {
        // Rollback
        this.myCourses = prevMyCourses
        this.currentCourse = prevCurrent
        this._handleError(err)
        throw err
      }
    },

    // ── Sections ─────────────────────────────────────────────────────────────

    async createSection(slug, title) {
      this._clearErrors()
      try {
        const { data } = await api.post(`/instructor/courses/${slug}/sections`, { title })
        const section = data.data ?? data
        if (this.currentCourse) {
          this.currentCourse.sections = [...(this.currentCourse.sections ?? []), section]
        }
        return section
      } catch (err) {
        this._handleError(err)
        throw err
      }
    },

    async updateSection(id, payload) {
      this._clearErrors()
      try {
        const { data } = await api.patch(`/instructor/sections/${id}`, payload)
        const updated = data.data ?? data
        if (this.currentCourse) {
          this.currentCourse.sections = this.currentCourse.sections.map((s) =>
            s.id === id ? { ...s, ...updated } : s
          )
        }
        return updated
      } catch (err) {
        this._handleError(err)
        throw err
      }
    },

    async deleteSection(id) {
      this._clearErrors()
      try {
        await api.delete(`/instructor/sections/${id}`)
        if (this.currentCourse) {
          this.currentCourse.sections = this.currentCourse.sections.filter((s) => s.id !== id)
        }
      } catch (err) {
        this._handleError(err)
        throw err
      }
    },

    // Optimistic reorder sections
    async reorderSections(slug, orderedIds) {
      this._clearErrors()
      if (!this.currentCourse) return

      const prevSections = this.currentCourse.sections.map((s) => ({ ...s }))

      // Optimistic: sort sections by the new order
      const sectionMap = Object.fromEntries(this.currentCourse.sections.map((s) => [s.id, s]))
      this.currentCourse.sections = orderedIds.map((id) => sectionMap[id]).filter(Boolean)

      try {
        const { data } = await api.patch(`/instructor/courses/${slug}/sections/reorder`, {
          ordered_ids: orderedIds,
        })
        this.currentCourse.sections = data.data ?? data
      } catch (err) {
        // Rollback
        this.currentCourse.sections = prevSections
        this._handleError(err)
        throw err
      }
    },

    // ── Lessons ──────────────────────────────────────────────────────────────

    async createLesson(sectionId, payload) {
      this._clearErrors()
      try {
        const { data } = await api.post(`/instructor/sections/${sectionId}/lessons`, payload)
        const lesson = data.data ?? data
        if (this.currentCourse) {
          this.currentCourse.sections = this.currentCourse.sections.map((s) =>
            s.id === sectionId ? { ...s, lessons: [...(s.lessons ?? []), lesson] } : s
          )
        }
        return lesson
      } catch (err) {
        this._handleError(err)
        throw err
      }
    },

    async updateLesson(id, payload) {
      this._clearErrors()
      try {
        const { data } = await api.patch(`/instructor/lessons/${id}`, payload)
        const updated = data.data ?? data
        if (this.currentCourse) {
          this.currentCourse.sections = this.currentCourse.sections.map((s) => ({
            ...s,
            lessons: (s.lessons ?? []).map((l) => (l.id === id ? { ...l, ...updated } : l)),
          }))
        }
        return updated
      } catch (err) {
        this._handleError(err)
        throw err
      }
    },

    async deleteLesson(id) {
      this._clearErrors()
      try {
        await api.delete(`/instructor/lessons/${id}`)
        if (this.currentCourse) {
          this.currentCourse.sections = this.currentCourse.sections.map((s) => ({
            ...s,
            lessons: (s.lessons ?? []).filter((l) => l.id !== id),
          }))
        }
      } catch (err) {
        this._handleError(err)
        throw err
      }
    },

    // ── Practice Submissions ──────────────────────────────────────────────────

    async fetchSubmissions(status = null) {
      this._clearErrors()
      this.submissionsLoading = true
      try {
        const params = {}
        if (status) params.status = status
        const { data } = await api.get('/instructor/submissions', { params })
        this.submissions = data.data ?? data
        this.submissionsMeta = data.meta ?? null
      } catch (err) {
        this._handleError(err)
      } finally {
        this.submissionsLoading = false
      }
    },

    async gradeSubmission(id, { status, feedback }) {
      this._clearErrors()
      this.grading = true
      try {
        const { data } = await api.patch(`/instructor/submissions/${id}`, { status, feedback })
        const updated = data.data ?? data
        this.submissions = this.submissions.map((s) => (s.id === id ? { ...s, ...updated } : s))
        return updated
      } catch (err) {
        this._handleError(err)
        throw err
      } finally {
        this.grading = false
      }
    },

    // Optimistic reorder lessons
    async reorderLessons(sectionId, orderedIds) {
      this._clearErrors()
      if (!this.currentCourse) return

      const prevSections = this.currentCourse.sections.map((s) => ({
        ...s,
        lessons: (s.lessons ?? []).map((l) => ({ ...l })),
      }))

      // Optimistic: sort lessons in the target section
      this.currentCourse.sections = this.currentCourse.sections.map((s) => {
        if (s.id !== sectionId) return s
        const lessonMap = Object.fromEntries((s.lessons ?? []).map((l) => [l.id, l]))
        return { ...s, lessons: orderedIds.map((id) => lessonMap[id]).filter(Boolean) }
      })

      try {
        const { data } = await api.patch(`/instructor/sections/${sectionId}/lessons/reorder`, {
          ordered_ids: orderedIds,
        })
        const updated = data.data ?? data
        this.currentCourse.sections = this.currentCourse.sections.map((s) =>
          s.id === sectionId ? { ...s, lessons: updated } : s
        )
      } catch (err) {
        // Rollback
        this.currentCourse.sections = prevSections
        this._handleError(err)
        throw err
      }
    },
  },
})
