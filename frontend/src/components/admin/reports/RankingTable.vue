<script setup>
import { ref, computed } from 'vue'
import { formatCurrency } from '../../../utils/money.js'
import { costCoveragePercent, isFullCostCoverage } from '../../../utils/reportsFormat.js'

// Rankings by margin (products), revenue-per-hour (services), and paid
// enrollment revenue (courses) — spec `admin-reporting`'s three ranking
// requirements ([Slice 4]). All three arrays are fetched together (they
// share the range-scoped watcher in AdminReports.vue's loadAggregates), so
// this component owns only WHICH one is on screen. Mirrors LedgerTable.vue's
// empty-state/table markup convention.
//
// Products carry a cost-coverage indicator next to margin: `unit_cost_cents`
// is manual data entry defaulting to 0, indistinguishable at the DB level
// from a genuinely free item, so a margin figure shown alone would silently
// overstate margin whenever cost data is incomplete.
const props = defineProps({
  products: { type: Array, default: () => [] },
  services: { type: Array, default: () => [] },
  courses: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:active-type'])

const TABS = [
  { type: 'products', label: 'Productos' },
  { type: 'services', label: 'Servicios' },
  { type: 'courses', label: 'Cursos' },
]

const COLUMN_LABELS = {
  products: { entity: 'Producto', second: 'Cantidad' },
  services: { entity: 'Servicio', second: 'Duración (h)' },
  courses: { entity: 'Curso', second: 'Inscripciones pagas' },
}

const activeType = ref('products')

function selectTab(type) {
  activeType.value = type
  emit('update:active-type', type)
}

const activeRows = computed(() => props[activeType.value])
const columns = computed(() => COLUMN_LABELS[activeType.value])

function rowKey(row) {
  return row.product_id ?? row.service_id ?? row.course_id
}

function secondColumnValue(row) {
  if (activeType.value === 'products') return row.quantity
  if (activeType.value === 'services') return row.duration_hours
  return row.paid_enrollment_count
}
</script>

<template>
  <div class="bg-surface rounded-2xl border border-blush-canvas/20 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <h2 class="font-title-md text-title-md text-on-surface">Rankings</h2>
      <div class="flex gap-2">
        <button
          v-for="tab in TABS"
          :key="tab.type"
          type="button"
          :data-ranking-tab="tab.type"
          :class="[
            'px-3 py-1.5 rounded-xl font-label-sm text-label-sm transition-colors',
            activeType === tab.type
              ? 'bg-primary text-on-primary'
              : 'border border-blush-canvas/30 text-on-surface hover:bg-surface-container-low',
          ]"
          @click="selectTab(tab.type)"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <div v-if="!activeRows.length" class="text-center py-10 font-body-md text-body-md text-on-surface-variant">
      No hay datos para el rango seleccionado
    </div>

    <table v-else class="w-full text-left">
      <thead>
        <tr class="font-label-sm text-label-sm text-on-surface-variant border-b border-blush-canvas/20">
          <th class="py-2 pr-3">{{ columns.entity }}</th>
          <th class="py-2 pr-3 text-right">{{ columns.second }}</th>
          <th class="py-2 pr-3 text-right">Ingresos</th>
          <th v-if="activeType === 'products'" class="py-2 pr-3 text-right">Margen</th>
          <th v-if="activeType === 'services'" class="py-2 pr-3 text-right">Ingreso/hora</th>
          <th v-if="activeType === 'courses'" class="py-2 text-right">Inscripciones gratis</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="row in activeRows"
          :key="rowKey(row)"
          data-ranking-row
          class="font-body-sm text-body-sm text-on-surface border-b border-blush-canvas/10"
        >
          <td class="py-2 pr-3">{{ row.title }}</td>
          <td class="py-2 pr-3 text-right">{{ secondColumnValue(row) }}</td>
          <td class="py-2 pr-3 text-right">{{ formatCurrency(row.revenue_cents) }}</td>

          <td v-if="activeType === 'products'" class="py-2 pr-3 text-right">
            {{ formatCurrency(row.margin_cents) }}
            <span
              v-if="!isFullCostCoverage(row.revenue_cents, row.known_cost_revenue_cents)"
              data-ranking-margin-coverage
              class="block font-label-sm text-label-sm text-on-surface-variant"
            >
              Cobertura de costo: {{ costCoveragePercent(row.revenue_cents, row.known_cost_revenue_cents) }}%
            </span>
          </td>
          <td v-if="activeType === 'services'" class="py-2 pr-3 text-right">
            {{ formatCurrency(row.revenue_per_hour_cents) }}
          </td>
          <td v-if="activeType === 'courses'" class="py-2 text-right">{{ row.free_enrollment_count }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
