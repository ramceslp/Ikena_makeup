import { describe, it, expect } from 'vitest'
import {
  granularityLabel,
  degradationMessage,
  defaultReportDateRange,
  costCoveragePercent,
  isFullCostCoverage,
} from '../utils/reportsFormat.js'

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

describe('costCoveragePercent', () => {
  it('computes the percentage of revenue that has a known cost basis', () => {
    expect(costCoveragePercent(50000, 30000)).toBe(60)
  })

  it('returns 100 when every unit sold has a known cost', () => {
    expect(costCoveragePercent(20000, 20000)).toBe(100)
  })

  it('returns 100 for zero revenue instead of dividing by zero', () => {
    expect(costCoveragePercent(0, 0)).toBe(100)
  })

  it('returns 0 when no line in the range has a known cost', () => {
    expect(costCoveragePercent(50000, 0)).toBe(0)
  })
})

describe('isFullCostCoverage', () => {
  it('is true when known-cost revenue matches total revenue', () => {
    expect(isFullCostCoverage(20000, 20000)).toBe(true)
  })

  it('is false when only part of the revenue has a known cost', () => {
    expect(isFullCostCoverage(50000, 30000)).toBe(false)
  })

  it('is true for zero revenue (nothing to cover)', () => {
    expect(isFullCostCoverage(0, 0)).toBe(true)
  })
})
