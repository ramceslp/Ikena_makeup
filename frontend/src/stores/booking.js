import { defineStore } from 'pinia'
import api from '../services/api.js'

export const useBookingStore = defineStore('booking', {
  state: () => ({
    availableSlots: [],
    isLoading: false,
    bookingError: null,
    lastBookingResult: null,
    // Admin state
    appointments: [],
    appointmentsMeta: null,
    slots: [],
  }),

  actions: {
    // ── Public: fetch available slots for a service ─────────────────────────

    async fetchAvailableSlots(serviceId) {
      this.isLoading = true
      this.bookingError = null
      try {
        const response = await api.get(`/services/${serviceId}/available-slots`)
        this.availableSlots = response.data.data
      } catch (err) {
        this.bookingError = err.response?.data?.message || 'Error al cargar los horarios disponibles'
      } finally {
        this.isLoading = false
      }
    },

    // ── Public: create a booking ────────────────────────────────────────────

    async createBooking(payload) {
      this.isLoading = true
      this.bookingError = null
      try {
        const response = await api.post('/bookings', payload)
        this.lastBookingResult = response.data
        return response.data
      } catch (err) {
        const status = err.response?.status
        if (status === 409) {
          // Re-fetch slots so the taken slot disappears from the picker,
          // then set the error (fetchAvailableSlots clears bookingError internally)
          await this.fetchAvailableSlots(payload.service_id)
          this.bookingError = 'Este horario ya no está disponible. Por favor elige otro.'
        } else if (status === 401) {
          this.bookingError = 'Debes iniciar sesión para realizar una reserva.'
        } else {
          this.bookingError =
            err.response?.data?.message || 'Error al procesar la reserva. Inténtalo de nuevo.'
        }
        this.lastBookingResult = null
        return null
      } finally {
        this.isLoading = false
      }
    },

    // ── Admin: fetch appointments list ──────────────────────────────────────

    async fetchAppointments(filters = {}) {
      this.isLoading = true
      this.bookingError = null
      try {
        const response = await api.get('/admin/appointments', { params: filters })
        this.appointments = response.data.data
        this.appointmentsMeta = response.data.meta
      } catch (err) {
        this.bookingError =
          err.response?.data?.message || 'Error al cargar las citas'
      } finally {
        this.isLoading = false
      }
    },

    // ── Admin: mark appointment as paid (manual) ────────────────────────────

    async markAppointmentPaid(id) {
      const response = await api.patch(`/admin/appointments/${id}/mark-paid`)
      return response.data.data
    },

    // ── Admin: cancel an appointment ────────────────────────────────────────

    async cancelAppointment(id) {
      const response = await api.patch(`/admin/appointments/${id}/cancel`)
      return response.data.data
    },

    // ── Admin: slot CRUD ────────────────────────────────────────────────────

    async fetchSlots(serviceId) {
      const response = await api.get(`/admin/services/${serviceId}/slots`)
      this.slots = response.data.data
    },

    async createSlot(serviceId, data) {
      const response = await api.post(`/admin/services/${serviceId}/slots`, data)
      return response.data.data
    },

    async updateSlot(serviceId, slotId, data) {
      const response = await api.patch(`/admin/services/${serviceId}/slots/${slotId}`, data)
      return response.data.data
    },

    async deleteSlot(serviceId, slotId) {
      await api.delete(`/admin/services/${serviceId}/slots/${slotId}`)
    },
  },
})
