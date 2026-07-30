<script setup>
import { computed } from 'vue'
import { useCartStore } from '../../stores/cart.js'

// Ported from frontend/src/components/cart/CartSummary.vue
// (mobile-capacitor-setup Phase 8, task 8.1). The "Proceder al Pago" CTA was
// rendered-but-permanently-disabled in PR8a; this PR (8b, tasks 8.3-8.5)
// wires it to cart.pay(), which calls POST /checkout/handoff and opens the
// returned URL via @capacitor/browser (design.md Decision 1) instead of the
// web's direct /cart/checkout + inline PayPhone widget -- rendering payment
// UI inside this app's own WebView is explicitly forbidden by the spec's
// Mobile App Boundaries.
const cart = useCartStore()

// DISPLAY estimate only — keep in sync with backend commerce.tax.iva_rate
// (config key COMMERCE_IVA_RATE). The backend is authoritative for the
// amount actually charged.
const IVA_RATE = 0.15

const subtotalCents = computed(() => Math.round(cart.subtotal * 100))
const taxCents = computed(() => Math.round(subtotalCents.value * IVA_RATE))
const totalCents = computed(() => subtotalCents.value + taxCents.value)

// Plain currency formatting for these three DERIVED totals -- deliberately
// NOT routed through utils/formatPrice.js. That util's "Gratis" fallback is
// a policy for a genuine zero unit *price* (a real free product/service),
// not for a computed tax/subtotal/total that can round to 0 for reasons
// that have nothing to do with the cart being free (e.g. a small subtotal
// makes taxCents round to 0 via Math.round(subtotalCents * IVA_RATE)).
// "IVA (15%): Gratis" would be nonsensical. Restores the original web
// CartSummary.vue's local toFixed(2)-based formatter for this reason.
function formatCents(cents) {
  return `$${(cents / 100).toFixed(2)}`
}

const subtotalDisplay = computed(() => formatCents(subtotalCents.value))
const taxDisplay = computed(() => formatCents(taxCents.value))
const totalDisplay = computed(() => formatCents(totalCents.value))
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
        <span class="tabular-nums">{{ subtotalDisplay }}</span>
      </div>
      <div class="flex justify-between font-body-md text-body-md text-on-surface-variant">
        <span>IVA (15%)</span>
        <span class="tabular-nums">{{ taxDisplay }}</span>
      </div>
    </div>

    <!-- Total -->
    <div class="flex justify-between items-center border-t border-blush-canvas/20 pt-4">
      <span class="font-title-md text-title-md text-deep-marsala">Total</span>
      <span data-cart-total class="font-title-lg text-title-lg text-primary tabular-nums">{{ totalDisplay }}</span>
    </div>

    <!-- CTA — wired to cart.pay() (tasks 8.3-8.5): POSTs /checkout/handoff
         and opens the result via @capacitor/browser, never this app's own
         router/WebView. Disabled only while a handoff request is in flight,
         to prevent duplicate taps opening the browser twice. -->
    <button
      data-checkout-btn
      :disabled="cart.isPaying"
      @click="cart.pay()"
      class="btn-gloss w-full bg-apricot-glow text-deep-marsala font-label-md text-label-md py-4 rounded-xl transition-all shadow-lg shadow-apricot-glow/20 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
    >
      {{ cart.isPaying ? 'Redirigiendo…' : 'Proceder al Pago' }}
    </button>
  </div>
</template>
