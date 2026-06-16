import { defineStore } from 'pinia'
import api from '../services/api.js'

export const useCoursesStore = defineStore('courses', {
  state: () => ({
    courses: [],
    meta: null,
    filters: {
      search: '',
      min_price: '',
      max_price: '',
      sort: 'newest',
      page: 1,
    },
    currentCourse: null,
    myCourses: [],
    currentLesson: null,
    loading: false,
    error: null,
    // Reviews
    reviews: [],
    reviewsMeta: null,
    reviewsLoading: false,
    reviewSubmitting: false,
    reviewDeleting: false,
    reviewError: null,
    // Practice submissions
    submissionSubmitting: false,
    submissionError: null,
    // Certificate
    certificate: null,
    certificateLoading: false,
    certificateError: null,
  }),

  actions: {
    async fetchCourses(filters = {}) {
      this.loading = true
      this.error = null
      try {
        const merged = { ...this.filters, ...filters }
        this.filters = merged
        // Remove empty values so they don't pollute the query string
        const params = {}
        for (const [key, value] of Object.entries(merged)) {
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

    async enroll(slug) {
      const response = await api.post(`/courses/${slug}/enroll`)
      return response.data.data
    },

    async fetchMyCourses() {
      this.loading = true
      this.error = null
      try {
        const response = await api.get('/my-courses')
        this.myCourses = response.data.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar tus cursos'
      } finally {
        this.loading = false
      }
    },

    async fetchLesson(id) {
      const response = await api.get(`/lessons/${id}`)
      this.currentLesson = response.data.data
      return this.currentLesson
    },

    async checkout(slug) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post(`/courses/${slug}/checkout`)
        return response.data.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al iniciar el pago'
        throw err
      } finally {
        this.loading = false
      }
    },

    async confirmPayment({ id, clientTransactionId }) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post('/payments/confirm', { id, clientTransactionId })
        return response.data.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al confirmar el pago'
        throw err
      } finally {
        this.loading = false
      }
    },

    async fetchReviews(slug) {
      this.reviewsLoading = true
      this.reviewError = null
      try {
        const response = await api.get(`/courses/${slug}/reviews`)
        this.reviews = response.data.data
        this.reviewsMeta = response.data.meta ?? null
      } catch (err) {
        this.reviewError = err.response?.data?.message || 'Error al cargar las valoraciones'
      } finally {
        this.reviewsLoading = false
      }
    },

    async submitReview(slug, { rating, body }) {
      this.reviewSubmitting = true
      this.reviewError = null
      try {
        const response = await api.post(`/courses/${slug}/reviews`, { rating, body })
        await this.fetchReviews(slug)
        await this.fetchCourse(slug)
        return response.data.data
      } catch (err) {
        this.reviewError = err.response?.data?.message || 'Error al publicar la valoración'
        throw err
      } finally {
        this.reviewSubmitting = false
      }
    },

    async deleteReview(slug) {
      this.reviewDeleting = true
      this.reviewError = null
      try {
        await api.delete(`/courses/${slug}/reviews`)
        await this.fetchReviews(slug)
        await this.fetchCourse(slug)
      } catch (err) {
        this.reviewError = err.response?.data?.message || 'Error al eliminar la valoración'
        throw err
      } finally {
        this.reviewDeleting = false
      }
    },

    async submitPractice(lessonId, { before, after }) {
      this.submissionError = null
      this.submissionSubmitting = true
      try {
        const fd = new FormData()
        fd.append('before', before)
        fd.append('after', after)
        const { data } = await api.post(
          `/lessons/${lessonId}/submissions`,
          fd,
          { headers: { 'Content-Type': 'multipart/form-data' } }
        )
        const submission = data.data ?? data
        if (this.currentLesson) this.currentLesson.my_submission = submission
        return submission
      } catch (err) {
        this.submissionError = err.response?.data?.message || 'Error al enviar la entrega'
        throw err
      } finally {
        this.submissionSubmitting = false
      }
    },

    async fetchCertificate(slug) {
      this.certificateLoading = true
      this.certificateError = null
      try {
        const { data } = await api.get(`/courses/${slug}/certificate`)
        this.certificate = data.data ?? data
        return this.certificate
      } catch (err) {
        this.certificateError = err.response?.data?.message || 'No se pudo obtener el certificado'
        throw err
      } finally {
        this.certificateLoading = false
      }
    },

    async toggleComplete(lessonId) {
      // Optimistic update: toggle the completed flag in currentCourse sections
      if (this.currentCourse?.sections) {
        for (const section of this.currentCourse.sections) {
          const lesson = section.lessons?.find((l) => l.id === lessonId)
          if (lesson) {
            lesson.completed = !lesson.completed
            break
          }
        }
      }

      try {
        const response = await api.post(`/lessons/${lessonId}/complete`)
        const { lesson_id, completed, progress } = response.data.data

        // Sync with server response
        if (this.currentCourse?.sections) {
          for (const section of this.currentCourse.sections) {
            const lesson = section.lessons?.find((l) => l.id === lesson_id)
            if (lesson) {
              lesson.completed = completed
              break
            }
          }
        }

        // Also update currentLesson if it matches
        if (this.currentLesson?.id === lesson_id) {
          this.currentLesson.completed = completed
        }

        // Update myCourses progress if the course is there
        if (progress && this.currentCourse) {
          const myCourseSlugs = this.myCourses.find(
            (c) => c.id === this.currentCourse.id
          )
          if (myCourseSlugs) {
            myCourseSlugs.completed_lessons = progress.completed_lessons
            myCourseSlugs.progress_percentage = progress.percentage
          }
        }

        return response.data.data
      } catch (err) {
        // Rollback optimistic update on error
        if (this.currentCourse?.sections) {
          for (const section of this.currentCourse.sections) {
            const lesson = section.lessons?.find((l) => l.id === lessonId)
            if (lesson) {
              lesson.completed = !lesson.completed
              break
            }
          }
        }
        throw err
      }
    },
  },
})
