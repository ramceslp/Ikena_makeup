<script setup>
// Presentational component — renders a single KPI metric card.
// Props:
//   label   — string — the metric name (e.g. "Ingresos")
//   value   — string|number — already formatted value to display
//   icon    — string (optional) — Material Symbols icon name
//   hint    — string (optional) — small subtitle text below the value
defineProps({
  label: {
    type: String,
    required: true,
  },
  value: {
    type: [String, Number],
    required: true,
  },
  icon: {
    type: String,
    default: null,
  },
  hint: {
    type: String,
    default: null,
  },
})
</script>

<template>
  <div
    class="bg-surface-container-low rounded-2xl border border-outline-variant p-6 flex flex-col gap-3 hover:border-blush-canvas/40 transition-colors"
  >
    <!-- Icon + Label row -->
    <div class="flex items-center gap-2">
      <span
        v-if="icon"
        class="material-symbols-outlined text-[20px] text-on-surface-variant"
        aria-hidden="true"
      >{{ icon }}</span>
      <p class="font-label-md text-label-md text-on-surface-variant uppercase tracking-widest">
        {{ label }}
      </p>
    </div>

    <!-- Value -->
    <p class="font-headline-lg text-headline-lg text-deep-marsala leading-none">
      {{ value }}
    </p>

    <!-- Hint -->
    <p v-if="hint" class="font-label-sm text-label-sm text-outline">
      {{ hint }}
    </p>
  </div>
</template>
