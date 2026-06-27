<script setup>
import { computed } from 'vue'

const props = defineProps({
  // primary = apricot CTA · outline = bordered marsala · solid = filled marsala
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' }, // md | sm
  type: { type: String, default: 'button' },
  // Async state: disables the button and shows a spinner without layout shift.
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const base =
  'relative inline-flex items-center justify-center gap-2 rounded-xl font-title-md ' +
  'transition-all active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed disabled:active:scale-100 ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary ' +
  'focus-visible:ring-offset-2 focus-visible:ring-offset-surface'

const variants = {
  primary:
    'btn-gloss bg-apricot-glow text-deep-marsala shadow-lg shadow-apricot-glow/20 hover:-translate-y-0.5',
  outline:
    'border-2 border-primary text-primary hover:bg-primary hover:text-on-primary',
  solid: 'bg-primary text-on-primary hover:shadow-xl',
}

const sizes = {
  md: 'px-8 py-4 text-title-md',
  sm: 'px-5 py-2 rounded-lg text-label-md',
}

const classes = computed(() => [base, variants[props.variant], sizes[props.size]])

// Loading implies non-interactive.
const isDisabled = computed(() => props.disabled || props.loading)
</script>

<template>
  <button
    :type="type"
    :disabled="isDisabled"
    :aria-busy="loading || undefined"
    :class="classes"
  >
    <!-- Spinner overlays the centered label so width never shifts -->
    <span
      v-if="loading"
      class="absolute inset-0 z-[1] flex items-center justify-center"
      aria-hidden="true"
    >
      <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
      </svg>
    </span>

    <span
      class="relative z-[1] inline-flex items-center gap-2 transition-opacity"
      :class="loading ? 'opacity-0' : 'opacity-100'"
    >
      <slot />
    </span>
  </button>
</template>
