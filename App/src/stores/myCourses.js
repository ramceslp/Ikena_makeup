import { defineStore } from 'pinia'
import { Browser } from '@capacitor/browser'
import api from '../services/api.js'
import { safeCtaUrl } from '../utils/cta.js'

// The user's enrolled courses (GET /my-courses), for the profile screen.
//
// Its own store rather than a slice of courses.js, per this codebase's
// one-concern-per-store convention (products.js / services.js / booking.js /
// cart.js / push.js / profile.js all follow it): courses.js serves the public
// catalog and is populated on Home and the catalog screens, while this is
// account data that only exists for an authenticated user.
export const useMyCoursesStore = defineStore('myCourses', {
  state: () => ({
    courses: [],
    loading: false,
    error: null,
    // Set when a course's web_url is missing or fails the safeCtaUrl protocol
    // check. Without a UI consumer this would be another instance of this
    // codebase's "state set but never rendered" bug class — MyCoursesSection
    // renders it.
    openError: null,
  }),

  actions: {
    async fetchMyCourses() {
      this.loading = true
      this.error = null
      try {
        const response = await api.get('/my-courses')
        // GET /my-courses returns a plain collection, not a paginator — there
        // is no `meta` to record here (unlike profile.js's fetchOrders()).
        this.courses = response.data.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar tus cursos'
      } finally {
        this.loading = false
      }
    },

    /**
     * Open a course's lessons.
     *
     * This app has no /learn player, so the lessons live on the web. The URL
     * comes from the API (MyCourseResource's `web_url`, built from
     * config('app.frontend_url')) rather than being assembled here: the app
     * only knows VITE_API_URL, and the web origin is a different host in
     * production and a different port in development.
     *
     * Validated with the same safeCtaUrl guard the app already applies to
     * every externally-supplied link, so a malformed or javascript:/data:
     * value can never reach Browser.open().
     *
     * @returns {Promise<boolean>} true when the browser was opened
     */
    async openCourse(course) {
      this.openError = null

      const url = safeCtaUrl(course?.web_url)
      if (!url) {
        this.openError = 'No pudimos abrir este curso. Inténtalo desde la web.'
        return false
      }

      try {
        await Browser.open({ url })
        return true
      } catch (err) {
        console.error('Failed to open the course player:', err)
        this.openError = 'No pudimos abrir tu navegador. Inténtalo de nuevo.'
        return false
      }
    },
  },
})
