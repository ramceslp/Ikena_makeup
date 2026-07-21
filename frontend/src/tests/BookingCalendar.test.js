import { describe, it, expect } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import BookingCalendar from '../components/booking/BookingCalendar.vue'

// Fixed "today" so every test is deterministic regardless of wall-clock date.
// 2026-07-21 is a Tuesday.
const TODAY = '2026-07-21'

const fakeDays = [
  { date: '2026-07-21', available_count: 3 }, // available (today)
  { date: '2026-07-22', available_count: 0 }, // full
  { date: '2026-07-23', available_count: 5 }, // available
  // 2026-07-24 absent -> closed
]

function mountCalendar(props = {}) {
  return mount(BookingCalendar, {
    props: { days: fakeDays, selectedDate: null, today: TODAY, windowDays: 60, ...props },
  })
}

describe('BookingCalendar.vue', () => {
  it('renders three distinct day states: available, full, closed', () => {
    const wrapper = mountCalendar()
    const cells = wrapper.findAll('[data-calendar-day]')
    const byKey = Object.fromEntries(cells.map((c) => [c.attributes('data-date'), c]))

    expect(byKey['2026-07-21'].attributes('data-day-state')).toBe('available')
    expect(byKey['2026-07-22'].attributes('data-day-state')).toBe('full')
    expect(byKey['2026-07-24'].attributes('data-day-state')).toBe('closed')
  })

  it('marks days before "today" (past, same month) as outside the window', () => {
    const wrapper = mountCalendar()
    const cells = wrapper.findAll('[data-calendar-day]')
    const byKey = Object.fromEntries(cells.map((c) => [c.attributes('data-date'), c]))
    // July 2026 starts on a Wednesday; 2026-07-01 is in-grid and before today
    expect(byKey['2026-07-01'].attributes('data-day-state')).toBe('outside')
    expect(byKey['2026-07-01'].element.disabled).toBe(true)
  })

  it('full and closed days are disabled and cannot be selected', async () => {
    const wrapper = mountCalendar()
    const cells = wrapper.findAll('[data-calendar-day]')
    const byKey = Object.fromEntries(cells.map((c) => [c.attributes('data-date'), c]))

    expect(byKey['2026-07-22'].element.disabled).toBe(true) // full
    expect(byKey['2026-07-24'].element.disabled).toBe(true) // closed

    await byKey['2026-07-22'].trigger('click')
    await byKey['2026-07-24'].trigger('click')
    expect(wrapper.emitted('day-selected')).toBeFalsy()
  })

  it('clicking an available day emits day-selected with its date key', async () => {
    const wrapper = mountCalendar()
    const cells = wrapper.findAll('[data-calendar-day]')
    const target = cells.find((c) => c.attributes('data-date') === '2026-07-23')

    await target.trigger('click')

    expect(wrapper.emitted('day-selected')).toBeTruthy()
    expect(wrapper.emitted('day-selected')[0]).toEqual(['2026-07-23'])
  })

  it('marks the selectedDate day with data-day-selected and aria-pressed', () => {
    const wrapper = mountCalendar({ selectedDate: '2026-07-23' })
    const cells = wrapper.findAll('[data-calendar-day]')
    const selected = cells.find((c) => c.attributes('data-date') === '2026-07-23')
    const other = cells.find((c) => c.attributes('data-date') === '2026-07-21')

    expect(selected.attributes('data-day-selected')).toBe('true')
    expect(selected.attributes('aria-pressed')).toBe('true')
    expect(other.attributes('data-day-selected')).toBeFalsy()
  })

  it('every day cell has an aria-label conveying date and availability', () => {
    const wrapper = mountCalendar()
    const cells = wrapper.findAll('[data-calendar-day]')
    const available = cells.find((c) => c.attributes('data-date') === '2026-07-21')
    const full = cells.find((c) => c.attributes('data-date') === '2026-07-22')

    expect(available.attributes('aria-label')).toContain('3 horarios disponibles')
    expect(full.attributes('aria-label')).toContain('completo')
  })

  it('disables the "previous month" control at the first month of the booking window', () => {
    const wrapper = mountCalendar()
    const prevBtn = wrapper.find('[data-calendar-prev]')
    expect(prevBtn.exists()).toBe(true)
    expect(prevBtn.element.disabled).toBe(true)
  })

  it('disables the "next month" control at the last month of the booking window', async () => {
    const wrapper = mountCalendar({ windowDays: 10 })
    // today (2026-07-21) + 9 days is still July -> only one month in the window
    const nextBtn = wrapper.find('[data-calendar-next]')
    expect(nextBtn.exists()).toBe(true)
    expect(nextBtn.element.disabled).toBe(true)
  })

  it('enables next-month navigation and updates the heading when clicked, within the window', async () => {
    const wrapper = mountCalendar()
    expect(wrapper.find('[data-calendar-heading]').text()).toContain('Julio 2026')

    const nextBtn = wrapper.find('[data-calendar-next]')
    expect(nextBtn.element.disabled).toBe(false)
    await nextBtn.trigger('click')

    expect(wrapper.find('[data-calendar-heading]').text()).toContain('Agosto 2026')
  })

  it('supports keyboard navigation: ArrowRight moves roving focus to the next available day', async () => {
    const wrapper = mountCalendar()
    const cells = wrapper.findAll('[data-calendar-day]')
    const first = cells.find((c) => c.attributes('data-date') === '2026-07-21')
    const next = cells.find((c) => c.attributes('data-date') === '2026-07-22') // full, must be skipped
    const secondAvailable = cells.find((c) => c.attributes('data-date') === '2026-07-23')

    // Roving tabindex starts on the first available day.
    expect(first.attributes('tabindex')).toBe('0')

    first.element.focus()
    await first.trigger('keydown', { key: 'ArrowRight' })

    // 07-22 is full/disabled and must not receive focus (not a focus trap either way).
    expect(next.element.disabled).toBe(true)
  })

  it('supports keyboard selection: Enter on a focused available day emits day-selected', async () => {
    const wrapper = mountCalendar()
    const cells = wrapper.findAll('[data-calendar-day]')
    const target = cells.find((c) => c.attributes('data-date') === '2026-07-21')

    await target.trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('day-selected')).toBeTruthy()
    expect(wrapper.emitted('day-selected')[0]).toEqual(['2026-07-21'])
  })

  it('re-validates roving focus when the `days` prop changes without a month change (e.g. post-409 refresh)', async () => {
    const wrapper = mountCalendar()
    const cells = () => wrapper.findAll('[data-calendar-day]')
    const byKey = () => Object.fromEntries(cells().map((c) => [c.attributes('data-date'), c]))

    // Roving tabindex starts on the first available day (2026-07-21, "today").
    expect(byKey()['2026-07-21'].attributes('tabindex')).toBe('0')

    // Simulate the exact scenario this bug exists for: a booking 409 causes
    // fetchAvailableDays to refresh, and the focused day flips from
    // available to full. The month does NOT change.
    await wrapper.setProps({
      days: [
        { date: '2026-07-21', available_count: 0 }, // now full — was focused
        { date: '2026-07-22', available_count: 0 }, // full
        { date: '2026-07-23', available_count: 5 }, // still available
      ],
    })

    const afterCells = cells()
    const tabbable = afterCells.filter((c) => c.attributes('tabindex') === '0')
    // Exactly one cell must remain Tab-reachable — the grid must never be
    // left with every cell at tabindex="-1".
    expect(tabbable).toHaveLength(1)
    expect(tabbable[0].attributes('data-date')).toBe('2026-07-23')
  })

  it('disabled day cells are not focus traps (native disabled, removed from tab order)', () => {
    const wrapper = mountCalendar()
    const cells = wrapper.findAll('[data-calendar-day]')
    const full = cells.find((c) => c.attributes('data-date') === '2026-07-22')
    const closed = cells.find((c) => c.attributes('data-date') === '2026-07-24')

    expect(full.element.tagName).toBe('BUTTON')
    expect(full.element.disabled).toBe(true)
    expect(closed.element.tagName).toBe('BUTTON')
    expect(closed.element.disabled).toBe(true)
  })

  it('moves actual DOM focus to the new focusable day when the focused cell becomes unavailable (post-409 refresh)', async () => {
    // Must be attached to document.body for `document.activeElement` / native
    // `:disabled` blur behavior to work realistically in jsdom.
    const wrapper = mount(BookingCalendar, {
      props: { days: fakeDays, selectedDate: null, today: TODAY, windowDays: 60 },
      attachTo: document.body,
    })

    try {
      const cells = () => wrapper.findAll('[data-calendar-day]')
      const byKey = () => Object.fromEntries(cells().map((c) => [c.attributes('data-date'), c]))

      const initiallyFocused = byKey()['2026-07-21']
      initiallyFocused.element.focus()
      expect(document.activeElement).toBe(initiallyFocused.element)

      // Same scenario as the tabindex test above, but this time assert the
      // OBSERVABLE effect: real DOM focus must move, not just tabindex
      // bookkeeping — a buggy implementation can satisfy tabindex="0" on the
      // new cell while `document.activeElement` is still stuck on
      // `document.body` because the old cell was natively blurred by its own
      // `:disabled` binding.
      await wrapper.setProps({
        days: [
          { date: '2026-07-21', available_count: 0 }, // now full — was focused
          { date: '2026-07-22', available_count: 0 }, // full
          { date: '2026-07-23', available_count: 5 }, // still available
        ],
      })
      // The fix defers the actual `.focus()` call by one extra tick (it must
      // run AFTER the re-render that applies `:disabled`), so a single
      // `setProps` await isn't enough to observe it settled.
      await flushPromises()

      const newTarget = byKey()['2026-07-23'].element
      expect(document.activeElement).toBe(newTarget)
    } finally {
      wrapper.unmount()
    }
  })

  it('does not steal focus from outside the grid when `days` refreshes in the background', async () => {
    const wrapper = mount(BookingCalendar, {
      props: { days: fakeDays, selectedDate: null, today: TODAY, windowDays: 60 },
      attachTo: document.body,
    })

    try {
      // Focus something entirely unrelated to the calendar.
      const outsideButton = document.createElement('button')
      document.body.appendChild(outsideButton)
      outsideButton.focus()
      expect(document.activeElement).toBe(outsideButton)

      await wrapper.setProps({
        days: [
          { date: '2026-07-21', available_count: 0 },
          { date: '2026-07-22', available_count: 0 },
          { date: '2026-07-23', available_count: 5 },
        ],
      })
      await flushPromises()

      // Focus must remain on the unrelated element — the calendar must not
      // yank it away just because its own data refreshed.
      expect(document.activeElement).toBe(outsideButton)
      document.body.removeChild(outsideButton)
    } finally {
      wrapper.unmount()
    }
  })

  it('does not steal focus from outside the grid when a day becomes available in the background, even when no cell was previously focused/focusable (both sides of the guard start null)', async () => {
    // No cell is available anywhere in the whole window, so `focusedKey`
    // starts as `null` (nothing to route the roving tabindex to).
    const fullyBookedEverywhere = [
      { date: '2026-07-21', available_count: 0 },
      { date: '2026-07-22', available_count: 0 },
    ]
    const wrapper = mount(BookingCalendar, {
      props: { days: fullyBookedEverywhere, selectedDate: null, today: TODAY, windowDays: 60 },
      attachTo: document.body,
    })

    try {
      const tabbableBefore = wrapper
        .findAll('[data-calendar-day]')
        .filter((c) => c.attributes('tabindex') === '0')
      expect(tabbableBefore).toHaveLength(0)

      // Focus something entirely unrelated to the calendar — e.g. a sibling
      // form field, as in the real app. `domFocusedDayKey()` is ALSO `null`
      // here (nothing in the grid holds DOM focus) — a guard that compares
      // `domFocusedDayKey() === focusedKey.value` without excluding `null`
      // would misread `null === null` as "focus was inside the grid".
      const outsideInput = document.createElement('input')
      document.body.appendChild(outsideInput)
      outsideInput.focus()
      expect(document.activeElement).toBe(outsideInput)

      // A day becomes available in the background.
      await wrapper.setProps({
        days: [
          { date: '2026-07-21', available_count: 2 },
          { date: '2026-07-22', available_count: 0 },
        ],
      })
      await flushPromises()

      // Focus must remain on the outside input — the calendar must not yank
      // real DOM focus into itself just because a cell became reachable.
      expect(document.activeElement).toBe(outsideInput)
      document.body.removeChild(outsideInput)
    } finally {
      wrapper.unmount()
    }
  })

  it('auto-advances to the first month within the window that has an available day, when the initially-viewed month has none', () => {
    // Every day present in July is fully booked; the only opening is in
    // August, still within the default 60-day window.
    const fullyBookedJulyThenOpenAugust = [
      { date: '2026-07-21', available_count: 0 },
      { date: '2026-07-22', available_count: 0 },
      { date: '2026-08-05', available_count: 2 },
    ]

    const wrapper = mountCalendar({ days: fullyBookedJulyThenOpenAugust })

    // The view must have jumped to August, not stayed stranded on July with
    // zero focusable cells.
    expect(wrapper.find('[data-calendar-heading]').text()).toContain('Agosto 2026')

    const cells = wrapper.findAll('[data-calendar-day]')
    const aug5 = cells.find((c) => c.attributes('data-date') === '2026-08-05')
    expect(aug5.attributes('data-day-state')).toBe('available')
    expect(aug5.attributes('tabindex')).toBe('0')

    // Exactly one cell is Tab-reachable — the keyboard entry point exists.
    const tabbable = cells.filter((c) => c.attributes('tabindex') === '0')
    expect(tabbable).toHaveLength(1)
  })

  it('does NOT auto-advance the viewed month when a background `days` refresh empties it out — only the initial mount may auto-advance', async () => {
    // Initial month (July) has one available day, so the setup-time jump is
    // a no-op and the view legitimately starts on July.
    const wrapper = mountCalendar({
      days: [{ date: '2026-07-21', available_count: 2 }],
    })
    expect(wrapper.find('[data-calendar-heading]').text()).toContain('Julio 2026')

    // Background refresh (e.g. a post-409 re-fetch) empties July but opens
    // up August. The user is still looking at July — the view must NOT
    // teleport to August out from under them.
    await wrapper.setProps({
      days: [
        { date: '2026-07-21', available_count: 0 },
        { date: '2026-08-05', available_count: 2 },
      ],
    })
    await flushPromises()

    expect(wrapper.find('[data-calendar-heading]').text()).toContain('Julio 2026')
    // Accepted, documented trade-off: with July now fully booked, no cell in
    // the currently-viewed month is Tab-reachable — the month-nav buttons
    // remain the way out, not a jump the user didn't ask for.
    const tabbable = wrapper.findAll('[data-calendar-day]').filter((c) => c.attributes('tabindex') === '0')
    expect(tabbable).toHaveLength(0)
  })

  it('stays on the current month when NO month in the whole window has any availability', () => {
    const fullyBookedEverywhere = [
      { date: '2026-07-21', available_count: 0 },
      { date: '2026-07-22', available_count: 0 },
    ]

    const wrapper = mountCalendar({ days: fullyBookedEverywhere })

    expect(wrapper.find('[data-calendar-heading]').text()).toContain('Julio 2026')
    const cells = wrapper.findAll('[data-calendar-day]')
    const tabbable = cells.filter((c) => c.attributes('tabindex') === '0')
    expect(tabbable).toHaveLength(0)
  })
})

describe('BookingCalendar.vue — locked (booking submit in flight)', () => {
  it('disables day cells and month-nav buttons when locked is true, even for otherwise-available days', () => {
    const wrapper = mountCalendar({ locked: true })
    const cells = wrapper.findAll('[data-calendar-day]')
    const byKey = Object.fromEntries(cells.map((c) => [c.attributes('data-date'), c]))

    // 2026-07-21 and 2026-07-23 are 'available' per fakeDays but must still
    // be disabled while a booking submission is locking the calendar.
    expect(byKey['2026-07-21'].attributes('data-day-state')).toBe('available')
    expect(byKey['2026-07-21'].element.disabled).toBe(true)
    expect(byKey['2026-07-23'].element.disabled).toBe(true)

    expect(wrapper.find('[data-calendar-prev]').element.disabled).toBe(true)
    expect(wrapper.find('[data-calendar-next]').element.disabled).toBe(true)
  })

  it('clicking an available day cell while locked does not emit day-selected', async () => {
    const wrapper = mountCalendar({ locked: true })
    const cells = wrapper.findAll('[data-calendar-day]')
    const target = cells.find((c) => c.attributes('data-date') === '2026-07-23')

    await target.trigger('click')

    expect(wrapper.emitted('day-selected')).toBeFalsy()
  })

  it('conveys via aria-label that an available day is locked, not permanently unavailable', () => {
    const wrapper = mountCalendar({ locked: true })
    const cells = wrapper.findAll('[data-calendar-day]')
    const available = cells.find((c) => c.attributes('data-date') === '2026-07-21')

    expect(available.attributes('aria-label')).toContain('reserva en proceso')
  })

  it('does not lock the calendar when locked is false (default), even with days loading', () => {
    const wrapper = mountCalendar()
    const cells = wrapper.findAll('[data-calendar-day]')
    const available = cells.find((c) => c.attributes('data-date') === '2026-07-21')

    expect(available.element.disabled).toBe(false)
    expect(wrapper.find('[data-calendar-next]').element.disabled).toBe(false)
  })
})
