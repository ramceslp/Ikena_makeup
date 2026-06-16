<script setup>
import { computed } from 'vue'

const props = defineProps({
  // 0–100 percentage value
  value: { type: Number, default: 0 },
  // Whether to render the numeric label beside the bar
  showLabel: { type: Boolean, default: false },
})

// Clamp value to [0, 100] to prevent layout breakage
const clamped = computed(() => Math.min(100, Math.max(0, props.value)))
</script>

<template>
  <div class="flex items-center gap-3">
    <!-- Track -->
    <div
      class="flex-grow h-2 bg-surface-container rounded-full overflow-hidden"
      role="progressbar"
      :aria-valuenow="clamped"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-label="`Progreso: ${clamped}%`"
    >
      <!-- Fill -->
      <div
        class="h-full bg-apricot-glow rounded-full transition-all duration-500"
        :style="{ width: `${clamped}%` }"
      />
    </div>

    <!-- Optional percentage label -->
    <span
      v-if="showLabel"
      class="font-label-sm text-label-sm text-on-surface-variant shrink-0 w-10 text-right"
    >
      {{ clamped }}%
    </span>
  </div>
</template>
