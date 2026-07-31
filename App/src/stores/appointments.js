import { defineStore } from 'pinia'
import api from '../services/api.js'

// The user's own agenda (GET /profile/appointments?scope=upcoming|past).
//
// Upcoming and past are two separate server-side scopes with OPPOSITE sort
// orders (nearest first vs. most recent first) and independent pagination, so
// they are held as two independent slices here rather than one list filtered
// client-side. Filtering locally would also mean fetching a customer's entire
// booking history just to show the next appointment.
//
// Its own store, not a slice of profile.js, per the one-concern-per-store
// convention already followed across this codebase.
function emptyScope() {
  return { items: [], meta: null, loading: false, error: null }
}

export const useAppointmentsStore = defineStore('appointments', {
  state: () => ({
    upcoming: emptyScope(),
    past: emptyScope(),
  }),

  getters: {
    // The next appointment, or null. Relies on the server's ascending sort for
    // the upcoming scope — re-sorting here would risk silently disagreeing
    // with it on a tie-break rule (same reasoning as profile.js's fetchOrders).
    nextAppointment: (state) => state.upcoming.items[0] ?? null,
  },

  actions: {
    /**
     * @param {'upcoming'|'past'} scope
     */
    async fetchAppointments(scope = 'upcoming', page = 1) {
      const slice = this[scope]
      if (!slice) return

      slice.loading = true
      slice.error = null
      try {
        const response = await api.get('/profile/appointments', {
          params: { scope, page },
        })
        slice.items = response.data.data
        slice.meta = response.data.meta
      } catch (err) {
        slice.error = err.response?.data?.message || 'Error al cargar tu agenda'
      } finally {
        slice.loading = false
      }
    },

    /**
     * Load both scopes. Deliberately Promise.allSettled, not Promise.all: the
     * two requests are independent, and one failing must not blank out the
     * other. Each slice already carries its own error field for that reason.
     */
    async fetchAll() {
      await Promise.allSettled([
        this.fetchAppointments('upcoming'),
        this.fetchAppointments('past'),
      ])
    },
  },
})
