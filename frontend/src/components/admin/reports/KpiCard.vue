<script setup>
// Presentational single-metric card for the admin reports dashboard.
// Deliberately dumb: formatting (currency, counts) happens in the parent so
// this component stays reusable across every KPI shape.
defineProps({
  label: { type: String, required: true },
  value: { type: String, required: true },
  hint: { type: String, default: '' },
})
</script>

<template>
  <div class="bg-surface rounded-2xl border border-blush-canvas/20 p-5">
    <p class="font-label-sm text-label-sm text-on-surface-variant">{{ label }}</p>
    <p class="font-headline-sm text-headline-sm text-deep-marsala mt-1">{{ value }}</p>
    <p
      v-if="hint"
      data-kpi-hint
      class="font-label-sm text-label-sm text-on-surface-variant mt-2"
    >
      {{ hint }}
    </p>
  </div>
</template>
