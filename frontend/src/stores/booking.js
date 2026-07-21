import { defineStore } from 'pinia'
import api from '../services/api.js'

export const useBookingStore = defineStore('booking', {
  state: () => ({
    // Calendar day summaries for the booking window ([{ date, available_count }]).
    // Days with no agenda block are simply absent from this array.
    availableDays: [],
    isDaysLoading: false,
    daysError: null,
    // Slots for the currently viewed day only (day-scoped picker step 2).
    daySlots: [],
    isDaySlotsLoading: false,
    daySlotsError: null,
    isLoading: false,
    bookingError: null,
    lastBookingResult: null,
    // Incremented every time createBooking fails with a 409 (slot cap
    // exceeded between selection and submission). Components watch this to
    // clear their own local "selected slot" state, which is otherwise left
    // pointing at a slot that no longer exists.
    slotConflictVersion: 0,
    // Admin state
    appointments: [],
    appointmentsMeta: null,
    agendaBlocks: [],
    // Request-sequencing tokens (not for template use): incremented on every
    // new fetchAvailableDays/fetchDaySlots call so a stale response that
    // resolves after a newer request has since started can be detected and
    // ignored, instead of silently overwriting fresher data (ordinary
    // network jitter — no cancellation needed, just a "latest wins" guard).
    _availableDaysRequestId: 0,
    _daySlotsRequestId: 0,
  }),

  actions: {
    // ── Public: fetch the day-summary calendar for a service ────────────────

    async fetchAvailableDays(serviceId) {
      const requestId = ++this._availableDaysRequestId
      this.isDaysLoading = true
      this.daysError = null
      try {
        const response = await api.get(`/services/${serviceId}/available-days`)
        if (requestId !== this._availableDaysRequestId) return // superseded by a newer request
        this.availableDays = response.data.data
      } catch (err) {
        if (requestId !== this._availableDaysRequestId) return
        this.daysError = err.response?.data?.message || 'Error al cargar los días disponibles'
      } finally {
        if (requestId === this._availableDaysRequestId) {
          this.isDaysLoading = false
        }
      }
    },

    // ── Public: fetch slots for a single day ────────────────────────────────

    async fetchDaySlots(serviceId, date) {
      const requestId = ++this._daySlotsRequestId
      this.isDaySlotsLoading = true
      this.daySlotsError = null
      try {
        const response = await api.get(`/services/${serviceId}/available-slots`, {
          params: { date },
        })
        if (requestId !== this._daySlotsRequestId) return // superseded by a newer request
        this.daySlots = response.data.data
      } catch (err) {
        if (requestId !== this._daySlotsRequestId) return
        this.daySlotsError = err.response?.data?.message || 'Error al cargar los horarios de ese día'
      } finally {
        if (requestId === this._daySlotsRequestId) {
          this.isDaySlotsLoading = false
        }
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
          // The calendar is locked to the submitted day for the entire
          // duration of a submit (see BookingCalendar's `locked` prop /
          // SlotPicker's `isSubmitting`) — the user cannot have switched
          // days in the meantime, so the day on screen when a 409 lands is
          // always `payload.scheduled_date`. No separate "currently viewed
          // day" needs tracking.
          await this.fetchDaySlots(payload.service_id, payload.scheduled_date)
          await this.fetchAvailableDays(payload.service_id)
          this.bookingError = 'Este horario ya no está disponible. Por favor elige otro.'
          this.slotConflictVersion += 1
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

    // ── Admin: venue agenda block CRUD (VAGA-001) ───────────────────────────

    async fetchAgendaBlocks() {
      this.isLoading = true
      this.bookingError = null
      try {
        const response = await api.get('/admin/agenda')
        this.agendaBlocks = response.data.data
      } catch (err) {
        this.bookingError = err.response?.data?.message || 'Error al cargar la agenda del local'
      } finally {
        this.isLoading = false
      }
    },

    // Create/update/delete intentionally let validation errors (e.g. 422
    // overlap/XOR) propagate to the caller — the admin form needs the raw
    // error response to render field-level messages, unlike the public
    // booking flow which translates errors into user-facing copy itself.

    async createAgendaBlock(data) {
      const response = await api.post('/admin/agenda', data)
      return response.data.data
    },

    async updateAgendaBlock(blockId, data) {
      const response = await api.patch(`/admin/agenda/${blockId}`, data)
      return response.data.data
    },

    async deleteAgendaBlock(blockId) {
      await api.delete(`/admin/agenda/${blockId}`)
    },
  },
})
