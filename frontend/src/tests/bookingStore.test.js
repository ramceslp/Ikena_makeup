import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
    patch: vi.fn(),
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
}))

import api from '../services/api.js'
import { useBookingStore } from '../stores/booking.js'

// ---------------------------------------------------------------------------
// fetchAvailableSlots
// ---------------------------------------------------------------------------

describe('booking store — fetchAvailableSlots', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchAvailableSlots populates availableSlots from API response', async () => {
    const fakeSlots = [
      { id: 1, date_label: '2026-07-04', start_time: '10:00', capacity_remaining: 1 },
      { id: 2, date_label: '2026-07-07', start_time: '14:00', capacity_remaining: 1 },
      { id: 3, date_label: '2026-07-11', start_time: '10:00', capacity_remaining: 1 },
    ]
    api.get.mockResolvedValueOnce({ data: { data: fakeSlots } })

    const store = useBookingStore()
    await store.fetchAvailableSlots(1)

    expect(api.get).toHaveBeenCalledWith('/services/1/available-slots')
    expect(store.availableSlots).toEqual(fakeSlots)
    expect(store.isLoading).toBe(false)
    expect(store.bookingError).toBeNull()
  })

  it('fetchAvailableSlots sets isLoading true during fetch then false after', async () => {
    let loadingDuringCall = false
    api.get.mockImplementationOnce(async () => {
      loadingDuringCall = true
      return { data: { data: [] } }
    })

    const store = useBookingStore()
    await store.fetchAvailableSlots(1)

    expect(loadingDuringCall).toBe(true)
    expect(store.isLoading).toBe(false)
  })

  it('fetchAvailableSlots sets bookingError on API failure', async () => {
    api.get.mockRejectedValueOnce({
      response: { data: { message: 'Servicio no encontrado' } },
    })

    const store = useBookingStore()
    await store.fetchAvailableSlots(99)

    expect(store.availableSlots).toEqual([])
    expect(store.bookingError).toBe('Servicio no encontrado')
    expect(store.isLoading).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// createBooking
// ---------------------------------------------------------------------------

describe('booking store — createBooking', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('createBooking on 201 sets lastBookingResult and clears bookingError', async () => {
    const fakeResult = {
      appointment: { id: 10, status: 'pending' },
      gateway_payload: { checkout_url: 'https://pay.example.com/abc' },
    }
    api.post.mockResolvedValueOnce({ status: 201, data: fakeResult })

    const store = useBookingStore()
    const result = await store.createBooking({
      service_id: 1,
      scheduled_date: '2026-07-04',
      scheduled_time: '10:00',
      whatsapp: '+5930999',
    })

    expect(api.post).toHaveBeenCalledWith('/bookings', {
      service_id: 1,
      scheduled_date: '2026-07-04',
      scheduled_time: '10:00',
      whatsapp: '+5930999',
    })
    expect(store.lastBookingResult).toEqual(fakeResult)
    expect(store.bookingError).toBeNull()
    expect(result).toEqual(fakeResult)
  })

  it('createBooking on 409 sets bookingError and keeps lastBookingResult null', async () => {
    const error = { response: { status: 409, data: { message: 'Slot already taken' } } }
    api.post.mockRejectedValueOnce(error)
    // fetchAvailableSlots re-fetch after 409
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const store = useBookingStore()
    const result = await store.createBooking({
      service_id: 1,
      scheduled_date: '2026-07-04',
      scheduled_time: '10:00',
      whatsapp: '+5930999',
    })

    expect(store.bookingError).toBe('Este horario ya no está disponible. Por favor elige otro.')
    expect(store.lastBookingResult).toBeNull()
    expect(result).toBeNull()
  })

  it('createBooking on 409 re-fetches available slots for the service', async () => {
    const error = { response: { status: 409, data: {} } }
    api.post.mockRejectedValueOnce(error)
    // Mock the re-fetch
    const freshSlots = [{ id: 5, date_label: '2026-07-10', start_time: '11:00', capacity_remaining: 1 }]
    api.get.mockResolvedValueOnce({ data: { data: freshSlots } })

    const store = useBookingStore()
    await store.createBooking({
      service_id: 7,
      scheduled_date: '2026-07-04',
      scheduled_time: '10:00',
      whatsapp: '+5930999',
    })

    // Must have called GET /services/7/available-slots after the 409
    expect(api.get).toHaveBeenCalledWith('/services/7/available-slots')
    // The fresh slots replace the stale list
    expect(store.availableSlots).toEqual(freshSlots)
  })

  it('createBooking on 422 sets bookingError with server message', async () => {
    const error = {
      response: {
        status: 422,
        data: { message: 'El servicio no acepta reservas.' },
      },
    }
    api.post.mockRejectedValueOnce(error)

    const store = useBookingStore()
    await store.createBooking({ service_id: 1, scheduled_date: '2026-07-04', scheduled_time: '10:00', whatsapp: '+5930999' })

    expect(store.bookingError).toBe('El servicio no acepta reservas.')
    expect(store.lastBookingResult).toBeNull()
  })

  it('createBooking on 401 sets bookingError', async () => {
    const error = { response: { status: 401, data: {} } }
    api.post.mockRejectedValueOnce(error)

    const store = useBookingStore()
    await store.createBooking({ service_id: 1, scheduled_date: '2026-07-04', scheduled_time: '10:00', whatsapp: '+5930999' })

    expect(store.bookingError).toBeTruthy()
    expect(store.lastBookingResult).toBeNull()
  })
})

// ---------------------------------------------------------------------------
// Admin — fetchAppointments
// ---------------------------------------------------------------------------

describe('booking store — fetchAppointments (admin)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchAppointments GETs /admin/appointments and populates appointments', async () => {
    const fakeAppointments = [
      { id: 1, status: 'pending', service: { title: 'Maquillaje Social' }, user: { name: 'Ana' } },
    ]
    api.get.mockResolvedValueOnce({ data: { data: fakeAppointments, meta: {} } })

    const store = useBookingStore()
    await store.fetchAppointments()

    expect(api.get).toHaveBeenCalledWith('/admin/appointments', { params: {} })
    expect(store.appointments).toEqual(fakeAppointments)
  })

  it('fetchAppointments passes filters as query params', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = useBookingStore()
    await store.fetchAppointments({ status: 'paid', service_id: 2 })

    expect(api.get).toHaveBeenCalledWith('/admin/appointments', {
      params: { status: 'paid', service_id: 2 },
    })
  })
})

// ---------------------------------------------------------------------------
// Admin — markAppointmentPaid
// ---------------------------------------------------------------------------

describe('booking store — markAppointmentPaid (admin)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('markAppointmentPaid PATCHes /admin/appointments/{id}/mark-paid', async () => {
    const updated = { id: 5, status: 'paid' }
    api.patch.mockResolvedValueOnce({ data: { data: updated } })

    const store = useBookingStore()
    const result = await store.markAppointmentPaid(5)

    expect(api.patch).toHaveBeenCalledWith('/admin/appointments/5/mark-paid')
    expect(result).toEqual(updated)
  })
})

// ---------------------------------------------------------------------------
// Admin — cancelAppointment
// ---------------------------------------------------------------------------

describe('booking store — cancelAppointment (admin)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('cancelAppointment PATCHes /admin/appointments/{id}/cancel', async () => {
    const cancelled = { id: 3, status: 'cancelled' }
    api.patch.mockResolvedValueOnce({ data: { data: cancelled } })

    const store = useBookingStore()
    const result = await store.cancelAppointment(3)

    expect(api.patch).toHaveBeenCalledWith('/admin/appointments/3/cancel')
    expect(result).toEqual(cancelled)
  })
})

// ---------------------------------------------------------------------------
// Admin — fetchSlots / createSlot / updateSlot / deleteSlot
// ---------------------------------------------------------------------------

describe('booking store — slot admin actions', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchSlots GETs /admin/services/{serviceId}/slots', async () => {
    const fakeSlots = [{ id: 1, start_time: '10:00', day_of_week: 1 }]
    api.get.mockResolvedValueOnce({ data: { data: fakeSlots } })

    const store = useBookingStore()
    await store.fetchSlots(2)

    expect(api.get).toHaveBeenCalledWith('/admin/services/2/slots')
    expect(store.slots).toEqual(fakeSlots)
  })

  it('createSlot POSTs to /admin/services/{serviceId}/slots', async () => {
    const newSlot = { id: 10, start_time: '14:00', day_of_week: 3 }
    api.post.mockResolvedValueOnce({ data: { data: newSlot } })

    const store = useBookingStore()
    const result = await store.createSlot(2, { start_time: '14:00', day_of_week: 3 })

    expect(api.post).toHaveBeenCalledWith('/admin/services/2/slots', { start_time: '14:00', day_of_week: 3 })
    expect(result).toEqual(newSlot)
  })

  it('updateSlot PATCHes /admin/services/{serviceId}/slots/{slotId}', async () => {
    const updated = { id: 10, is_blocked: true }
    api.patch.mockResolvedValueOnce({ data: { data: updated } })

    const store = useBookingStore()
    const result = await store.updateSlot(2, 10, { is_blocked: true })

    expect(api.patch).toHaveBeenCalledWith('/admin/services/2/slots/10', { is_blocked: true })
    expect(result).toEqual(updated)
  })

  it('deleteSlot DELETEs /admin/services/{serviceId}/slots/{slotId}', async () => {
    api.delete.mockResolvedValueOnce({})

    const store = useBookingStore()
    await store.deleteSlot(2, 10)

    expect(api.delete).toHaveBeenCalledWith('/admin/services/2/slots/10')
  })
})
