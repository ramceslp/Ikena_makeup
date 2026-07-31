<script setup>
import { computed, onMounted, ref } from 'vue'
import { useBookingStore } from '../../stores/booking.js'
import { formatDayHeading, getBusinessToday } from '../../utils/localDate.js'
import BookingCalendar from './BookingCalendar.vue'

const props = defineProps({
  serviceId: {
    type: [Number, String],
    required: true,
  },
})

const emit = defineEmits(['slot-selected', 'selection-cleared'])

const bookingStore = useBookingStore()

// "Today" is resolved once, at mount, in the BUSINESS timezone (not the
// browser's) — the calendar's booking window is anchored to it for the
// component's lifetime, and must agree with the backend's own
// `[today, today + look_ahead_days)` window (see getBusinessToday).
const today = getBusinessToday()

const selectedDate = ref(null)
const selectedSlotKey = ref(null)

const days = computed(() => bookingStore.availableDays)
const daysLoading = computed(() => bookingStore.isDaysLoading)
const daysError = computed(() => bookingStore.daysError)

const daySlots = computed(() => bookingStore.daySlots)
const daySlotsLoading = computed(() => bookingStore.isDaySlotsLoading)
const daySlotsError = computed(() => bookingStore.daySlotsError)

// True while the checkout handoff POST is in flight (BookingForm's submit).
// Locks the whole calendar (day cells, month nav, time chips) — see
// BookingCalendar's `locked` prop. Deliberately NOT
// `daysLoading`/`daySlotsLoading`: those are ordinary background fetches and
// must stay interactive.
const isSubmitting = computed(() => bookingStore.isLoading)

// A recurring weekly agenda block can still yield slots sharing the same
// `id` within a single day only in edge cases; date is fixed per day view,
// so id + start_time is a sufficient unique key here.
function occurrenceKey(slot) {
  return `${slot.id}-${slot.start_time}`
}

// Distinguishes WHY a chip is disabled so the rendered label and styling can
// tell "temporarily locked" apart from "genuinely unavailable" — see
// isDisabled() and the template below.
function slotDisabledReason(slot) {
  if (slot.is_blocked) return 'blocked'
  if (slot.capacity_remaining <= 0) return 'full'
  if (isSubmitting.value) return 'locked'
  return null
}

function isDisabled(slot) {
  return slotDisabledReason(slot) !== null
}

async function onDaySelected(dateKey) {
  selectedDate.value = dateKey
  selectedSlotKey.value = null // changing day clears any previously selected time
  emit('selection-cleared') // ...and the parent's selectedSlot, so it can't submit a slot from a previously-viewed day
  // A stale error banner from a previous payDeposit() attempt must not keep
  // showing once the user has moved on to a different day — it no longer
  // describes what's on screen.
  bookingStore.bookingError = null
  await bookingStore.fetchDaySlots(props.serviceId, dateKey)
}

function retryFetchDays() {
  bookingStore.fetchAvailableDays(props.serviceId)
}

function selectSlot(slot) {
  if (isDisabled(slot)) return
  selectedSlotKey.value = occurrenceKey(slot)
  emit('slot-selected', {
    id: slot.id,
    scheduled_date: slot.date_label,
    scheduled_time: slot.start_time,
  })
}

const dayHeading = computed(() => (selectedDate.value ? formatDayHeading(selectedDate.value) : ''))

// Announced via aria-live so screen reader users learn the outcome of
// picking a day without having to re-explore the DOM.
const liveMessage = computed(() => {
  if (!selectedDate.value) return ''
  if (daySlotsLoading.value) return `Cargando horarios para ${dayHeading.value}…`
  if (daySlotsError.value) return daySlotsError.value
  const n = daySlots.value.length
  if (n === 0) return `No hay horarios disponibles para ${dayHeading.value}`
  return `${n} horario${n === 1 ? '' : 's'} disponible${n === 1 ? '' : 's'} para ${dayHeading.value}`
})

onMounted(() => {
  bookingStore.fetchAvailableDays(props.serviceId)
})
</script>

<template>
  <div class="w-full flex flex-col gap-6">
    <!-- Calendar loading (FIRST load only — nothing to show yet, so a
         spinner replacing this slot is correct). Gated on `days.length === 0`
         rather than `daysLoading` alone: a BACKGROUND refresh (the
         `retryFetchDays` re-fetch) also flips `daysLoading` true/false, but
         `availableDays` in the store is only overwritten on a SUCCESSFUL
         response — stale data stays put while it's in flight — so this
         branch does not re-trigger once a calendar is showing. -->
    <div
      v-if="daysLoading && days.length === 0"
      class="flex items-center gap-2 py-8 text-on-surface-variant"
    >
      <span class="material-symbols-outlined animate-spin text-[18px]" aria-hidden="true">refresh</span>
      <span class="font-body-sm text-body-sm">Cargando calendario…</span>
    </div>

    <!-- Calendar error (FIRST load failed — no stale data to fall back on). -->
    <div
      v-else-if="daysError && days.length === 0"
      role="alert"
      class="font-body-md text-body-md text-error py-4"
    >
      {{ daysError }}
    </div>

    <!-- Empty state: service has no available days in the whole window -->
    <div
      v-else-if="days.length === 0"
      class="text-center py-8 font-body-md text-body-md text-on-surface-variant"
    >
      No hay horarios disponibles
    </div>

    <!-- Month calendar. Deliberately kept mounted for the lifetime of any
         non-empty `days` — including across background refreshes — so the
         <BookingCalendar> instance (and the DOM focus inside it) survives a
         retry re-fetch instead of being torn down and rebuilt by a
         v-if/v-else-if branch switch (Vue never patches across different
         vnode types at the same position). A background refresh in flight
         is signaled non-destructively below, not by unmounting this. -->
    <template v-else>
      <div
        v-if="daysLoading"
        aria-hidden="true"
        class="flex items-center gap-2 text-on-surface-variant"
      >
        <span class="material-symbols-outlined animate-spin text-[16px]" aria-hidden="true">refresh</span>
        <span class="font-label-sm text-label-sm">Actualizando calendario…</span>
      </div>

      <!-- A background refresh failed while a stale calendar is still on
           screen. Non-blocking — the stale data below stays visible and
           interactive — but must not be silently swallowed: the user could
           otherwise keep picking from a calendar that never refreshed. -->
      <div
        v-else-if="daysError"
        data-days-refresh-error
        role="status"
        class="flex items-center justify-between gap-2 rounded-lg bg-error-container/60 px-3 py-2"
      >
        <span class="flex items-center gap-2 font-label-sm text-label-sm text-on-error-container">
          <span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>
          No se pudo actualizar el calendario.
        </span>
        <button
          type="button"
          data-days-refresh-retry
          @click="retryFetchDays"
          class="font-label-sm text-label-sm text-on-error-container underline cursor-pointer hover:opacity-80"
        >
          Reintentar
        </button>
      </div>

      <BookingCalendar
        :days="days"
        :selected-date="selectedDate"
        :today="today"
        :locked="isSubmitting"
        @day-selected="onDaySelected"
      />
    </template>

    <!-- Screen-reader announcement for the outcome of selecting a day -->
    <div aria-live="polite" class="sr-only">{{ liveMessage }}</div>

    <!-- Time chips for the selected day -->
    <div v-if="selectedDate" class="flex flex-col gap-3">
      <h3 data-day-heading class="font-title-sm text-title-sm text-on-surface">
        {{ dayHeading }}
      </h3>

      <div
        v-if="daySlotsLoading"
        data-day-slots-loading
        class="flex items-center gap-2 py-4 text-on-surface-variant"
      >
        <span class="material-symbols-outlined animate-spin text-[18px]" aria-hidden="true">refresh</span>
        <span class="font-body-sm text-body-sm">Cargando horarios…</span>
      </div>

      <div
        v-else-if="daySlotsError"
        role="alert"
        class="font-body-md text-body-md text-error"
      >
        {{ daySlotsError }}
      </div>

      <div
        v-else-if="daySlots.length === 0"
        class="font-body-sm text-body-sm text-on-surface-variant"
      >
        No hay horarios disponibles para ese día
      </div>

      <div v-else class="flex flex-wrap gap-2">
        <button
          v-for="slot in daySlots"
          :key="occurrenceKey(slot)"
          type="button"
          data-slot-card
          :data-slot-selected="selectedSlotKey === occurrenceKey(slot) ? 'true' : undefined"
          :disabled="isDisabled(slot)"
          :aria-pressed="isDisabled(slot) ? undefined : (selectedSlotKey === occurrenceKey(slot) ? 'true' : 'false')"
          @click="selectSlot(slot)"
          @keydown.enter.space.prevent="selectSlot(slot)"
          class="flex items-center gap-1.5 rounded-full border px-4 py-2 min-h-11 font-label-md text-label-md transition-all"
          :class="[
            slotDisabledReason(slot) === 'locked'
              ? 'opacity-50 cursor-wait bg-surface-container border-blush-canvas/20 text-on-surface-variant'
              : isDisabled(slot)
                ? 'opacity-40 cursor-not-allowed bg-surface-container border-blush-canvas/20 text-on-surface-variant'
                : selectedSlotKey === occurrenceKey(slot)
                  ? 'border-primary bg-primary/10 text-primary ring-2 ring-primary'
                  : 'border-blush-canvas/30 bg-surface hover:border-primary hover:bg-primary/5 cursor-pointer text-on-surface',
          ]"
        >
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">schedule</span>
          {{ slot.start_time }}
          <span v-if="isDisabled(slot)" class="font-label-sm text-label-sm text-outline">
            ({{ slot.is_blocked ? 'Bloqueado' : slotDisabledReason(slot) === 'full' ? 'Completo' : 'Procesando…' }})
          </span>
          <span
            v-else-if="slot.is_near_capacity"
            data-near-capacity-badge
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-tertiary-container text-on-tertiary-container font-label-sm text-label-sm"
          >
            <span class="material-symbols-outlined text-[12px]" aria-hidden="true">warning</span>
            {{ slot.warning_message }}
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
