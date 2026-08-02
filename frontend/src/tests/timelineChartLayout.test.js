import { describe, it, expect } from 'vitest'
import { computeTimelineLayout } from '../utils/timelineChartLayout.js'

function periodsOf(n, { totalCents = () => 1000 } = {}) {
  return Array.from({ length: n }, (_, i) => ({
    label: `p${i}`,
    total_cents: totalCents(i),
  }))
}

describe('computeTimelineLayout', () => {
  it('lays out a single bar spanning the full plot width', () => {
    const layout = computeTimelineLayout(periodsOf(1))

    expect(layout.bars).toHaveLength(1)
    expect(layout.bars[0].height).toBeGreaterThan(0)
    expect(layout.bars[0].width).toBeGreaterThan(0)
    expect(layout.bars[0].width).toBeLessThanOrEqual(layout.chartWidth)
  })

  it('lays out 92 bars without collapsing width to zero or negative', () => {
    const layout = computeTimelineLayout(periodsOf(92))

    expect(layout.bars).toHaveLength(92)
    for (const bar of layout.bars) {
      expect(bar.width).toBeGreaterThan(0)
    }
    // chart grows wide enough that 92 thin bars stay visually distinct
    expect(layout.chartWidth).toBeGreaterThan(480)
  })

  it('thins x-axis labels so 92 bars do not render 92 overlapping labels', () => {
    const layout = computeTimelineLayout(periodsOf(92))
    const visibleLabels = layout.bars.filter((b) => b.showLabel)

    expect(visibleLabels.length).toBeLessThan(92)
    expect(visibleLabels.length).toBeGreaterThan(0)
    // first and last bars always keep a label so the range stays legible
    expect(layout.bars[0].showLabel).toBe(true)
    expect(layout.bars[91].showLabel).toBe(true)
  })

  it('renders a flat zero-height baseline for an all-zero series without dividing by zero', () => {
    const layout = computeTimelineLayout(periodsOf(6, { totalCents: () => 0 }))

    expect(layout.bars).toHaveLength(6)
    for (const bar of layout.bars) {
      expect(bar.height).toBe(0)
      expect(Number.isFinite(bar.y)).toBe(true)
    }
  })

  it('returns an empty bar list for an empty period array', () => {
    const layout = computeTimelineLayout([])
    expect(layout.bars).toEqual([])
  })
})
