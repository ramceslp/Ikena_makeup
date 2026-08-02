/**
 * Pure SVG layout math for `RevenueTimelineChart.vue`. Extracted so the
 * geometry (bar width/height/label thinning) can be unit-tested at the
 * boundary cases the spec's timeline requirement forces on the chart: N=1,
 * N=92 (the day-granularity cap, `PeriodCalendar::DAY_CAP`), and an all-zero
 * series (nothing paid in the range — must render, not divide by zero).
 *
 * Deliberately a SEPARATE module from `SalesChart.vue` rather than a
 * generalisation of it: `SalesChart.vue` is hardcoded to exactly 6 points
 * and `InstructorDashboard.vue` depends on its current rendering. Widening
 * it to N points risks that existing, unrelated dashboard — a second,
 * purpose-built layout for up to 92 points is the lower-risk path.
 */

const BAR_AREA_HEIGHT = 160 // px, matches SalesChart.vue's plot area
const SIDE_MARGIN = 20
const MIN_BAR_GROUP_WIDTH = 10 // px — below this, bars stop being distinguishable
const MAX_VISIBLE_LABELS = 12 // caps x-axis label collisions at N=92

/**
 * @param {Array<{label: string, total_cents: number}>} periods
 * @returns {{
 *   chartWidth: number,
 *   chartHeight: number,
 *   barAreaHeight: number,
 *   bars: Array<{x: number, y: number, width: number, height: number, label: string, totalCents: number, labelX: number, showLabel: boolean}>,
 * }}
 */
export function computeTimelineLayout(periods) {
  const n = periods.length
  const barAreaHeight = BAR_AREA_HEIGHT
  const chartHeight = barAreaHeight + 40 // + room for x-axis labels

  if (n === 0) {
    return { chartWidth: 480, chartHeight, barAreaHeight, bars: [] }
  }

  const barGroupWidth = Math.max(MIN_BAR_GROUP_WIDTH, 480 / n)
  const chartWidth = Math.max(480, barGroupWidth * n + SIDE_MARGIN * 2)
  const barWidth = barGroupWidth * 0.6
  const barGap = (barGroupWidth - barWidth) / 2

  const maxCents = Math.max(...periods.map((p) => p.total_cents))
  const labelStride = Math.max(1, Math.ceil(n / MAX_VISIBLE_LABELS))

  const bars = periods.map((period, i) => {
    const ratio = maxCents > 0 ? period.total_cents / maxCents : 0
    const height = ratio * barAreaHeight
    const x = SIDE_MARGIN + i * barGroupWidth + barGap
    const y = barAreaHeight - height
    const isEdge = i === 0 || i === n - 1
    const showLabel = isEdge || i % labelStride === 0

    return {
      x,
      y,
      width: barWidth,
      height,
      label: period.label,
      totalCents: period.total_cents,
      labelX: SIDE_MARGIN + i * barGroupWidth + barGroupWidth / 2,
      showLabel,
    }
  })

  return { chartWidth, chartHeight, barAreaHeight, bars }
}
