<script setup>
import { computed } from 'vue'
import { formatCurrency } from '../../../utils/money.js'

// Composition-by-type breakdown (spec `admin-reporting`, "Composition by
// order type"). Plain proportional bars (div width %), not SVG — three-to-
// four categories don't need chart geometry.
//
// Retained deposits are rendered as their OWN row, never folded into
// "service" — the spec is explicit that composition's total legitimately
// disagrees with the summary's confirmed revenue because it includes
// retained deposits (spec "Retained deposits on cancellation"); merging the
// two here would be the exact confusion that requirement exists to avoid.
const props = defineProps({
  byType: { type: Object, required: true }, // { course, product, service } — cents
  retainedDepositsCents: { type: Number, required: true },
  totalCents: { type: Number, required: true },
})

const TYPE_LABELS = { course: 'Cursos', product: 'Productos', service: 'Servicios' }

const rows = computed(() => {
  const typeRows = Object.entries(TYPE_LABELS).map(([key, label]) => ({
    key,
    label,
    cents: props.byType[key] ?? 0,
  }))

  return [
    ...typeRows,
    { key: 'retained', label: 'Depósitos retenidos (cancelaciones)', cents: props.retainedDepositsCents },
  ]
})

function widthPercent(cents) {
  if (props.totalCents <= 0) return 0
  return Math.min(100, (cents / props.totalCents) * 100)
}
</script>

<template>
  <div class="bg-surface rounded-2xl border border-blush-canvas/20 p-5">
    <div class="space-y-3">
      <div
        v-for="row in rows"
        :key="row.key"
        :data-composition-retained="row.key === 'retained' ? true : null"
        class="flex flex-col gap-1"
      >
        <div class="flex justify-between font-body-sm text-body-sm text-on-surface">
          <span>{{ row.label }}</span>
          <span>{{ formatCurrency(row.cents) }}</span>
        </div>
        <div class="h-2 rounded-full bg-surface-container-low overflow-hidden">
          <div
            data-composition-bar
            class="h-full rounded-full"
            :class="row.key === 'retained' ? 'bg-outline-variant' : 'bg-apricot-glow'"
            :style="{ width: `${widthPercent(row.cents)}%` }"
          />
        </div>
      </div>
    </div>

    <p class="font-label-sm text-label-sm text-on-surface-variant mt-4">
      Total del período: {{ formatCurrency(totalCents) }}. Esta cifra incluye los depósitos retenidos por
      cancelación, por lo que no coincide con "Ingresos confirmados" del resumen (que los excluye
      deliberadamente).
    </p>
  </div>
</template>
