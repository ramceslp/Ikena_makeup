<script setup>
import { computed } from 'vue'
import { useCartStore } from '../../stores/cart.js'

// Ported from frontend/src/components/cart/CartSummary.vue
// (mobile-capacitor-setup Phase 8, task 8.1), with one deliberate deviation:
// the "Proceder al Pago" CTA is rendered but permanently disabled and NOT
// wired to any handler in this PR. The web version posts to /cart/checkout
// and renders the PayPhone widget inline in Cart.vue -- doing the same here
// would render payment UI inside this app's own WebView, which the spec's
// Mobile App Boundaries explicitly forbid. The app's real "pay" action
// (checkout-handoff -> @capacitor/browser, per design.md Decision 1) is
// tasks 8.3-8.5, a separate PR (8b) that will replace this disabled button
// with the real wiring. Kept visible-but-disabled rather than omitted
// entirely so the cart page isn't missing an obviously-expected CTA.
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
      <span data-cart-total class="font-title-lg text-title-lg text-primary">{{ totalDisplay }}</span>
    </div>

    <!-- CTA — disabled, see file header comment. Wired in a future PR. -->
    <button
      data-checkout-btn
      disabled
      class="btn-gloss w-full bg-apricot-glow text-deep-marsala font-label-md text-label-md py-4 rounded-xl transition-all shadow-lg shadow-apricot-glow/20 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
    >
      Proceder al Pago
    </button>
  </div>
</template>
