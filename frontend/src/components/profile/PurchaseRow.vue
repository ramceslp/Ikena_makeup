<script setup>
import BaseBadge from '../ui/BaseBadge.vue'
import { formatCurrency } from '../../utils/money.js'

const props = defineProps({
  order: { type: Object, required: true },
})

const statusConfig = {
  paid:     { label: 'Pagado',    variant: 'accent' },
  pending:  { label: 'Pendiente', variant: 'secondary' },
  canceled: { label: 'Cancelado', variant: 'blush' },
}

function formatDate(dateStr) {
  return new Intl.DateTimeFormat('es', { dateStyle: 'medium' }).format(new Date(dateStr))
}

const status = statusConfig[props.order.status]
const dateStr = props.order.paid_at ?? props.order.created_at
</script>

<template>
  <div class="flex items-center gap-4 bg-surface rounded-2xl border border-blush-canvas/30 p-4">
    <!-- Thumbnail -->
    <div class="w-20 h-14 rounded-lg overflow-hidden bg-surface-container flex-shrink-0 flex items-center justify-center">
      <img
        v-if="order.course.thumbnail"
        :src="order.course.thumbnail"
        :alt="order.course.title"
        class="w-full h-full object-cover"
      />
      <span v-else class="material-symbols-outlined text-outline text-2xl" aria-hidden="true">
        school
      </span>
    </div>

    <!-- Course info -->
    <div class="flex-1 min-w-0">
      <p class="font-title-sm text-title-sm text-on-surface truncate">{{ order.course.title }}</p>
      <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">{{ formatDate(dateStr) }}</p>
    </div>

    <!-- Amount + status -->
    <div class="flex flex-col items-end gap-1 flex-shrink-0">
      <span class="font-title-sm text-title-sm text-on-surface">
        {{ formatCurrency(order.amount_cents, order.currency) }}
      </span>
      <!-- Failed uses error-container tokens (BaseBadge has no error variant) -->
      <span
        v-if="order.status === 'failed'"
        class="inline-flex items-center px-2 py-0.5 rounded font-label-sm text-label-sm bg-error-container text-on-error-container"
      >
        Fallido
      </span>
      <BaseBadge v-else :variant="status?.variant || 'blush'" pill>
        {{ status?.label || order.status }}
      </BaseBadge>
    </div>
  </div>
</template>
