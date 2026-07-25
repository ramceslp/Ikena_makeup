/**
 * Timezone-safe helpers for the plain Y-m-d date strings the booking API
 * emits (business timezone: America/Guayaquil). `new Date('2026-07-21')`
 * parses as UTC midnight, which shifts to the previous local day for anyone
 * west of UTC — every function here works in LOCAL time instead, either by
 * appending a local-time suffix before parsing or by reading local Date
 * getters when formatting back to a string.
 */

/**
 * IANA identifier for the business's local day boundary (Ecuador, UTC-5,
 * no DST). The backend derives its `[today, today + look_ahead_days)`
 * availability window from `config('booking.timezone')`, which is set to
 * this same zone — see `AvailableSlotsRequest` and `VenueAvailabilityResolver`.
 */
export const BUSINESS_TIMEZONE = 'America/Guayaquil'

function pad2(n) {
  return String(n).padStart(2, '0')
}

function capitalize(s) {
  return s.charAt(0).toUpperCase() + s.slice(1)
}

/** Parses a bare 'YYYY-MM-DD' string as local midnight (never UTC). */
export function parseLocalDate(dateKey) {
  return new Date(`${dateKey}T00:00:00`)
}

/** Formats a Date back into 'YYYY-MM-DD' using LOCAL fields (not toISOString). */
export function formatDateKey(date) {
  return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`
}

/** Adds `days` (may be negative) to a 'YYYY-MM-DD' key, staying in local time. */
export function addDaysToDateKey(dateKey, days) {
  const date = parseLocalDate(dateKey)
  date.setDate(date.getDate() + days)
  return formatDateKey(date)
}

/** e.g. '2026-07-03' -> "Viernes 3 de julio" */
export function formatDayHeading(dateKey) {
  const date = parseLocalDate(dateKey)
  const weekday = new Intl.DateTimeFormat('es', { weekday: 'long' }).format(date)
  const month = new Intl.DateTimeFormat('es', { month: 'long' }).format(date)
  return `${capitalize(weekday)} ${date.getDate()} de ${month}`
}

/** e.g. (2026, 6) -> "Julio 2026" (monthIndex is 0-based, like Date#getMonth). */
export function formatMonthYearHeading(year, monthIndex) {
  const date = new Date(year, monthIndex, 1)
  const month = new Intl.DateTimeFormat('es', { month: 'long' }).format(date)
  return `${capitalize(month)} ${year}`
}

/** Monday-first single-letter weekday header labels for the calendar grid. */
export const WEEKDAY_HEADER_LABELS = ['L', 'M', 'X', 'J', 'V', 'S', 'D']

/**
 * Returns "today" as a 'YYYY-MM-DD' key observed in the BUSINESS timezone
 * (`BUSINESS_TIMEZONE`), regardless of the device/browser's own timezone.
 *
 * This is the single entry point for deriving "today" anywhere it crosses
 * the booking API boundary — the backend anchors its availability window to
 * the same business timezone, so a naive `new Date()` read in browser-local
 * time would disagree with the server for any user outside that zone.
 */
export function getBusinessToday() {
  const formatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: BUSINESS_TIMEZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  })
  // Built from `formatToParts()` rather than trusting `format()`'s joined
  // string — this value feeds parseLocalDate, lexicographic date-key
  // comparisons, and outgoing API query params, so a malformed separator or
  // field order (some reduced-ICU/older engines have emitted non-dash
  // shapes for 'en-CA') would silently corrupt the entire booking window.
  // Reading each field by its `type` tag removes the assumption about the
  // joined format entirely, instead of merely checking it after the fact.
  const parts = Object.fromEntries(
    formatter.formatToParts(new Date()).map((part) => [part.type, part.value]),
  )
  return `${parts.year}-${parts.month}-${parts.day}`
}
