<script setup>
import { computed } from 'vue'

// Order-status and appointment-status funnels (spec `admin-reporting`'s
// "Order-status funnel" requirement plus its appointment sibling, [Slice
// 4]). The backend already zero-fills every known status
// (`FunnelQuery::countByStatus`), so this component only needs to render
// whatever object it receives — plain proportional bars, not SVG geometry,
// same reasoning `CompositionBars.vue` uses: 3-4 categories don't need
// chart geometry.
const props = defineProps({
  orders: { type: Object, default: () => ({}) },
  appointments: { type: Object, default: () => ({}) },
})

const ORDER_STATUS_LABELS = { pending: 'Pendiente', paid: 'Pagada', failed: 'Fallida', canceled: 'Cancelada' }
const APPOINTMENT_STATUS_LABELS = { pending: 'Pendiente', confirmed: 'Confirmada', paid: 'Pagada', cancelled: 'Cancelada' }

function buildBars(counts, labels) {
  const entries = Object.entries(counts)
  const max = Math.max(0, ...entries.map(([, count]) => count))

  return entries.map(([status, count]) => ({
    status,
    label: labels[status] ?? status,
    count,
    widthPercent: max > 0 ? Math.round((count / max) * 100) : 0,
  }))
}

const orderBars = computed(() => buildBars(props.orders, ORDER_STATUS_LABELS))
const appointmentBars = computed(() => buildBars(props.appointments, APPOINTMENT_STATUS_LABELS))
</script>

<template>
  <div class="bg-surface rounded-2xl border border-blush-canvas/20 p-5">
    <h2 class="font-title-md text-title-md text-on-surface mb-4">Embudo de estados</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
      <div>
        <h3 class="font-label-md text-label-md text-on-surface-variant mb-3">Órdenes</h3>
        <p v-if="!orderBars.length" class="font-body-sm text-body-sm text-on-surface-variant">Sin datos</p>
        <div v-else class="space-y-3">
          <div v-for="bar in orderBars" :key="bar.status" data-funnel-order-bar class="flex flex-col gap-1">
            <div class="flex justify-between font-body-sm text-body-sm text-on-surface">
              <span>{{ bar.label }}</span>
              <span>{{ bar.count }}</span>
            </div>
            <div class="h-2 rounded-full bg-surface-container-low overflow-hidden">
              <div data-funnel-bar-fill class="h-full rounded-full bg-apricot-glow" :style="{ width: `${bar.widthPercent}%` }" />
            </div>
          </div>
        </div>
      </div>

      <div>
        <h3 class="font-label-md text-label-md text-on-surface-variant mb-3">Citas</h3>
        <p v-if="!appointmentBars.length" class="font-body-sm text-body-sm text-on-surface-variant">Sin datos</p>
        <div v-else class="space-y-3">
          <div v-for="bar in appointmentBars" :key="bar.status" data-funnel-appointment-bar class="flex flex-col gap-1">
            <div class="flex justify-between font-body-sm text-body-sm text-on-surface">
              <span>{{ bar.label }}</span>
              <span>{{ bar.count }}</span>
            </div>
            <div class="h-2 rounded-full bg-surface-container-low overflow-hidden">
              <div data-funnel-bar-fill class="h-full rounded-full bg-apricot-glow" :style="{ width: `${bar.widthPercent}%` }" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
