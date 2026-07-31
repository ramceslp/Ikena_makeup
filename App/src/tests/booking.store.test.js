import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

vi.mock('@capacitor/browser', () => ({
  Browser: { open: vi.fn().mockResolvedValue(undefined) },
}))

import api from '../services/api.js'
import { Browser } from '@capacitor/browser'
import { useBookingStore } from '../stores/booking.js'

// Trimmed port of frontend/src/stores/booking.js: only the PUBLIC booking
// flow (fetchAvailableDays, fetchDaySlots, payDeposit), needed by
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

  // ── payDeposit() — the app's real booking action ───────────────────────────
  // It snapshots the selection; the appointment is created server-side at
  // redeem, in the browser. The previous flow created a `pending` appointment
  // from the app and then had no way to collect the deposit at all, so the
  // agenda filled up with unpaid holds.

  describe('payDeposit', () => {
    const payload = {
      service_id: 5,
      scheduled_date: '2026-08-01',
      scheduled_time: '10:00',
      whatsapp: '+593999999999',
    }

    it('snapshots the selection and opens the browser, without creating a booking', async () => {
      api.post.mockResolvedValueOnce({
        data: { data: { url: 'https://app.ikena.com/checkout/resume#token=abc' } },
      })

      const store = useBookingStore()
      const opened = await store.payDeposit(payload)

      expect(opened).toBe(true)
      expect(api.post).toHaveBeenCalledWith('/checkout/handoff', {
        type: 'appointment',
        ...payload,
      })
      expect(api.post).not.toHaveBeenCalledWith('/bookings', expect.anything())
      expect(Browser.open).toHaveBeenCalledWith({
        url: 'https://app.ikena.com/checkout/resume#token=abc',
      })
      expect(store.handoffOpened).toBe(true)
      expect(store.bookingError).toBeNull()
      expect(store.isLoading).toBe(false)
    })

    it('reports failure and leaves handoffOpened false when the handoff call fails', async () => {
      const failure = new Error('Unprocessable')
      failure.response = { status: 422, data: { message: 'Este servicio no acepta citas.' } }
      api.post.mockRejectedValueOnce(failure)

      const store = useBookingStore()
      const opened = await store.payDeposit(payload)

      expect(opened).toBe(false)
      expect(Browser.open).not.toHaveBeenCalled()
      expect(store.handoffOpened).toBe(false)
      expect(store.bookingError).toBe('Este servicio no acepta citas.')
      expect(store.isLoading).toBe(false)
    })

    it('uses a sign-in message on 401, matching the global interceptor fallback', async () => {
      const unauthorized = new Error('Unauthorized')
      unauthorized.response = { status: 401, data: {} }
      api.post.mockRejectedValueOnce(unauthorized)

      const store = useBookingStore()

      expect(await store.payDeposit(payload)).toBe(false)
      expect(store.bookingError).toBe('Debes iniciar sesión para reservar.')
    })

    it('falls back to a generic Spanish message when the failure carries no body', async () => {
      api.post.mockRejectedValueOnce(new Error('Network Error'))

      const store = useBookingStore()

      expect(await store.payDeposit(payload)).toBe(false)
      expect(store.bookingError).toBe('No se pudo iniciar el pago del depósito. Inténtalo de nuevo.')
    })

    it('never opens the browser when the handoff response has no usable url', async () => {
      api.post.mockResolvedValueOnce({ data: { data: { expires_at: '2026-08-01T00:00:00Z' } } })

      const store = useBookingStore()

      expect(await store.payDeposit(payload)).toBe(false)
      expect(Browser.open).not.toHaveBeenCalled()
      expect(store.bookingError).toBe('No se pudo iniciar el pago del depósito. Inténtalo de nuevo.')
    })

    it('clears a previous error at the start of the next attempt', async () => {
      const store = useBookingStore()
      api.post.mockRejectedValueOnce(new Error('Network Error'))
      await store.payDeposit(payload)
      expect(store.bookingError).not.toBeNull()

      api.post.mockResolvedValueOnce({
        data: { data: { url: 'https://app.ikena.com/checkout/resume#token=abc' } },
      })
      await store.payDeposit(payload)

      expect(store.bookingError).toBeNull()
    })
  })
})
