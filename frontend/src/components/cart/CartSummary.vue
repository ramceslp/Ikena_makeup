<script setup>
import { computed } from 'vue'
import { useCartStore } from '../../stores/cart.js'

defineEmits(['checkout'])

defineProps({
  loading: { type: Boolean, default: false },
})

const cart = useCartStore()

// DISPLAY estimate only — keep in sync with backend commerce.tax.iva_rate
// (config key COMMERCE_IVA_RATE). The backend is authoritative for the
// amount actually charged.
const IVA_RATE = 0.15

const subtotalCents = computed(() => Math.round(cart.subtotal * 100))
const taxCents = computed(() => Math.round(subtotalCents.value * IVA_RATE))
const totalCents = computed(() => subtotalCents.value + taxCents.value)

const subtotalDisplay = computed(() => `$${(subtotalCents.value / 100).toFixed(2)}`)
const taxDisplay = computed(() => `$${(taxCents.value / 100).toFixed(2)}`)
const totalDisplay = computed(() => `$${(totalCents.value / 100).toFixed(2)}`)
</script>

<template>
  <div class="bg-white rounded-2xl border border-blush-canvas/30 p-6 flex flex-col gap-4">
    <h2 class="font-title-md text-title-md text-deep-marsala">Resumen del pedido</h2>

    <!-- Item count -->
    <p class="font-body-sm text-body-sm text-on-surface-variant">
      {{ cart.count }} {{ cart.count === 1 ? 'producto' : 'productos' }}
    </p>

    <!-- Line items breakdown -->
    <div class="space-y-2 border-t border-blush-canvas/20 pt-4">
      <div class="flex justify-between font-body-md text-body-md text-on-surface-variant">
        <span>Subtotal</span>
        <span>{{ subtotalDisplay }}</span>
      </div>
      <div class="flex justify-between font-body-md text-body-md text-on-surface-variant">
        <span>IVA (15%)</span>
        <span>{{ taxDisplay }}</span>
      </div>
    </div>

    <!-- Total -->
    <div class="flex justify-between items-center border-t border-blush-canvas/20 pt-4">
      <span class="font-title-md text-title-md text-deep-marsala">Total</span>
      <span class="font-title-lg text-title-lg text-primary">{{ totalDisplay }}</span>
    </div>

    <!-- CTA -->
    <button
      data-checkout-btn
      :disabled="loading"
      @click="$emit('checkout')"
      class="btn-gloss w-full bg-apricot-glow text-deep-marsala font-label-md text-label-md py-4 rounded-xl hover:-translate-y-0.5 active:scale-95 transition-all shadow-lg shadow-apricot-glow/20 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
    >
      <span class="relative z-[1] inline-flex items-center justify-center gap-2">
        <svg
          v-if="loading"
          class="animate-spin w-5 h-5 shrink-0"
          fill="none"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        {{ loading ? 'Procesando...' : 'Proceder al Pago' }}
      </span>
    </button>
  </div>
</template>
