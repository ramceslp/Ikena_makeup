import { describe, it, expect, vi, afterEach } from 'vitest'
import {
  parseLocalDate,
  formatDateKey,
  addDaysToDateKey,
  formatDayHeading,
  formatMonthYearHeading,
  getBusinessToday,
  BUSINESS_TIMEZONE,
} from '../utils/localDate.js'

describe('localDate utils — timezone-safe Y-m-d handling', () => {
  it('parseLocalDate parses a bare Y-m-d string as local midnight, not UTC', () => {
    const date = parseLocalDate('2026-07-21')
    expect(date.getFullYear()).toBe(2026)
    expect(date.getMonth()).toBe(6) // 0-indexed: July
    expect(date.getDate()).toBe(21)
  })

  it('formatDateKey round-trips a local Date back to Y-m-d', () => {
    const date = new Date(2026, 6, 21)
    expect(formatDateKey(date)).toBe('2026-07-21')
  })

  it('formatDateKey zero-pads single-digit month and day', () => {
    const date = new Date(2026, 0, 5)
    expect(formatDateKey(date)).toBe('2026-01-05')
  })

  it('addDaysToDateKey adds days without UTC drift', () => {
    expect(addDaysToDateKey('2026-07-21', 1)).toBe('2026-07-22')
    expect(addDaysToDateKey('2026-07-21', 60)).toBe('2026-09-19')
  })

  it('addDaysToDateKey correctly crosses a month boundary', () => {
    expect(addDaysToDateKey('2026-07-31', 1)).toBe('2026-08-01')
  })

  it('formatDayHeading produces a capitalized "Weekday D de Month" heading', () => {
    // 2026-07-03 is a Friday
    expect(formatDayHeading('2026-07-03')).toBe('Viernes 3 de julio')
  })

  it('formatMonthYearHeading produces a capitalized "Month Year" heading', () => {
    expect(formatMonthYearHeading(2026, 6)).toBe('Julio 2026')
  })
})

describe('getBusinessToday — anchors "today" to the business timezone, not the browser\'s', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('exposes the business timezone as a named constant', () => {
    expect(BUSINESS_TIMEZONE).toBe('America/Guayaquil')
  })

  it('returns the business-timezone date even when the instant has already rolled over in UTC', () => {
    // 2026-07-21T02:00:00Z is still 2026-07-20T21:00:00-05:00 in America/Guayaquil.
    // A naive `new Date()` read in a UTC (or more-eastern) host timezone would
    // report the calendar day as already 2026-07-21 — one day ahead of the
    // business's actual "today".
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-07-21T02:00:00Z'))
    expect(getBusinessToday()).toBe('2026-07-20')
  })

  it('rolls over exactly at business-timezone midnight, not UTC midnight', () => {
    // 2026-07-21T05:00:00Z === 2026-07-21T00:00:00-05:00 in America/Guayaquil.
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-07-21T05:00:00Z'))
    expect(getBusinessToday()).toBe('2026-07-21')
  })

  it('builds the Y-m-d key from formatToParts() field values rather than trusting the joined format() string', () => {
    // Reduced-ICU/older engines have emitted non-dash shapes (e.g. slashes,
    // reordered fields) for 'en-CA' from `.format()`, even though
    // `.formatToParts()` still tags each field correctly. Simulating that
    // here by making `.format()` return a deliberately malformed string: an
    // implementation trusting `.format()`'s joined output would return that
    // malformed string as "today", silently corrupting every date
    // comparison, parse, and outgoing API query param downstream. Reading
    // fields via `.formatToParts()` must be immune to this.
    //
    // No fake timers here deliberately — Vitest/sinon's fake-timers
    // implementation patches `Intl.DateTimeFormat` instances with their own
    // OWN `format` property to keep locale formatting in sync with the
    // faked clock, which would shadow (and silently defeat) a spy placed on
    // the shared prototype getter.
    //
    // `Intl.DateTimeFormat.prototype.format` is a spec-defined ACCESSOR
    // (getter) that returns a bound formatting function — not a plain data
    // property — so it must be spied on via its getter, not `.format`
    // itself directly.
    const formatGetterSpy = vi
      .spyOn(Intl.DateTimeFormat.prototype, 'format', 'get')
      .mockReturnValue(() => '07/21/2026')

    const result = getBusinessToday()

    formatGetterSpy.mockRestore()

    // Computed independently (real clock, real .format()) so this doesn't
    // hardcode "today" as a literal — only the malformed-`.format()`
    // resilience is under test here.
    const expectedParts = Object.fromEntries(
      new Intl.DateTimeFormat('en-CA', {
        timeZone: BUSINESS_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      })
        .formatToParts(new Date())
        .map((part) => [part.type, part.value]),
    )
    const expected = `${expectedParts.year}-${expectedParts.month}-${expectedParts.day}`

    expect(result).toBe(expected)
    expect(result).not.toBe('07/21/2026')
    expect(result).toMatch(/^\d{4}-\d{2}-\d{2}$/)
  })
})
