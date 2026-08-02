<script setup>
import api from '../../../services/api.js'
import { formatCurrency } from '../../../utils/money.js'

// Sales ledger (spec `admin-reporting` "Sales ledger" + `report-export`).
// Presentational for its rows/pagination — the parent (`AdminReports.vue`)
// owns the fetch, same container/presentational split as the rest of this
// screen. Export MUST go through the shared axios instance as a blob
// (design D7): a bare `<a href>` would not carry the Sanctum bearer token
// and would 401.
const props = defineProps({
  rows: { type: Array, default: () => [] },
  meta: { type: [Object, null], default: null },
  streamFilter: { type: String, default: '' },
  from: { type: String, required: true },
  to: { type: String, required: true },
})

const emit = defineEmits(['update:page', 'update:stream'])

const STREAM_OPTIONS = [
  { value: '', label: 'Todos los orígenes' },
  { value: 'course_sale', label: 'Venta de curso' },
  { value: 'product_sale', label: 'Venta de producto' },
  { value: 'appointment_deposit', label: 'Anticipo de cita' },
  { value: 'appointment_deposit_retained', label: 'Anticipo retenido' },
  { value: 'appointment_settlement', label: 'Liquidación de cita' },
]

function onStreamChange(event) {
  emit('update:stream', event.target.value)
}

async function exportCsv() {
  const params = { from: props.from, to: props.to }
  if (props.streamFilter) params.stream = [props.streamFilter]

  const response = await api.get('/admin/reports/ledger/export', { params, responseType: 'blob' })
  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = url
  link.download = `ledger-${props.from}-${props.to}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

function printLedger() {
  window.print()
}
</script>

<template>
  <div id="ledger-print-area" class="bg-surface rounded-2xl border border-blush-canvas/20 p-5">
    <div class="no-print flex flex-wrap items-center justify-between gap-3 mb-4">
      <h2 class="font-title-md text-title-md text-on-surface">Libro de ventas</h2>
      <div class="flex items-center gap-3">
        <select
          data-ledger-stream-filter
          :value="streamFilter"
          @change="onStreamChange"
          class="rounded-xl border border-blush-canvas/30 bg-surface px-3 py-2 font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
        >
          <option v-for="opt in STREAM_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
        <button
          data-ledger-export
          type="button"
          class="px-4 py-2 rounded-xl border border-primary text-primary font-label-md text-label-md hover:bg-primary hover:text-on-primary transition-colors"
          @click="exportCsv"
        >
          Exportar CSV
        </button>
        <button
          data-ledger-print
          type="button"
          class="px-4 py-2 rounded-xl border border-blush-canvas/30 font-label-md text-label-md hover:bg-surface-container-low transition-colors"
          @click="printLedger"
        >
          Imprimir
        </button>
      </div>
    </div>

    <div v-if="!rows.length" class="text-center py-10 font-body-md text-body-md text-on-surface-variant">
      No hay movimientos en este período
    </div>

    <table v-else class="w-full text-left">
      <thead>
        <tr class="font-label-sm text-label-sm text-on-surface-variant border-b border-blush-canvas/20">
          <th class="py-2 pr-3">Fecha</th>
          <th class="py-2 pr-3">Origen</th>
          <th class="py-2 pr-3">Cliente</th>
          <th class="py-2 text-right">Monto</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(row, i) in rows"
          :key="i"
          data-ledger-row
          class="font-body-sm text-body-sm text-on-surface border-b border-blush-canvas/10"
        >
          <td class="py-2 pr-3">{{ row.occurred_at?.slice(0, 10) }}</td>
          <td class="py-2 pr-3">{{ row.label }}</td>
          <td class="py-2 pr-3">{{ row.counterparty ?? '—' }}</td>
          <td class="py-2 text-right">{{ formatCurrency(row.amount_cents) }}</td>
        </tr>
      </tbody>
    </table>

    <div
      v-if="meta && meta.last_page > 1"
      class="no-print flex items-center justify-center gap-4 mt-6"
    >
      <button
        data-ledger-page-prev
        type="button"
        :disabled="meta.current_page <= 1 || undefined"
        class="flex items-center gap-1 px-4 py-2 rounded-xl border border-blush-canvas/30 font-label-md text-label-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed hover:enabled:bg-surface-container-low"
        @click="emit('update:page', meta.current_page - 1)"
      >
        Anterior
      </button>
      <span class="font-body-md text-body-md text-on-surface-variant">
        Página {{ meta.current_page }} de {{ meta.last_page }}
      </span>
      <button
        data-ledger-page-next
        type="button"
        :disabled="meta.current_page >= meta.last_page || undefined"
        class="flex items-center gap-1 px-4 py-2 rounded-xl border border-blush-canvas/30 font-label-md text-label-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed hover:enabled:bg-surface-container-low"
        @click="emit('update:page', meta.current_page + 1)"
      >
        Siguiente
      </button>
    </div>
  </div>
</template>
