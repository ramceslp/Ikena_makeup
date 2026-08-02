import { addDaysToDateKey } from './localDate.js'

/**
 * Presentation helpers for the admin reports dashboard. Pure functions only
 * — no DOM, no HTTP — so the degradation/label logic that the spec's
 * "granularity auto-degrades" requirement depends on can be unit-tested
 * without mounting a component.
 */

const GRANULARITY_LABELS = {
  day: 'día',
  week: 'semana',
  month: 'mes',
}

/** e.g. 'day' -> 'día'. Falls back to the raw key for an unknown value. */
export function granularityLabel(key) {
  return GRANULARITY_LABELS[key] ?? key
}

/**
 * Builds the Spanish notice the UI MUST show when the API auto-degraded the
 * granularity (design adjustment #1 — no 422, but the frontend must make the
 * substitution visible so daily labels are never rendered over weekly
 * buckets). Returns `null` when there is nothing to report.
 */
export function degradationMessage(requestedGranularity, effectiveGranularity) {
  if (requestedGranularity === effectiveGranularity) return null

  return `El rango elegido es muy amplio para mostrarse por ${granularityLabel(requestedGranularity)}; se muestra por ${granularityLabel(effectiveGranularity)}.`
}

/**
 * Default filter window: a 30-day INCLUSIVE range ending on `today`
 * ('YYYY-MM-DD'). Matches the `to` param contract the backend documents
 * (inclusive calendar date, converted to the exclusive bound internally).
 */
export function defaultReportDateRange(today) {
  return {
    from: addDaysToDateKey(today, -29),
    to: today,
  }
}
