import { describe, it, expect } from 'vitest'
import { granularityLabel, degradationMessage, defaultReportDateRange } from '../utils/reportsFormat.js'

describe('granularityLabel', () => {
  it('maps day/week/month to Spanish labels', () => {
    expect(granularityLabel('day')).toBe('día')
    expect(granularityLabel('week')).toBe('semana')
    expect(granularityLabel('month')).toBe('mes')
  })

  it('falls back to the raw key for an unknown granularity', () => {
    expect(granularityLabel('quarter')).toBe('quarter')
  })
})

describe('degradationMessage', () => {
  it('builds a Spanish sentence naming both granularities when they differ', () => {
    const message = degradationMessage('day', 'week')
    expect(message).toContain('semana')
    expect(message).toContain('día')
  })

  it('returns null when requested and effective granularity are the same', () => {
    expect(degradationMessage('week', 'week')).toBeNull()
  })
})

describe('defaultReportDateRange', () => {
  it('returns a 30-day inclusive window ending on the given date', () => {
    const range = defaultReportDateRange('2026-08-15')
    expect(range).toEqual({ from: '2026-07-17', to: '2026-08-15' })
  })

  it('crosses a year boundary correctly', () => {
    const range = defaultReportDateRange('2026-01-10')
    expect(range).toEqual({ from: '2025-12-12', to: '2026-01-10' })
  })
})
