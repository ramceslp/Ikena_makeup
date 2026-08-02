<script setup>
import { degradationMessage } from '../../../utils/reportsFormat.js'

// Controlled component: the parent owns the filter state and re-fetches on
// each `update:*` event. `degraded`/`requestedGranularity`/
// `effectiveGranularity` come straight from the API response's filter
// metadata so the notice can never drift from what was actually returned
// (design adjustment #1 — a wide range degrades instead of ever 422ing).
const props = defineProps({
  from: { type: String, required: true },
  to: { type: String, required: true },
  granularity: { type: String, required: true },
  degraded: { type: Boolean, default: false },
  requestedGranularity: { type: String, default: null },
  effectiveGranularity: { type: String, default: null },
})

const emit = defineEmits(['update:from', 'update:to', 'update:granularity'])

function onFromChange(event) {
  emit('update:from', event.target.value)
}

function onToChange(event) {
  emit('update:to', event.target.value)
}

function onGranularityChange(event) {
  emit('update:granularity', event.target.value)
}
</script>

<template>
  <div class="bg-surface rounded-2xl border border-blush-canvas/20 p-4">
    <div class="flex flex-wrap gap-4">
      <div class="flex flex-col gap-1">
        <label class="font-label-sm text-label-sm text-on-surface-variant">Desde</label>
        <input
          data-filter-from
          type="date"
          :value="from"
          @change="onFromChange"
          class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="font-label-sm text-label-sm text-on-surface-variant">Hasta</label>
        <input
          data-filter-to
          type="date"
          :value="to"
          @change="onToChange"
          class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="font-label-sm text-label-sm text-on-surface-variant">Granularidad</label>
        <select
          data-filter-granularity
          :value="granularity"
          @change="onGranularityChange"
          class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none min-w-[120px]"
        >
          <option value="day">Día</option>
          <option value="week">Semana</option>
          <option value="month">Mes</option>
        </select>
      </div>
    </div>

    <p
      v-if="degraded"
      data-degradation-notice
      class="mt-3 font-body-sm text-body-sm text-on-error-container bg-error-container/40 rounded-lg px-3 py-2"
    >
      {{ degradationMessage(props.requestedGranularity, props.effectiveGranularity) }}
    </p>
  </div>
</template>
