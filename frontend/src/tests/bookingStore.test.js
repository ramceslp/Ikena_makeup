import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
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
// fetchAvailableDays
// ---------------------------------------------------------------------------

describe('booking store — fetchAvailableDays', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchAvailableDays populates availableDays from API response', async () => {
    const fakeDays = [
      { date: '2026-07-21', available_count: 5 },
      { date: '2026-07-22', available_count: 0 },
    ]
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })

    const store = useBookingStore()
    await store.fetchAvailableDays(1)

    expect(api.get).toHaveBeenCalledWith('/services/1/available-days')
    expect(store.availableDays).toEqual(fakeDays)
    expect(store.isDaysLoading).toBe(false)
    expect(store.daysError).toBeNull()
  })

  it('fetchAvailableDays sets isDaysLoading true during fetch then false after', async () => {
    let loadingDuringCall = false
    api.get.mockImplementationOnce(async () => {
      loadingDuringCall = true
      return { data: { data: [] } }
    })

    const store = useBookingStore()
    await store.fetchAvailableDays(1)

    expect(loadingDuringCall).toBe(true)
    expect(store.isDaysLoading).toBe(false)
  })

  it('fetchAvailableDays sets daysError on API failure', async () => {
    api.get.mockRejectedValueOnce({
      response: { data: { message: 'Servicio no encontrado' } },
    })

    const store = useBookingStore()
    await store.fetchAvailableDays(99)

    expect(store.availableDays).toEqual([])
    expect(store.daysError).toBe('Servicio no encontrado')
    expect(store.isDaysLoading).toBe(false)
  })

  it('fetchAvailableDays ignores a stale response that resolves after a newer request has started', async () => {
    let resolveFirst
    api.get.mockImplementationOnce(() => new Promise((resolve) => { resolveFirst = resolve }))
    const freshDays = [{ date: '2026-08-01', available_count: 4 }]
    api.get.mockResolvedValueOnce({ data: { data: freshDays } })

    const store = useBookingStore()
    const firstCall = store.fetchAvailableDays(1) // in-flight, will resolve LAST
    const secondCall = store.fetchAvailableDays(1) // fired before the first settles
    await secondCall

    // Resolve the stale first request only after the second has already landed.
    const staleDays = [{ date: '2026-06-01', available_count: 9 }]
    resolveFirst({ data: { data: staleDays } })
    await firstCall

    expect(store.availableDays).toEqual(freshDays)
  })
})

// ---------------------------------------------------------------------------
// fetchDaySlots
// ---------------------------------------------------------------------------

describe('booking store — fetchDaySlots', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchDaySlots GETs available-slots with a date param and populates daySlots', async () => {
    const fakeSlots = [
      { id: 1, date_label: '2026-07-21', start_time: '10:00', capacity_remaining: 1 },
    ]
    api.get.mockResolvedValueOnce({ data: { data: fakeSlots } })

    const store = useBookingStore()
    await store.fetchDaySlots(1, '2026-07-21')

    expect(api.get).toHaveBeenCalledWith('/services/1/available-slots', {
      params: { date: '2026-07-21' },
    })
    expect(store.daySlots).toEqual(fakeSlots)
    expect(store.isDaySlotsLoading).toBe(false)
    expect(store.daySlotsError).toBeNull()
  })

  it('fetchDaySlots sets isDaySlotsLoading true during fetch then false after', async () => {
    let loadingDuringCall = false
    api.get.mockImplementationOnce(async () => {
      loadingDuringCall = true
      return { data: { data: [] } }
    })

    const store = useBookingStore()
    await store.fetchDaySlots(1, '2026-07-21')

    expect(loadingDuringCall).toBe(true)
    expect(store.isDaySlotsLoading).toBe(false)
  })

  it('fetchDaySlots sets daySlotsError on API failure (e.g. 422 invalid date)', async () => {
    api.get.mockRejectedValueOnce({
      response: { data: { message: 'La fecha no es válida' } },
    })

    const store = useBookingStore()
    await store.fetchDaySlots(1, 'bad-date')

    expect(store.daySlots).toEqual([])
    expect(store.daySlotsError).toBe('La fecha no es válida')
    expect(store.isDaySlotsLoading).toBe(false)
  })

  it('fetchDaySlots ignores a stale response that resolves after a newer request has started (race)', async () => {
    // Simulates: user clicks day A, then day B before A's response lands.
    let resolveDayA
    api.get.mockImplementationOnce(() => new Promise((resolve) => { resolveDayA = resolve }))
    const dayBSlots = [{ id: 2, date_label: '2026-07-22', start_time: '09:00', capacity_remaining: 1 }]
    api.get.mockResolvedValueOnce({ data: { data: dayBSlots } })

    const store = useBookingStore()
    const callA = store.fetchDaySlots(1, '2026-07-21') // in-flight, will resolve LAST
    const callB = store.fetchDaySlots(1, '2026-07-22') // fired before A settles
    await callB

    // Day A's (stale) response lands after day B's — ordinary network jitter.
    const dayASlots = [{ id: 1, date_label: '2026-07-21', start_time: '10:00', capacity_remaining: 1 }]
    resolveDayA({ data: { data: dayASlots } })
    await callA

    // daySlots must still reflect the LATEST request (day B), not the stale
    // day A response that landed after it — otherwise the picker shows day
    // A's times under day B's heading and the user can submit a mismatched date.
    expect(store.daySlots).toEqual(dayBSlots)
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
    // re-fetch of the selected day's slots, then the day summary, after 409
    api.get.mockResolvedValueOnce({ data: { data: [] } })
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

  it('createBooking on 409 re-fetches the submitted day\'s slots and the day summary', async () => {
    const error = { response: { status: 409, data: {} } }
    api.post.mockRejectedValueOnce(error)
    // Mock the re-fetch: fetchDaySlots first, then fetchAvailableDays
    const freshSlots = [{ id: 5, date_label: '2026-07-10', start_time: '11:00', capacity_remaining: 1 }]
    const freshDays = [{ date: '2026-07-10', available_count: 0 }]
    api.get.mockResolvedValueOnce({ data: { data: freshSlots } })
    api.get.mockResolvedValueOnce({ data: { data: freshDays } })

    const store = useBookingStore()
    await store.createBooking({
      service_id: 7,
      scheduled_date: '2026-07-10',
      scheduled_time: '11:00',
      whatsapp: '+5930999',
    })

    // Must re-fetch the same day that was just attempted (a day can become
    // fully booked after this failure, so the summary is refreshed too).
    expect(api.get).toHaveBeenCalledWith('/services/7/available-slots', {
      params: { date: '2026-07-10' },
    })
    expect(api.get).toHaveBeenCalledWith('/services/7/available-days')
    expect(store.daySlots).toEqual(freshSlots)
    expect(store.availableDays).toEqual(freshDays)
  })

  it('createBooking on 409 increments slotConflictVersion so watchers can clear stale local selections', async () => {
    const error = { response: { status: 409, data: {} } }
    api.post.mockRejectedValueOnce(error)
    api.get.mockResolvedValueOnce({ data: { data: [] } })
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const store = useBookingStore()
    expect(store.slotConflictVersion).toBe(0)

    await store.createBooking({
      service_id: 1,
      scheduled_date: '2026-07-04',
      scheduled_time: '10:00',
      whatsapp: '+5930999',
    })

    expect(store.slotConflictVersion).toBe(1)
  })

  it('createBooking on 422/401 does NOT increment slotConflictVersion (only a real conflict clears the selection)', async () => {
    const error = { response: { status: 422, data: { message: 'El servicio no acepta reservas.' } } }
    api.post.mockRejectedValueOnce(error)

    const store = useBookingStore()
    await store.createBooking({ service_id: 1, scheduled_date: '2026-07-04', scheduled_time: '10:00', whatsapp: '+5930999' })

    expect(store.slotConflictVersion).toBe(0)
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
// Admin — venue agenda block CRUD (VAGA-001, Slice 4)
// ---------------------------------------------------------------------------

describe('booking store — agenda block admin actions', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchAgendaBlocks GETs /admin/agenda and populates agendaBlocks', async () => {
    const fakeBlocks = [
      { id: 1, day_of_week: 1, specific_date: null, open_time: '09:00', close_time: '18:00', concurrency_limit: 3, soft_threshold: 2, is_blocked: false },
    ]
    api.get.mockResolvedValueOnce({ data: { data: fakeBlocks } })

    const store = useBookingStore()
    await store.fetchAgendaBlocks()

    expect(api.get).toHaveBeenCalledWith('/admin/agenda')
    expect(store.agendaBlocks).toEqual(fakeBlocks)
  })

  it('fetchAgendaBlocks sets bookingError on API failure', async () => {
    api.get.mockRejectedValueOnce({ response: { data: { message: 'Error de servidor' } } })

    const store = useBookingStore()
    await store.fetchAgendaBlocks()

    expect(store.agendaBlocks).toEqual([])
    expect(store.bookingError).toBe('Error de servidor')
  })

  it('createAgendaBlock POSTs to /admin/agenda', async () => {
    const newBlock = { id: 5, day_of_week: 2, open_time: '09:00', close_time: '12:00', concurrency_limit: 2, soft_threshold: null, is_blocked: false }
    api.post.mockResolvedValueOnce({ data: { data: newBlock } })

    const store = useBookingStore()
    const result = await store.createAgendaBlock({
      day_of_week: 2,
      open_time: '09:00',
      close_time: '12:00',
      concurrency_limit: 2,
    })

    expect(api.post).toHaveBeenCalledWith('/admin/agenda', {
      day_of_week: 2,
      open_time: '09:00',
      close_time: '12:00',
      concurrency_limit: 2,
    })
    expect(result).toEqual(newBlock)
  })

  it('createAgendaBlock throws on validation failure (e.g. overlap 422) without swallowing the error', async () => {
    const error = { response: { status: 422, data: { message: 'overlapping block', errors: {} } } }
    api.post.mockRejectedValueOnce(error)

    const store = useBookingStore()
    await expect(
      store.createAgendaBlock({ day_of_week: 1, open_time: '09:00', close_time: '12:00' }),
    ).rejects.toBe(error)
  })

  it('updateAgendaBlock PATCHes /admin/agenda/{blockId}', async () => {
    const updated = { id: 5, close_time: '17:00' }
    api.patch.mockResolvedValueOnce({ data: { data: updated } })

    const store = useBookingStore()
    const result = await store.updateAgendaBlock(5, { close_time: '17:00' })

    expect(api.patch).toHaveBeenCalledWith('/admin/agenda/5', { close_time: '17:00' })
    expect(result).toEqual(updated)
  })

  it('updateAgendaBlock throws on validation failure without swallowing the error', async () => {
    const error = { response: { status: 422, data: { message: 'overlapping block' } } }
    api.patch.mockRejectedValueOnce(error)

    const store = useBookingStore()
    await expect(store.updateAgendaBlock(5, { close_time: '08:00' })).rejects.toBe(error)
  })

  it('deleteAgendaBlock DELETEs /admin/agenda/{blockId}', async () => {
    api.delete.mockResolvedValueOnce({})

    const store = useBookingStore()
    await store.deleteAgendaBlock(5)

    expect(api.delete).toHaveBeenCalledWith('/admin/agenda/5')
  })
})
