<script setup>
import { computed } from 'vue'

const props = defineProps({
  // blush = soft tag · accent = highlight · secondary = filled · primary = strong
  variant: { type: String, default: 'blush' },
  pill: { type: Boolean, default: false },
})

const base = 'inline-flex items-center gap-1 font-label-sm text-label-sm'

const variants = {
  blush: 'bg-blush-canvas text-primary',
  accent: 'bg-apricot-glow text-deep-marsala',
  secondary: 'bg-secondary text-on-secondary',
  primary: 'bg-primary/90 backdrop-blur-md text-on-primary uppercase tracking-tighter',
}

const classes = computed(() => [
  base,
  variants[props.variant],
  props.pill ? 'px-3 py-1 rounded-full' : 'px-2 py-0.5 rounded',
])
</script>

<template>
  <span :class="classes">
    <slot />
  </span>
</template>
