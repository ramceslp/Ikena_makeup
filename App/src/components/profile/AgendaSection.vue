<script setup>
import { ref, computed } from 'vue'
import { useAppointmentsStore } from '../../stores/appointments.js'
import BaseBadge from '../ui/BaseBadge.vue'
import { formatCurrency } from '../../utils/money.js'

// "Mi agenda" — the customer's own appointments, split into upcoming and past.
//
// Before this, appointments were readable only through the ADMIN list: a
// customer had no way to check when their next appointment was. /profile/orders
// does include appointment ORDERS, but sorted by purchase date and mixed in
// with product and course purchases, which is not an agenda.
//
// The two scopes are separate server queries with opposite sort orders and
// independent pagination (see stores/appointments.js), so this tab switch
// picks which slice to render — it does not filter one list.
const store = useAppointmentsStore()

const scope = ref('upcoming')
const slice = computed(() => store[scope.value])

const tabs = [
  { key: 'upcoming', label: 'Próximas' },
  { key: 'past', label: 'Pasadas' },
]

const statusConfig = {
  pending: { label: 'Pago pendiente', variant: 'secondary' },
  paid: { label: 'Pagada', variant: 'accent' },
  confirmed: { label: 'Confirmada', variant: 'accent' },
  cancelled: { label: 'Cancelada', variant: 'blush' },
}

// scheduled_date is a bare 'YYYY-MM-DD'. `new Date('2026-08-01')` parses that
// as UTC midnight, which renders as the PREVIOUS day in any negative-UTC-offset
// timezone — Ecuador is UTC-5, so every appointment would show a day early.
// Appending the time forces local-time parsing. Same fix, same reason, as
// PurchaseRow.vue's formatDate().
function formatDate(dateStr) {
  if (!dateStr) return ''
  try {
    return new Intl.DateTimeFormat('es', {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
    }).format(new Date(`${dateStr}T00:00:00`))
  } catch {
    return dateStr
  }
}

function timeRange(appointment) {
  const start = appointment.scheduled_time
  const end = appointment.scheduled_end_time
  return end ? `${start} – ${end}` : start
}
</script>

<template>
  <div>
    <!-- Scope tabs -->
    <div
      role="tablist"
      aria-label="Filtrar agenda"
      class="inline-flex gap-1 bg-surface-container-low rounded-xl p-1 mb-4"
    >
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        role="tab"
        :data-agenda-tab="tab.key"
        :aria-selected="scope === tab.key"
        class="min-h-11 px-5 rounded-lg font-label-md text-label-md transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
        :class="scope === tab.key
          ? 'bg-surface text-deep-marsala shadow-sm font-bold'
          : 'text-on-surface-variant'"
        @click="scope = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Loading -->
    <div v-if="slice.loading" class="space-y-3">
      <div
        v-for="i in 2"
        :key="i"
        data-agenda-skeleton
        class="bg-surface-muted rounded-2xl border border-blush-canvas/30 p-4 flex items-center gap-4 animate-pulse"
      >
        <div class="w-14 h-14 bg-surface-container rounded-lg flex-shrink-0" />
        <div class="flex-1 space-y-2">
          <div class="h-4 bg-surface-container rounded w-2/3" />
          <div class="h-3 bg-surface-container rounded w-1/2" />
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="slice.error" data-agenda-error class="text-center state-y">
      <span class="material-symbols-outlined text-error text-5xl mb-4" aria-hidden="true">error</span>
      <p class="font-body-lg text-body-lg text-on-surface">{{ slice.error }}</p>
      <button
        type="button"
        data-agenda-retry
        class="mt-4 text-primary hover:underline font-label-md text-label-md min-h-11 px-4"
        @click="store.fetchAppointments(scope)"
      >
        Intentar de nuevo
      </button>
    </div>

    <!-- Empty -->
    <div v-else-if="!slice.items.length" data-agenda-empty class="text-center state-y">
      <span class="material-symbols-outlined text-blush-canvas text-5xl mb-3" aria-hidden="true">event_available</span>
      <p class="font-body-lg text-body-lg text-on-surface mb-4">
        {{ scope === 'upcoming' ? 'No tienes citas próximas.' : 'Todavía no tienes citas pasadas.' }}
      </p>
      <RouterLink
        v-if="scope === 'upcoming'"
        to="/services"
        data-browse-services
        class="inline-flex items-center min-h-11 px-6 py-3 rounded-xl bg-apricot-glow text-deep-marsala font-label-md text-label-md"
      >
        Reservar un servicio
      </RouterLink>
    </div>

    <!-- List -->
    <div v-else data-agenda-list class="space-y-3">
      <div
        v-for="appointment in slice.items"
        :key="appointment.id"
        data-agenda-row
        class="flex items-center gap-4 bg-surface rounded-2xl border border-blush-canvas/30 shadow-sm shadow-primary/5 p-4"
        :class="{ 'opacity-70': appointment.status === 'cancelled' }"
      >
        <!-- Date block -->
        <div class="w-14 flex-shrink-0 flex flex-col items-center justify-center bg-surface-container-low rounded-lg py-2">
          <span class="material-symbols-outlined text-primary text-[20px]" aria-hidden="true">calendar_month</span>
        </div>

        <div class="flex-1 min-w-0">
          <p class="font-title-sm text-title-sm text-on-surface truncate">
            {{ appointment.service?.title ?? 'Servicio' }}
          </p>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
            {{ formatDate(appointment.scheduled_date) }} · {{ timeRange(appointment) }}
          </p>
        </div>

        <div class="flex flex-col items-end gap-1 flex-shrink-0">
          <span
            v-if="appointment.deposit_amount_cents"
            class="font-title-sm text-title-sm text-on-surface tabular-nums"
          >
            {{ formatCurrency(appointment.deposit_amount_cents, appointment.order?.currency || 'USD') }}
          </span>
          <BaseBadge :variant="statusConfig[appointment.status]?.variant || 'blush'" pill>
            {{ statusConfig[appointment.status]?.label || appointment.status }}
          </BaseBadge>
        </div>
      </div>
    </div>
  </div>
</template>
