import { defineStore } from 'pinia'
import api from '../services/api.js'
import { startCheckoutHandoff } from '../services/checkoutHandoff.js'
import { openMeeting } from '../services/meetingLauncher.js'
import { buildParams } from './shared/buildParams.js'

// Trimmed port of frontend/src/stores/courses.js: fetchCourses() (Home's
// "3 most-recent courses" section) plus fetchCourse()/fetchCategories() added
// for the Courses.vue/CourseDetail.vue catalog surface -- same extension
// pattern already used by stores/products.js and stores/services.js (see
// their file-level comments for the identical fetchProduct/fetchService +
// fetchCategories precedent).
//
// enroll() is now ported as well (see its own docblock). Review/certificate
// actions remain out of scope: this app still has no /learn player -- the
// profile's "Mis cursos" section links out to the web one -- and no review UI,
// so they would have no caller.
//
// joinSession() is the one lesson-scoped action that exists here, and it is
// not a step toward a player: a live session is attended in Meet or Zoom, so
// the app only has to hand the room off to the system browser. A recorded
// lesson still belongs to the web player.
export const useCoursesStore = defineStore('courses', {
  state: () => ({
    courses: [],
    meta: null,
    categories: [],
    currentCourse: null,
    loading: false,
    error: null,
    // enroll() state. Deliberately separate from `loading`/`error`, which
    // belong to the catalog fetches -- an enrollment failure must not blank
    // out the course the user is currently reading.
    enrolling: false,
    enrollError: null,
    // joinSession() state, separate from enroll()'s for the same reason: a
    // closed meeting window must not blank out the enrollment CTA.
    joiningLessonId: null,
    sessionError: null,
    // Id of the course whose PAID checkout has been opened in the system
    // browser, so its CTA can switch to a "finish in your browser" state
    // rather than minting a second handoff token on a second tap.
    //
    // An id, NOT a boolean: as a boolean this leaked across courses. Hand off
    // course A, then open course B, and B rendered A's "Abrimos el pago en tu
    // navegador" panel with no enroll button at all — course B became
    // impossible to buy without restarting the app. Found on the emulator;
    // every unit test passed, because none of them viewed a second course
    // after a handoff.
    enrollHandoffCourseId: null,
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
      // An enrollment error belongs to the course that produced it. Opening a
      // different course must not inherit it — same leak class as the
      // enrollHandoffCourseId comment above. (The handoff id is NOT cleared
      // here: it is already scoped by id, and clearing it would wipe the
      // "finish in your browser" panel for the very course the user is
      // returning to.)
      this.enrollError = null
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

    /**
     * Enroll the current user in `course`.
     *
     * Branches on price, matching how the backend partitions the two entry
     * points (CourseController::enroll refuses paid courses;
     * CourseCheckoutAction refuses free ones):
     *
     *   free (price <= 0) -> POST /courses/{slug}/enroll, in-app, done. There
     *       is no money and no gateway involved, so bouncing the user out to a
     *       browser for it would be pure friction.
     *   paid              -> POST /checkout/handoff (type: course) and open
     *       the web checkout in the system browser. The Enrollment is created
     *       server-side only after the gateway approves
     *       (CheckoutController::confirm), never optimistically here.
     *
     * @returns {Promise<'enrolled'|'handoff'|null>} null on failure
     */
    async enroll(course) {
      this.enrollError = null

      if (!course?.slug || course?.id == null) {
        this.enrollError = 'No se pudo inscribirte en este curso.'
        return null
      }

      if (course.is_enrolled) {
        // Not an error worth surfacing — the CTA should already be showing the
        // enrolled state; this is the belt-and-braces guard behind it.
        return 'enrolled'
      }

      const price = parseFloat(course.price)
      const isFree = !Number.isFinite(price) || price <= 0

      this.enrolling = true
      try {
        if (isFree) {
          await api.post(`/courses/${course.slug}/enroll`)
          // Reflect the new state locally so the CTA updates without a
          // refetch. Only touch currentCourse when it IS this course — the
          // catalog grid can call enroll() for a card that is not the one
          // currently open in the detail view.
          if (this.currentCourse?.id === course.id) {
            this.currentCourse = { ...this.currentCourse, is_enrolled: true }
          }
          return 'enrolled'
        }

        await startCheckoutHandoff({ type: 'course', course_id: course.id })
        this.enrollHandoffCourseId = course.id
        return 'handoff'
      } catch (err) {
        if (err.response?.status === 401) {
          // The global api.js interceptor also redirects to /login on any 401;
          // this is the fallback copy for when that redirect itself fails.
          this.enrollError = 'Debes iniciar sesión para inscribirte.'
        } else if (err.response?.status === 409) {
          this.enrollError = 'Ya estás inscrito en este curso.'
        } else {
          this.enrollError =
            err.response?.data?.message || 'No se pudo procesar la inscripción. Inténtalo de nuevo.'
        }
        return null
      } finally {
        this.enrolling = false
      }
    },

    /**
     * Open a live session's room in the system browser.
     *
     * The catalog payload deliberately carries only each session's schedule,
     * never its meeting_url — the link is window-gated server-side, so it has
     * to be asked for at the moment the student taps, not cached with the
     * course. LessonResource returns it filled only inside the window and null
     * outside, which is why "closed" is a normal answer here rather than an
     * error.
     *
     * @returns {Promise<'opened'|'closed'|null>} null on failure
     */
    async joinSession(lessonId) {
      this.sessionError = null

      if (lessonId == null) {
        this.sessionError = 'No se pudo abrir la sesión.'
        return null
      }

      this.joiningLessonId = lessonId
      try {
        const { data } = await api.get(`/lessons/${lessonId}`)
        const lesson = data.data ?? data

        if (await openMeeting(lesson.meeting_url)) return 'opened'

        this.sessionError =
          'El enlace se habilita 15 minutos antes de la sesión y se cierra al terminar.'
        return 'closed'
      } catch (err) {
        this.sessionError =
          err.response?.status === 403
            ? 'Debes estar inscrito para entrar a esta sesión.'
            : err.response?.data?.message || 'No se pudo abrir la sesión. Inténtalo de nuevo.'
        return null
      } finally {
        this.joiningLessonId = null
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
