<script setup>
import { computed } from 'vue'
import { useCartStore } from '../../stores/cart.js'

defineEmits(['checkout'])

const cart = useCartStore()

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
      @click="$emit('checkout')"
      class="w-full bg-apricot-glow text-deep-marsala font-label-md text-label-md py-4 rounded-xl hover:-translate-y-0.5 active:scale-95 transition-all shadow-lg shadow-apricot-glow/20 disabled:opacity-40 disabled:cursor-not-allowed"
    >
      Proceder al Pago
    </button>
  </div>
</template>
