import { defineStore } from 'pinia'
import api from '../services/api.js'

// Profile/history (mobile-capacitor-setup Phase 8, tasks 8.9-8.10). Calls
// the SAME GET /api/profile/orders endpoint frontend/src/stores/auth.js's
// fetchOrders() already uses (ProfileController::orders() — always
// `->latest()->paginate(15)`, i.e. reverse-chronological by created_at, done
// server-side). Kept in its own store rather than folded into auth.js, per
// this codebase's established one-concern-per-store convention (see
// products.js/services.js/booking.js/cart.js/push.js).
export const useProfileStore = defineStore('profile', {
  state: () => ({
    orders: [],
    ordersMeta: null,
    loading: false,
    error: null,
  }),

  actions: {
    /**
     * [Spec: history loads / no history yet]. Does NOT sort the response —
     * the backend's `->latest()` call is the single source of truth for
     * ordering (verified against ProfileController::orders()); re-sorting
     * here would risk silently disagreeing with it on a tie-break rule.
     */
    async fetchOrders(page = 1) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get('/profile/orders', { params: { page } })
        this.orders = response.data.data
        this.ordersMeta = response.data.meta
      } catch {
        this.error = 'Error al cargar tu historial'
      } finally {
        this.loading = false
      }
    },
  },
})
