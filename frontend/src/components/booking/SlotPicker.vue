<script setup>
import { ref } from 'vue'

const props = defineProps({
  slots: {
    type: Array,
    required: true,
  },
})

const emit = defineEmits(['slot-selected'])

const selectedId = ref(null)

function isDisabled(slot) {
  return slot.is_blocked || slot.capacity_remaining <= 0
}

function selectSlot(slot) {
  if (isDisabled(slot)) return
  selectedId.value = slot.id
  emit('slot-selected', {
    id: slot.id,
    scheduled_date: slot.date_label,
    scheduled_time: slot.start_time,
  })
}

function formatDateLabel(dateLabel) {
  // dateLabel is an ISO date string like '2026-07-04'
  try {
    const date = new Date(dateLabel + 'T00:00:00')
    return new Intl.DateTimeFormat('es', { dateStyle: 'medium' }).format(date)
  } catch {
    return dateLabel
  }
}
</script>

<template>
  <div class="w-full">
    <!-- Empty state -->
    <div
      v-if="!slots || slots.length === 0"
      class="text-center py-8 font-body-md text-body-md text-on-surface-variant"
    >
      No hay horarios disponibles
    </div>

    <!-- Slot grid -->
    <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
      <button
        v-for="slot in slots"
        :key="slot.id"
        type="button"
        data-slot-card
        :data-slot-selected="selectedId === slot.id ? 'true' : undefined"
        :disabled="isDisabled(slot)"
        @click="selectSlot(slot)"
        class="flex flex-col items-center justify-center gap-1 rounded-xl border p-3 transition-all"
        :class="[
          isDisabled(slot)
            ? 'opacity-40 cursor-not-allowed bg-surface-container border-blush-canvas/20'
            : selectedId === slot.id
              ? 'border-primary bg-primary/10 text-primary ring-2 ring-primary'
              : 'border-blush-canvas/30 bg-surface hover:border-primary hover:bg-primary/5 cursor-pointer',
        ]"
      >
        <span class="font-label-md text-label-md font-semibold">
          {{ formatDateLabel(slot.date_label) }}
        </span>
        <span class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1">
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">schedule</span>
          {{ slot.start_time }}
        </span>
        <span v-if="isDisabled(slot)" class="font-label-sm text-label-sm text-outline">
          {{ slot.is_blocked ? 'Bloqueado' : 'Completo' }}
        </span>
      </button>
    </div>
  </div>
</template>
