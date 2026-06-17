<script setup>
import { computed } from 'vue'
import BaseBadge from '../ui/BaseBadge.vue'
import { formatCurrency } from '../../utils/money.js'

const props = defineProps({
  order: { type: Object, required: true },
})

const isAppointment = computed(() => !!(props.order.appointment && !props.order.course))

const statusConfig = {
  // Course order statuses
  paid:     { label: 'Pagado',    variant: 'accent' },
  pending:  { label: 'Pendiente', variant: 'secondary' },
  canceled: { label: 'Cancelado', variant: 'blush' },
  // Appointment statuses
  confirmed: { label: 'Confirmado', variant: 'accent' },
  cancelled: { label: 'Cancelado',  variant: 'blush' },
}

// For appointment rows the status comes from appointment.status, otherwise order.status
const statusKey = computed(() =>
  isAppointment.value
    ? (props.order.appointment.status ?? props.order.status)
    : props.order.status
)

const status = computed(() => statusConfig[statusKey.value])

function formatDate(dateStr) {
  if (!dateStr) return ''
  try {
    return new Intl.DateTimeFormat('es', { dateStyle: 'medium' }).format(new Date(dateStr + 'T00:00:00'))
  } catch {
    return dateStr
  }
}

// Course variant — keep original logic
const dateStr = computed(() => props.order.paid_at ?? props.order.created_at)
</script>

<template>
  <div class="flex items-center gap-4 bg-surface rounded-2xl border border-blush-canvas/30 p-4">

    <!-- ── Appointment variant ──────────────────────────────────────────── -->
    <template v-if="isAppointment">
      <!-- Icon placeholder (no thumbnail for appointments) -->
      <div class="w-20 h-14 rounded-lg overflow-hidden bg-surface-container flex-shrink-0 flex items-center justify-center">
        <span class="material-symbols-outlined text-outline text-2xl" aria-hidden="true">
          calendar_month
        </span>
      </div>

      <!-- Appointment info -->
      <div class="flex-1 min-w-0">
        <p class="font-title-sm text-title-sm text-on-surface truncate">
          {{ order.appointment.service?.title }}
        </p>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5 flex items-center gap-1">
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">calendar_today</span>
          {{ formatDate(order.appointment.scheduled_date) }}
          <span class="mx-1 text-outline">·</span>
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">schedule</span>
          {{ order.appointment.scheduled_time }}
        </p>
      </div>

      <!-- Deposit amount + status -->
      <div class="flex flex-col items-end gap-1 flex-shrink-0">
        <span class="font-title-sm text-title-sm text-on-surface">
          {{ formatCurrency(order.appointment.deposit_amount_cents, order.currency) }}
        </span>
        <BaseBadge :variant="status?.variant || 'blush'" pill>
          {{ status?.label || statusKey }}
        </BaseBadge>
      </div>
    </template>

    <!-- ── Course variant (original, unchanged) ────────────────────────── -->
    <template v-else>
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
    </template>

  </div>
</template>
