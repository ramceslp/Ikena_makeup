<script setup>
import { computed } from 'vue'

// Presentational component — hand-rolled SVG bar chart for monthly revenue.
// Props:
//   data — Array<{ period: 'YYYY-MM', revenue_cents: number, sales: number }>
//           Always 6 entries, oldest → newest, zero-filled.
const props = defineProps({
  data: {
    type: Array,
    required: true,
  },
})

// Spanish month abbreviations (index 1–12)
const MONTH_NAMES = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic']

// SVG layout constants
const CHART_WIDTH = 480
const CHART_HEIGHT = 200
const BAR_AREA_HEIGHT = 160   // height available for bars
const BOTTOM_MARGIN = 40       // space for x-axis labels
const SIDE_MARGIN = 20
const BAR_GROUP_WIDTH = (CHART_WIDTH - SIDE_MARGIN * 2) / 6  // width per month group
const BAR_WIDTH = BAR_GROUP_WIDTH * 0.55
const BAR_GAP = (BAR_GROUP_WIDTH - BAR_WIDTH) / 2

const bars = computed(() => {
  const revenues = props.data.map((d) => d.revenue_cents)
  const maxRevenue = Math.max(...revenues)

  // Detect if the window spans two calendar years
  const years = new Set(props.data.map((d) => d.period.slice(0, 4)))
  const multiYear = years.size > 1

  return props.data.map((d, i) => {
    const [year, monthStr] = d.period.split('-')
    const month = parseInt(monthStr, 10)
    const label = multiYear ? `${MONTH_NAMES[month]} ${year.slice(2)}` : MONTH_NAMES[month]

    // Bar height: scale to BAR_AREA_HEIGHT, guard divide-by-zero
    const heightRatio = maxRevenue > 0 ? d.revenue_cents / maxRevenue : 0
    const barHeight = Math.max(heightRatio * BAR_AREA_HEIGHT, 0)

    const x = SIDE_MARGIN + i * BAR_GROUP_WIDTH + BAR_GAP
    const y = BAR_AREA_HEIGHT - barHeight  // bars grow upward from baseline

    const revenueFormatted = new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(d.revenue_cents / 100)

    return {
      x,
      y,
      width: BAR_WIDTH,
      height: barHeight,
      label,
      revenue: revenueFormatted,
      sales: d.sales,
      // x center for x-axis label
      labelX: SIDE_MARGIN + i * BAR_GROUP_WIDTH + BAR_GROUP_WIDTH / 2,
    }
  })
})

// Build aria-label summarizing the series
const ariaLabel = computed(() => {
  const total = props.data.reduce((sum, d) => sum + d.revenue_cents, 0)
  const totalRevenue = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(total / 100)
  const firstPeriod = props.data[0]?.period ?? ''
  const lastPeriod = props.data[props.data.length - 1]?.period ?? ''
  return `Gráfico de barras: ventas mensuales de ${firstPeriod} a ${lastPeriod}. Ingresos totales en el período: ${totalRevenue}.`
})
</script>

<template>
  <div class="w-full overflow-x-auto">
    <svg
      :viewBox="`0 0 ${CHART_WIDTH} ${CHART_HEIGHT}`"
      role="img"
      :aria-label="ariaLabel"
      class="w-full"
      style="min-width: 280px"
    >
      <!-- Baseline -->
      <line
        :x1="SIDE_MARGIN"
        :y1="BAR_AREA_HEIGHT"
        :x2="CHART_WIDTH - SIDE_MARGIN"
        :y2="BAR_AREA_HEIGHT"
        stroke="var(--color-outline-variant)"
        stroke-width="1"
      />

      <!-- Bars -->
      <g
        v-for="(bar, i) in bars"
        :key="i"
        role="graphics-symbol"
        aria-roledescription="barra"
      >
        <!-- Accessible title per bar -->
        <title>{{ bar.label }}: {{ bar.revenue }} ({{ bar.sales }} venta{{ bar.sales !== 1 ? 's' : '' }})</title>

        <!-- Bar rect — apricot-glow for positive values, muted for zero -->
        <rect
          :x="bar.x"
          :y="bar.height > 0 ? bar.y : BAR_AREA_HEIGHT - 2"
          :width="bar.width"
          :height="bar.height > 0 ? bar.height : 2"
          :fill="bar.height > 0 ? 'var(--color-apricot-glow)' : 'var(--color-outline-variant)'"
          rx="3"
          ry="3"
        />

        <!-- Sales count label above bar (only when positive) -->
        <text
          v-if="bar.sales > 0"
          :x="bar.labelX"
          :y="bar.y - 4"
          text-anchor="middle"
          class="font-label-sm"
          font-size="10"
          fill="var(--color-on-surface-variant)"
        >{{ bar.sales }}</text>

        <!-- X-axis month label -->
        <text
          :x="bar.labelX"
          :y="BAR_AREA_HEIGHT + 18"
          text-anchor="middle"
          class="font-label-sm"
          font-size="10"
          fill="var(--color-on-surface-variant)"
        >{{ bar.label }}</text>
      </g>
    </svg>
  </div>
</template>
