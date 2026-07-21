import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { useBookingStore } from '../stores/booking.js'

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
import SlotPicker from '../components/booking/SlotPicker.vue'
import BookingForm from '../components/booking/BookingForm.vue'

const fakeDays = [
  { date: '2026-07-21', available_count: 2 },
  { date: '2026-07-22', available_count: 3 },
]

const daySlotsFor21 = [
  { id: 1, date_label: '2026-07-21', start_time: '10:00', capacity_remaining: 1, is_blocked: false },
  { id: 2, date_label: '2026-07-21', start_time: '11:00', capacity_remaining: 0, is_blocked: false },
]

const daySlotsFor22 = [
  { id: 3, date_label: '2026-07-22', start_time: '09:00', capacity_remaining: 1, is_blocked: false },
]

function mountPicker(props = {}) {
  const pinia = createPinia()
  return mount(SlotPicker, {
    props: { serviceId: 1, ...props },
    global: { plugins: [pinia] },
  })
}

// Variant that also hands back the pinia instance so a test can reach into
// the store directly (e.g. to simulate a 409 conflict signal), or mount a
// sibling component (e.g. BookingForm) sharing the same store.
function mountPickerWithStore(props = {}) {
  const pinia = createPinia()
  const wrapper = mount(SlotPicker, {
    props: { serviceId: 1, ...props },
    global: { plugins: [pinia] },
  })
  const store = useBookingStore(pinia)
  return { wrapper, store, pinia }
}

// Fixtures below hardcode 2026-07 dates while `today` inside SlotPicker
// comes from the real `getBusinessToday()`. Freeze the clock inside that
// window so cell states (available/full/closed/outside) stay deterministic
// regardless of the real wall-clock date — mirrors localDate.test.js.
// 2026-07-21T15:00:00Z === 2026-07-21T10:00:00-05:00 in America/Guayaquil.
beforeEach(() => {
  vi.useFakeTimers()
  vi.setSystemTime(new Date('2026-07-21T15:00:00Z'))
})

afterEach(() => {
  vi.useRealTimers()
})

describe('SlotPicker.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches the available-days calendar on mount and renders day cells', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })

    const wrapper = mountPicker()
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/services/1/available-days')
    const cells = wrapper.findAll('[data-calendar-day]')
    expect(cells.length).toBeGreaterThan(0)
    const available = cells.filter((c) => c.attributes('data-day-state') === 'available')
    expect(available.length).toBeGreaterThanOrEqual(2)
  })

  it('renders empty state when the service has no available days at all', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mountPicker()
    await flushPromises()

    expect(wrapper.text()).toContain('No hay horarios disponibles')
  })

  it('selecting a day fetches and renders that day\'s time chips with a heading', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } }) // available-days
    api.get.mockResolvedValueOnce({ data: { data: daySlotsFor21 } }) // available-slots?date=2026-07-21

    const wrapper = mountPicker()
    await flushPromises()

    const dayCell = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-21')
    await dayCell.trigger('click')
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/services/1/available-slots', {
      params: { date: '2026-07-21' },
    })
    expect(wrapper.find('[data-day-heading]').text()).toContain('21 de julio')
    const chips = wrapper.findAll('[data-slot-card]')
    expect(chips).toHaveLength(2)
    expect(wrapper.text()).toContain('10:00')
    expect(wrapper.text()).toContain('11:00')
  })

  it('shows a loading state while the selected day\'s slots are in flight', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })
    let resolveSlots
    api.get.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          resolveSlots = () => resolve({ data: { data: daySlotsFor21 } })
        }),
    )

    const wrapper = mountPicker()
    await flushPromises()

    const dayCell = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-21')
    await dayCell.trigger('click')
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-day-slots-loading]').exists()).toBe(true)

    resolveSlots()
    await flushPromises()

    expect(wrapper.find('[data-day-slots-loading]').exists()).toBe(false)
  })

  it('clicking a time chip emits slot-selected with { id, scheduled_date, scheduled_time }', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })
    api.get.mockResolvedValueOnce({ data: { data: daySlotsFor21 } })

    const wrapper = mountPicker()
    await flushPromises()
    const dayCell = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-21')
    await dayCell.trigger('click')
    await flushPromises()

    const chip = wrapper.findAll('[data-slot-card]')[0]
    await chip.trigger('click')

    const emitted = wrapper.emitted('slot-selected')
    expect(emitted).toBeTruthy()
    expect(emitted[0][0]).toEqual({
      id: 1,
      scheduled_date: '2026-07-21',
      scheduled_time: '10:00',
    })
  })

  it('marks the clicked chip as selected via data-slot-selected', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })
    api.get.mockResolvedValueOnce({ data: { data: daySlotsFor21 } })

    const wrapper = mountPicker()
    await flushPromises()
    const dayCell = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-21')
    await dayCell.trigger('click')
    await flushPromises()

    const chips = wrapper.findAll('[data-slot-card]')
    await chips[0].trigger('click')

    expect(chips[0].attributes('data-slot-selected')).toBe('true')
    expect(chips[1].attributes('data-slot-selected')).toBeFalsy()
  })

  it('disabled chips (capacity_remaining=0) cannot be clicked and do not emit', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })
    api.get.mockResolvedValueOnce({ data: { data: daySlotsFor21 } })

    const wrapper = mountPicker()
    await flushPromises()
    const dayCell = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-21')
    await dayCell.trigger('click')
    await flushPromises()

    const fullChip = wrapper.findAll('[data-slot-card]')[1] // 11:00, capacity_remaining: 0
    expect(fullChip.element.disabled).toBe(true)
    await fullChip.trigger('click')

    expect(wrapper.emitted('slot-selected')).toBeFalsy()
  })

  it('renders the near-capacity warning badge when is_near_capacity is true', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })
    api.get.mockResolvedValueOnce({
      data: {
        data: [
          {
            id: 9,
            date_label: '2026-07-21',
            start_time: '12:00',
            capacity_remaining: 1,
            is_blocked: false,
            is_near_capacity: true,
            warning_message: 'Alta demanda — quedan pocos horarios',
          },
        ],
      },
    })

    const wrapper = mountPicker()
    await flushPromises()
    const dayCell = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-21')
    await dayCell.trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-near-capacity-badge]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Alta demanda — quedan pocos horarios')
  })

  it('selecting a different day clears any previously selected time', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })
    api.get.mockResolvedValueOnce({ data: { data: daySlotsFor21 } })
    api.get.mockResolvedValueOnce({ data: { data: daySlotsFor22 } })

    const wrapper = mountPicker()
    await flushPromises()

    const day21 = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-21')
    await day21.trigger('click')
    await flushPromises()
    await wrapper.findAll('[data-slot-card]')[0].trigger('click')
    expect(wrapper.findAll('[data-slot-card]')[0].attributes('data-slot-selected')).toBe('true')

    const day22 = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-22')
    await day22.trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-slot-selected="true"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('09:00')
  })

  it('selecting a different day also emits selection-cleared so the parent drops its stale selectedSlot', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })
    api.get.mockResolvedValueOnce({ data: { data: daySlotsFor21 } })
    api.get.mockResolvedValueOnce({ data: { data: daySlotsFor22 } })

    const wrapper = mountPicker()
    await flushPromises()

    const day21 = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-21')
    await day21.trigger('click')
    await flushPromises()
    await wrapper.findAll('[data-slot-card]')[0].trigger('click')

    const day22 = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-22')
    await day22.trigger('click')
    await flushPromises()

    expect(wrapper.emitted('selection-cleared')).toBeTruthy()
  })

  it('switching to a different day hides a stale 409 error banner rendered by a sibling BookingForm', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })
    api.get.mockResolvedValueOnce({ data: { data: daySlotsFor21 } })
    api.get.mockResolvedValueOnce({ data: { data: daySlotsFor22 } })

    const { wrapper, store, pinia } = mountPickerWithStore()
    await flushPromises()

    const day21 = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-21')
    await day21.trigger('click')
    await flushPromises()

    // Simulate a previous 409 having set the shared store's bookingError —
    // this is what BookingForm renders as its inline alert banner.
    store.bookingError = 'Este horario ya no está disponible. Por favor elige otro.'
    const formWrapper = mount(BookingForm, {
      props: {
        selectedSlot: null,
        service: { id: 1, title: 'Maquillaje Social', price: '150.00', deposit_percentage: 30 },
      },
      global: { plugins: [pinia] },
    })
    expect(formWrapper.find('[role="alert"]').exists()).toBe(true)
    expect(formWrapper.text()).toContain('Este horario ya no está disponible')

    const day22 = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-22')
    await day22.trigger('click')
    await flushPromises()
    await formWrapper.vm.$nextTick()

    // The banner must disappear — it described a slot from a day the user
    // is no longer looking at.
    expect(formWrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('a 409 slot-conflict signal from the store clears the locally selected chip and notifies the parent', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })
    api.get.mockResolvedValueOnce({ data: { data: daySlotsFor21 } })

    const { wrapper, store } = mountPickerWithStore()
    await flushPromises()

    const dayCell = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-21')
    await dayCell.trigger('click')
    await flushPromises()
    await wrapper.findAll('[data-slot-card]')[0].trigger('click')
    expect(wrapper.findAll('[data-slot-card]')[0].attributes('data-slot-selected')).toBe('true')

    // Simulate the store having just processed a 409 from BookingForm's submit.
    store.slotConflictVersion += 1
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-slot-selected="true"]').exists()).toBe(false)
    expect(wrapper.emitted('selection-cleared')).toBeTruthy()
  })

  it('keyboard: Enter on a focused, enabled time chip selects it and emits slot-selected', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })
    api.get.mockResolvedValueOnce({ data: { data: daySlotsFor21 } })

    const wrapper = mountPicker()
    await flushPromises()
    const dayCell = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-21')
    await dayCell.trigger('click')
    await flushPromises()

    const chip = wrapper.findAll('[data-slot-card]')[0]
    await chip.trigger('keydown', { key: 'Enter' })

    const emitted = wrapper.emitted('slot-selected')
    expect(emitted).toBeTruthy()
    expect(emitted[0][0]).toEqual({
      id: 1,
      scheduled_date: '2026-07-21',
      scheduled_time: '10:00',
    })
  })
})

// ---------------------------------------------------------------------------
// Locks calendar interaction while a booking submit (bookingStore.isLoading)
// is in flight — day cells, month-nav buttons and time chips must all be
// disabled/inert, so the user cannot switch day/month/slot and race the 409
// handler's two cascading refreshes. Must NOT lock during ordinary
// background day/days fetches (isDaysLoading/isDaySlotsLoading) — those are
// a different, non-blocking condition.
// ---------------------------------------------------------------------------

describe('SlotPicker.vue — locks calendar interaction while a booking submit is in flight', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('disables day cells, month-nav buttons and time chips while bookingStore.isLoading is true', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })
    api.get.mockResolvedValueOnce({ data: { data: daySlotsFor21 } })

    const { wrapper, store } = mountPickerWithStore()
    await flushPromises()

    const day21 = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-21')
    await day21.trigger('click')
    await flushPromises()

    store.isLoading = true
    await wrapper.vm.$nextTick()

    // 2026-07-22 is 'available' (available_count: 3) per fakeDays — it would
    // otherwise be clickable, but must be locked while a submit is pending.
    const day22 = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-22')
    expect(day22.element.disabled).toBe(true)
    expect(wrapper.find('[data-calendar-next]').element.disabled).toBe(true)
    expect(wrapper.find('[data-calendar-prev]').element.disabled).toBe(true)

    const chips = wrapper.findAll('[data-slot-card]')
    expect(chips[0].element.disabled).toBe(true) // 10:00 — otherwise selectable (capacity_remaining: 1)
  })

  it('does not disable day cells or time chips during an ordinary background days/day-slots fetch (only during a booking submit)', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })
    api.get.mockResolvedValueOnce({ data: { data: daySlotsFor21 } })

    const { wrapper, store } = mountPickerWithStore()
    await flushPromises()

    const day21 = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-21')
    await day21.trigger('click')
    await flushPromises()

    // isDaySlotsLoading is deliberately NOT set here — flipping it hides the
    // chips behind their own "Cargando horarios…" indicator regardless of
    // any lock, which is unrelated existing behavior, not what's under test.
    store.isDaysLoading = true
    await wrapper.vm.$nextTick()

    const day22 = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-22')
    expect(day22.element.disabled).toBe(false)
    expect(wrapper.find('[data-calendar-next]').element.disabled).toBe(false)

    const chips = wrapper.findAll('[data-slot-card]')
    expect(chips[0].element.disabled).toBe(false)
  })

  it('clicking a day cell or time chip while a submit is in flight has no effect', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })
    api.get.mockResolvedValueOnce({ data: { data: daySlotsFor21 } })

    const { wrapper, store } = mountPickerWithStore()
    await flushPromises()

    const day21 = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-21')
    await day21.trigger('click')
    await flushPromises()

    store.isLoading = true
    await wrapper.vm.$nextTick()

    const selectionClearedCountBefore = (wrapper.emitted('selection-cleared') || []).length
    const day22 = wrapper.findAll('[data-calendar-day]').find((c) => c.attributes('data-date') === '2026-07-22')
    await day22.trigger('click')

    // Locked click must be a no-op: no new day-selection side effect fires.
    expect((wrapper.emitted('selection-cleared') || []).length).toBe(selectionClearedCountBefore)
    expect(wrapper.find('[data-day-heading]').text()).toContain('21')
  })
})

// ---------------------------------------------------------------------------
// Surfaces a background `fetchAvailableDays` failure when `days` is already
// non-empty — previously silently swallowed (no branch rendered anything),
// leaving a stale calendar with no indication the refresh failed.
// ---------------------------------------------------------------------------

describe('SlotPicker.vue — background calendar refresh failure', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows a non-blocking "could not refresh" indicator when a background fetchAvailableDays fails after days already loaded', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })

    const { wrapper, store } = mountPickerWithStore()
    await flushPromises()

    expect(wrapper.find('[data-days-refresh-error]').exists()).toBe(false)

    api.get.mockRejectedValueOnce({ response: { data: { message: 'Error de red' } } })
    await store.fetchAvailableDays(1)
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-days-refresh-error]').exists()).toBe(true)
    expect(wrapper.text()).toContain('No se pudo actualizar el calendario')
    // The stale calendar must still be visible, not replaced by an error-only view.
    expect(wrapper.findAll('[data-calendar-day]').length).toBeGreaterThan(0)
  })

  it('retry button re-triggers fetchAvailableDays and clears the indicator on success', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })
    const { wrapper, store } = mountPickerWithStore()
    await flushPromises()

    api.get.mockRejectedValueOnce({ response: { data: { message: 'Error de red' } } })
    await store.fetchAvailableDays(1)
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[data-days-refresh-error]').exists()).toBe(true)

    api.get.mockResolvedValueOnce({ data: { data: fakeDays } })
    await wrapper.find('[data-days-refresh-retry]').trigger('click')
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/services/1/available-days')
    expect(wrapper.find('[data-days-refresh-error]').exists()).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// Integration: the REAL composition (SlotPicker + BookingForm sharing a
// store), driven exactly like ServiceDetail.vue wires them, through the full
// day-select -> slot-select -> submit -> 409 flow. Unlike the tests above
// (which mount SlotPicker in isolation) and unlike BookingCalendar's own
// isolated `setProps` tests, nothing here artificially forces a prop update
// that the real app cannot produce — the 409 is driven by an actual rejected
// `api.post`, and the resulting day/calendar refresh goes through the real
// store -> SlotPicker -> BookingCalendar chain.
// ---------------------------------------------------------------------------

describe('SlotPicker.vue — real 409 integration (SlotPicker + BookingForm, real DOM focus)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  const initialDays = [
    { date: '2026-07-21', available_count: 1 }, // will become full after the 409
    { date: '2026-07-23', available_count: 3 }, // stays available — the expected focus target afterward
  ]
  const refreshedDays = [
    { date: '2026-07-21', available_count: 0 },
    { date: '2026-07-23', available_count: 3 },
  ]
  const daySlotsFor21Initial = [
    { id: 1, date_label: '2026-07-21', start_time: '10:00', capacity_remaining: 1, is_blocked: false },
  ]
  const daySlotsFor21Refreshed = [
    { id: 1, date_label: '2026-07-21', start_time: '10:00', capacity_remaining: 0, is_blocked: false },
  ]

  it('keeps meaningful DOM focus inside the calendar, and a consistent day heading/chips, across a real 409 conflict', async () => {
    let daysCallCount = 0
    let slots21CallCount = 0
    api.get.mockImplementation((url, config) => {
      if (url === '/services/1/available-days') {
        daysCallCount += 1
        return Promise.resolve({ data: { data: daysCallCount === 1 ? initialDays : refreshedDays } })
      }
      if (url === '/services/1/available-slots') {
        if (config.params.date === '2026-07-21') {
          slots21CallCount += 1
          return Promise.resolve({
            data: { data: slots21CallCount === 1 ? daySlotsFor21Initial : daySlotsFor21Refreshed },
          })
        }
        return Promise.resolve({ data: { data: [] } })
      }
      throw new Error(`unexpected GET ${url}`)
    })
    api.post.mockRejectedValueOnce({ response: { status: 409, data: {} } })

    const pinia = createPinia()
    const pickerWrapper = mount(SlotPicker, {
      props: { serviceId: 1 },
      global: { plugins: [pinia] },
      attachTo: document.body,
    })

    try {
      // 1. Days load.
      await flushPromises()

      // 2. User selects a day via keyboard (real focus + Enter), not a click.
      const day21 = pickerWrapper
        .findAll('[data-calendar-day]')
        .find((c) => c.attributes('data-date') === '2026-07-21')
      day21.element.focus()
      expect(document.activeElement).toBe(day21.element)
      await day21.trigger('keydown', { key: 'Enter' })
      await flushPromises()

      expect(pickerWrapper.find('[data-day-heading]').text()).toContain('21')

      // 3. Slots load, user selects a slot.
      const chip = pickerWrapper.findAll('[data-slot-card]')[0]
      await chip.trigger('click')
      const slotSelectedEvents = pickerWrapper.emitted('slot-selected')
      expect(slotSelectedEvents).toBeTruthy()
      const selectedSlot = slotSelectedEvents[slotSelectedEvents.length - 1][0]
      expect(selectedSlot).toEqual({ id: 1, scheduled_date: '2026-07-21', scheduled_time: '10:00' })

      // 4. Submit — BookingForm is a real sibling sharing the same store,
      // exactly like ServiceDetail.vue wires it.
      const formWrapper = mount(BookingForm, {
        props: {
          selectedSlot,
          service: { id: 1, title: 'Maquillaje Social', price: '150.00', deposit_percentage: 30 },
        },
        global: { plugins: [pinia] },
        attachTo: document.body,
      })

      await formWrapper.find('[data-whatsapp-input]').setValue('+593099999999')
      await formWrapper.find('[data-submit-btn]').trigger('click')

      // 4b. While the POST is in flight, the calendar must be LOCKED — day
      // 23 (still available) and the month-nav controls must be disabled,
      // so the user can't navigate away from day 21 mid-submit.
      const day23DuringSubmit = pickerWrapper
        .findAll('[data-calendar-day]')
        .find((c) => c.attributes('data-date') === '2026-07-23')
      expect(day23DuringSubmit.element.disabled).toBe(true)
      expect(pickerWrapper.find('[data-calendar-next]').element.disabled).toBe(true)

      await flushPromises()

      // 5. Server responded 409 — assert the OBSERVABLE effect: real DOM
      // focus must still be on a meaningful element inside the calendar, not
      // reset to document.body. Pre-FIX-1, SlotPicker's v-if/v-else-if
      // branches unmount <BookingCalendar> on every `fetchAvailableDays`
      // refresh (isDaysLoading flips true/false around the network call),
      // which destroys DOM focus and never restores it on the fresh
      // instance.
      expect(document.activeElement).not.toBe(document.body)
      const focusedCell = document.activeElement.closest('[data-calendar-day]')
      expect(focusedCell).not.toBeNull()
      expect(pickerWrapper.element.contains(document.activeElement)).toBe(true)
      // Day 21 is now full (capacity_remaining dropped to 0) — real focus
      // must have migrated to the next available day, 23rd.
      expect(focusedCell.dataset.date).toBe('2026-07-23')

      // 6. The rendered day heading and the rendered time chips must still
      // refer to the SAME day (21st) — the store's 409 handler re-fetches
      // the submitted day directly (day-switching is locked for the whole
      // duration of the submit, so it's necessarily still the day on screen).
      expect(pickerWrapper.find('[data-day-heading]').text()).toContain('21')
      const refreshedChips = pickerWrapper.findAll('[data-slot-card]')
      expect(refreshedChips).toHaveLength(daySlotsFor21Refreshed.length)
      expect(refreshedChips[0].text()).toContain('10:00')

      formWrapper.unmount()
    } finally {
      pickerWrapper.unmount()
    }
  })
})
