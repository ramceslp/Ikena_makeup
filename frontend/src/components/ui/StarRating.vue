<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  // Display mode: fractional value (e.g. 4.3)
  rating: { type: Number, default: 0 },
  // Editable mode: integer 1..max via v-model
  modelValue: { type: Number, default: 0 },
  editable: { type: Boolean, default: false },
  max: { type: Number, default: 5 },
  // 'sm' | 'md' | 'lg'
  size: { type: String, default: 'md' },
  // Optional reviews count rendered as "(N)"
  count: { type: Number, default: null },
  // Show numeric rating value in display mode
  showValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

// ── Size map ─────────────────────────────────────────────────────────────────
const sizeClass = computed(() => {
  const map = { sm: 'text-[16px]', md: 'text-[20px]', lg: 'text-[28px]' }
  return map[props.size] ?? map.md
})

// ── Editable: hover state ─────────────────────────────────────────────────────
const hover = ref(0)

function handleClick(i) {
  emit('update:modelValue', i)
}

// Active fill index in editable mode (hover takes priority)
const activeValue = computed(() => hover.value || props.modelValue)

// ── Display: determine star type per index ────────────────────────────────────
function starType(i) {
  if (props.rating >= i) return 'filled'
  if (props.rating >= i - 0.5) return 'half'
  return 'empty'
}

// ── ARIA ──────────────────────────────────────────────────────────────────────
const wrapperRole = computed(() => (props.editable ? 'radiogroup' : 'img'))

const wrapperAriaLabel = computed(() => {
  if (props.editable) return 'Selecciona una valoración'
  let label = `Valoración: ${props.rating} de ${props.max}`
  if (props.count !== null && props.count !== undefined) {
    label += `, ${props.count} valoraciones`
  }
  return label
})

// ── Indices for iteration ─────────────────────────────────────────────────────
const indices = computed(() => Array.from({ length: props.max }, (_, i) => i + 1))
</script>

<template>
  <!-- Single root element — role/aria change based on editable prop -->
  <div
    :role="wrapperRole"
    :aria-label="wrapperAriaLabel"
    class="inline-flex items-center gap-1"
  >
    <!-- ── EDITABLE stars ───────────────────────────────────────────────── -->
    <template v-if="editable">
      <button
        v-for="i in indices"
        :key="i"
        type="button"
        :aria-label="`${i} estrella${i !== 1 ? 's' : ''}`"
        class="focus:outline-none transition-colors"
        @click="handleClick(i)"
        @mouseenter="hover = i"
        @mouseleave="hover = 0"
      >
        <span
          class="material-symbols-outlined select-none"
          :class="[sizeClass, activeValue >= i ? 'text-apricot-glow' : 'text-outline-variant']"
          :style="activeValue >= i ? `font-variation-settings: 'FILL' 1;` : ''"
          aria-hidden="true"
        >star</span>
      </button>
    </template>

    <!-- ── DISPLAY stars ────────────────────────────────────────────────── -->
    <template v-else>
      <!-- Numeric value (optional) -->
      <span
        v-if="showValue"
        class="font-label-md text-label-md text-on-surface-variant"
      >{{ rating.toFixed(1) }}</span>

      <!-- Stars -->
      <span
        v-for="i in indices"
        :key="i"
        class="material-symbols-outlined select-none"
        :class="[sizeClass, starType(i) !== 'empty' ? 'text-apricot-glow' : 'text-outline-variant']"
        :style="starType(i) === 'filled' || starType(i) === 'half' ? `font-variation-settings: 'FILL' 1;` : ''"
        :data-star="starType(i)"
        aria-hidden="true"
      >{{ starType(i) === 'half' ? 'star_half' : 'star' }}</span>

      <!-- Count (optional) -->
      <span
        v-if="count !== null && count !== undefined"
        class="font-label-sm text-label-sm text-on-surface-variant"
      >({{ count }})</span>
    </template>
  </div>
</template>
