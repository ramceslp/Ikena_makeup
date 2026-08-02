<script setup>
import { computed } from 'vue'
import { computeTimelineLayout } from '../../../utils/timelineChartLayout.js'
import { granularityLabel } from '../../../utils/reportsFormat.js'
import { formatCurrency } from '../../../utils/money.js'

// Hand-rolled SVG bar chart for the revenue timeline (design D7 — no chart
// library in the repo). Visual language matches
// `components/instructor/SalesChart.vue`, but this is a SEPARATE component
// rather than a generalisation of it: SalesChart is hardcoded to a fixed
// 6-point series and InstructorDashboard.vue depends on its exact rendering.
// The timeline can hold up to 92 points (PeriodCalendar's day-granularity
// cap), so it needs its own N-point layout — see
// `utils/timelineChartLayout.js`.
const props = defineProps({
  // Array<{ label: string, total_cents: number }> — already zero-filled and
  // ordered oldest → newest by the API.
  periods: { type: Array, required: true },
  // The EFFECTIVE granularity the API actually returned (never the
  // requested one — adjustment #1: rendering daily labels over weekly
  // buckets is exactly the failure this prop exists to prevent).
  effectiveGranularity: { type: String, required: true },
})

const layout = computed(() => computeTimelineLayout(props.periods))

const ariaLabel = computed(() => {
  const total = props.periods.reduce((sum, p) => sum + p.total_cents, 0)
  return `Gráfico de ingresos por ${granularityLabel(props.effectiveGranularity)}. Total en el rango: ${formatCurrency(total)}.`
})
</script>

<template>
  <div class="w-full overflow-x-auto">
    <p v-if="periods.length === 0" class="font-body-md text-body-md text-on-surface-variant py-8 text-center">
      Sin datos para el rango seleccionado
    </p>

    <svg
      v-else
      :viewBox="`0 0 ${layout.chartWidth} ${layout.chartHeight}`"
      role="img"
      :aria-label="ariaLabel"
      class="w-full"
      :style="{ minWidth: `${Math.min(layout.chartWidth, 900)}px` }"
    >
      <line
        :x1="20"
        :y1="layout.barAreaHeight"
        :x2="layout.chartWidth - 20"
        :y2="layout.barAreaHeight"
        stroke="var(--color-outline-variant)"
        stroke-width="1"
      />

      <g v-for="(bar, i) in layout.bars" :key="i" role="graphics-symbol" aria-roledescription="barra">
        <title>{{ bar.label }}: {{ formatCurrency(bar.totalCents) }}</title>

        <rect
          :x="bar.x"
          :y="bar.height > 0 ? bar.y : layout.barAreaHeight - 2"
          :width="bar.width"
          :height="bar.height > 0 ? bar.height : 2"
          :fill="bar.height > 0 ? 'var(--color-apricot-glow)' : 'var(--color-outline-variant)'"
          rx="2"
          ry="2"
        />

        <text
          v-if="bar.showLabel"
          :x="bar.labelX"
          :y="layout.barAreaHeight + 18"
          text-anchor="middle"
          class="font-label-sm"
          font-size="9"
          fill="var(--color-on-surface-variant)"
        >{{ bar.label }}</text>
      </g>
    </svg>
  </div>
</template>
