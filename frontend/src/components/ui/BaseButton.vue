<script setup>
import { computed } from 'vue'

const props = defineProps({
  // primary = apricot CTA · outline = bordered marsala · solid = filled marsala
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' }, // md | sm
  type: { type: String, default: 'button' },
})

const base =
  'inline-flex items-center justify-center gap-2 rounded-xl font-title-md ' +
  'transition-all active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed'

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
</script>

<template>
  <button :type="type" :class="classes">
    <span class="relative z-[1] inline-flex items-center gap-2">
      <slot />
    </span>
  </button>
</template>
