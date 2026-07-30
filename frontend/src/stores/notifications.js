import { defineStore } from 'pinia'
import api from '../services/api.js'

/**
 * Admin push-notification centre (push-notifications Slice 4).
 *
 * Backs /admin/notificaciones: the unified send history (automatic triggers
 * AND custom admin sends) plus the compose form.
 *
 * Separate `sending` / `loading` flags rather than one shared flag: composing
 * a broadcast must not blank out the history table underneath the form while
 * the POST is in flight.
 */
export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    logs: [],
    meta: null,
    stats: null,
    loading: false,
    sending: false,
    error: null,
    sendError: null,
  }),

  actions: {
    async fetchLogs({ type = '', page = 1 } = {}) {
      this.loading = true
      this.error = null
      try {
        // Build params locally — never mutate shared state to hold filters,
        // which is how a later unfiltered call silently inherits an earlier
        // filter (see the same note in stores/posts.js).
        const params = { page }
        if (type) params.type = type

        const response = await api.get('/admin/push-notifications', { params })
        this.logs = response.data.data
        this.meta = response.data.meta
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar el historial de notificaciones'
      } finally {
        this.loading = false
      }
    },

    /**
     * Reachable device count + whether push is actually enabled on the server.
     * Deliberately silent on failure: this only powers an informational line
     * next to the form, and an error banner for it would be noise on a screen
     * whose real job is the history below.
     */
    async fetchStats() {
      try {
        const response = await api.get('/admin/push-notifications/stats')
        this.stats = response.data.data
      } catch {
        this.stats = null
      }
    },

    /**
     * Sends a custom broadcast. Resolves with the created history row.
     *
     * That row reports the QUEUED state, not a delivery outcome — the backend
     * fills in success/failure counts once FCM answers. A row that comes back
     * with status 'skipped' means Firebase is not configured on the server;
     * the view surfaces that distinctly rather than claiming a successful send.
     */
    async send({ title, body, route }) {
      this.sending = true
      this.sendError = null
      try {
        const payload = { title, body }
        if (route) payload.route = route

        const response = await api.post('/admin/push-notifications', payload)
        return response.data.data
      } catch (err) {
        // Surface the first field-level validation message when there is one —
        // "The route must be an internal path..." is far more actionable than
        // a generic failure notice.
        const errors = err.response?.data?.errors
        const firstFieldError = errors ? Object.values(errors)[0]?.[0] : null

        this.sendError =
          firstFieldError || err.response?.data?.message || 'Error al enviar la notificación'
        throw err
      } finally {
        this.sending = false
      }
    },
  },
})
