<script setup>
import { computed } from 'vue'
import BaseBadge from '../ui/BaseBadge.vue'
import { formatCurrency } from '../../utils/money.js'

const props = defineProps({
  order: { type: Object, required: true },
})

// Dispatch on the authoritative order.type (course | appointment | product_cart).
// Fall back to relation sniffing for any legacy payload that predates the type
// discriminator, and never assume a relation object is present.
const variant = computed(() => {
  if (props.order.type) return props.order.type
  if (props.order.appointment) return 'appointment'
  if (props.order.course) return 'course'
  return 'product_cart'
})

const statusConfig = {
  // Order statuses
  paid:     { label: 'Pagado',    variant: 'accent' },
  pending:  { label: 'Pendiente', variant: 'secondary' },
  canceled: { label: 'Cancelado', variant: 'blush' },
  // Appointment statuses
  confirmed: { label: 'Confirmado', variant: 'accent' },
  cancelled: { label: 'Cancelado',  variant: 'blush' },
}

// Appointment status (when the API serializes it) wins, otherwise the order status.
const statusKey = computed(() =>
  variant.value === 'appointment'
    ? (props.order.appointment?.status ?? props.order.status)
    : props.order.status
)
const status = computed(() => statusConfig[statusKey.value])

// Appointment service title: the real API serializes `service_title` (flat);
// tolerate the nested `service.title` shape too.
const serviceTitle = computed(() =>
  props.order.appointment?.service_title ?? props.order.appointment?.service?.title
)

// Product cart summary — uses the persisted snapshot fields, no product join.
const items = computed(() => props.order.items ?? [])
const productLabel = computed(() =>
  items.value.length === 1
    ? items.value[0].product_title
    : `${items.value.length} productos`
)
const productSubtitle = computed(() => items.value.map((i) => i.product_title).join(', '))

function formatDate(dateStr) {
  if (!dateStr) return ''
  try {
    return new Intl.DateTimeFormat('es', { dateStyle: 'medium' }).format(new Date(dateStr + 'T00:00:00'))
  } catch {
    return dateStr
  }
}

// Course variant date — keep original logic
const dateStr = computed(() => props.order.paid_at ?? props.order.created_at)
</script>

<template>
  <div class="flex items-center gap-4 bg-surface rounded-2xl border border-blush-canvas/30 shadow-sm shadow-primary/5 p-4">

    <!-- ── Appointment variant ──────────────────────────────────────────── -->
    <template v-if="variant === 'appointment'">
      <!-- Icon placeholder (no thumbnail for appointments) -->
      <div class="w-20 h-14 rounded-lg overflow-hidden bg-surface-container flex-shrink-0 flex items-center justify-center">
        <span class="material-symbols-outlined text-outline text-2xl" aria-hidden="true">
          calendar_month
        </span>
      </div>

      <!-- Appointment info -->
      <div class="flex-1 min-w-0">
        <p class="font-title-sm text-title-sm text-on-surface truncate">
          {{ serviceTitle }}
        </p>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5 flex items-center gap-1">
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">calendar_today</span>
          {{ formatDate(order.appointment?.scheduled_date) }}
          <span class="mx-1 text-outline">·</span>
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">schedule</span>
          {{ order.appointment?.scheduled_time }}
        </p>
      </div>

      <!-- Deposit amount + status -->
      <div class="flex flex-col items-end gap-1 flex-shrink-0">
        <span class="font-title-sm text-title-sm text-on-surface">
          {{ formatCurrency(order.appointment?.deposit_amount_cents, order.currency) }}
        </span>
        <BaseBadge :variant="status?.variant || 'blush'" pill>
          {{ status?.label || statusKey }}
        </BaseBadge>
      </div>
    </template>

    <!-- ── Product cart variant ─────────────────────────────────────────── -->
    <template v-else-if="variant === 'product_cart'">
      <!-- Icon placeholder (no single thumbnail for a multi-item cart) -->
      <div class="w-20 h-14 rounded-lg overflow-hidden bg-surface-container flex-shrink-0 flex items-center justify-center">
        <span class="material-symbols-outlined text-outline text-2xl" aria-hidden="true">
          shopping_bag
        </span>
      </div>

      <!-- Product info -->
      <div class="flex-1 min-w-0">
        <p class="font-title-sm text-title-sm text-on-surface truncate">{{ productLabel }}</p>
        <p v-if="items.length > 1" class="font-body-sm text-body-sm text-on-surface-variant mt-0.5 truncate">
          {{ productSubtitle }}
        </p>
        <p v-else class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
          {{ formatDate(dateStr) }}
        </p>
      </div>

      <!-- Amount + status -->
      <div class="flex flex-col items-end gap-1 flex-shrink-0">
        <span class="font-title-sm text-title-sm text-on-surface">
          {{ formatCurrency(order.amount_cents, order.currency) }}
        </span>
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

    <!-- ── Course variant (default) ─────────────────────────────────────── -->
    <template v-else>
      <!-- Thumbnail -->
      <div class="w-20 h-14 rounded-lg overflow-hidden bg-surface-container flex-shrink-0 flex items-center justify-center">
        <img
          v-if="order.course?.thumbnail"
          :src="order.course.thumbnail"
          :alt="order.course?.title"
          class="w-full h-full object-cover"
        />
        <span v-else class="material-symbols-outlined text-outline text-2xl" aria-hidden="true">
          school
        </span>
      </div>

      <!-- Course info -->
      <div class="flex-1 min-w-0">
        <p class="font-title-sm text-title-sm text-on-surface truncate">{{ order.course?.title }}</p>
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
