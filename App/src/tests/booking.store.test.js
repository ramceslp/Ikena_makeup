import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

import api from '../services/api.js'
import { useBookingStore } from '../stores/booking.js'

// Trimmed port of frontend/src/stores/booking.js: only the PUBLIC booking
// flow (fetchAvailableDays, fetchDaySlots, createBooking), needed by
// SlotPicker.vue/BookingForm.vue/ServiceDetail.vue. Admin actions
// (fetchAppointments, markAppointmentPaid, cancelAppointment, agenda CRUD)
// are intentionally NOT ported -- no admin surface in this app (see the
// spec's Mobile App Boundaries: "admin route unreachable from app").
describe('booking store (Phase 7 — public booking flow only)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  describe('fetchAvailableDays', () => {
    it('GETs /services/:id/available-days and populates availableDays', async () => {
      const fakeDays = [{ date: '2026-08-01', available_count: 3 }]
      api.get.mockResolvedValueOnce({ data: { data: fakeDays } })

      const store = useBookingStore()
      await store.fetchAvailableDays(5)

      expect(api.get).toHaveBeenCalledWith('/services/5/available-days')
      expect(store.availableDays).toEqual(fakeDays)
    })

    it('sets a Spanish error message when the request fails', async () => {
      api.get.mockRejectedValueOnce(new Error('Network Error'))

      const store = useBookingStore()
      await store.fetchAvailableDays(5)

      expect(store.daysError).toBe('Error al cargar los días disponibles')
    })
  })

  describe('fetchDaySlots', () => {
    it('GETs /services/:id/available-slots with the date param and populates daySlots', async () => {
      const fakeSlots = [{ id: 1, start_time: '10:00', capacity_remaining: 2 }]
      api.get.mockResolvedValueOnce({ data: { data: fakeSlots } })

      const store = useBookingStore()
      await store.fetchDaySlots(5, '2026-08-01')

      expect(api.get).toHaveBeenCalledWith('/services/5/available-slots', {
        params: { date: '2026-08-01' },
      })
      expect(store.daySlots).toEqual(fakeSlots)
    })
  })

  describe('createBooking', () => {
    const payload = {
      service_id: 5,
      scheduled_date: '2026-08-01',
      scheduled_time: '10:00',
      whatsapp: '+593999999999',
    }

    it('POSTs /bookings and returns the response data on success [Spec: booking succeeds]', async () => {
      const fakeResult = { data: { id: 42, status: 'pending_payment' } }
      api.post.mockResolvedValueOnce({ data: fakeResult })

      const store = useBookingStore()
      const result = await store.createBooking(payload)

      expect(api.post).toHaveBeenCalledWith('/bookings', payload)
      expect(result).toEqual(fakeResult)
      expect(store.lastBookingResult).toEqual(fakeResult)
      expect(store.bookingError).toBeNull()
    })

    it('on 409 (slot claimed by someone else), refreshes slots/days, sets a clear message, and returns null [Spec: slot goes stale before confirmation]', async () => {
      const conflict = new Error('Conflict')
      conflict.response = { status: 409 }
      api.post.mockRejectedValueOnce(conflict)
      api.get.mockResolvedValue({ data: { data: [] } })

      const store = useBookingStore()
      const result = await store.createBooking(payload)

      expect(result).toBeNull()
      expect(store.bookingError).toBe('Este horario ya no está disponible. Por favor elige otro.')
      // Refreshed slot options — not a silent failure.
      expect(api.get).toHaveBeenCalledWith('/services/5/available-slots', {
        params: { date: '2026-08-01' },
      })
      expect(api.get).toHaveBeenCalledWith('/services/5/available-days')
      expect(store.slotConflictVersion).toBe(1)
      expect(store.lastBookingResult).toBeNull()
    })

    it('on 401, sets a login-required message', async () => {
      const unauthorized = new Error('Unauthorized')
      unauthorized.response = { status: 401 }
      api.post.mockRejectedValueOnce(unauthorized)

      const store = useBookingStore()
      const result = await store.createBooking(payload)

      expect(result).toBeNull()
      expect(store.bookingError).toBe('Debes iniciar sesión para realizar una reserva.')
    })

    it('on other failures, uses the server message or a generic fallback', async () => {
      const serverError = new Error('Server Error')
      serverError.response = { status: 500, data: { message: 'Algo salió mal' } }
      api.post.mockRejectedValueOnce(serverError)

      const store = useBookingStore()
      const result = await store.createBooking(payload)

      expect(result).toBeNull()
      expect(store.bookingError).toBe('Algo salió mal')
    })
  })
})
