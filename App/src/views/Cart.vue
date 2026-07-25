<script setup>
import { useCartStore } from '../stores/cart.js'
import CartItemRow from '../components/cart/CartItemRow.vue'
import CartSummary from '../components/cart/CartSummary.vue'

// Ported from frontend/src/views/Cart.vue (mobile-capacitor-setup Phase 8,
// task 8.1). Cart build/manage UX (view items, adjust quantity, remove) is a
// direct port; the web version's inline PayPhone checkout handler
// (handleCheckout()) is NOT ported — see CartSummary.vue's header comment
// for why: rendering PayPhone in this app's own WebView would violate the
// spec's Mobile App Boundaries ("payment never renders in the app's own
// WebView"). The app's real "pay" action (checkout-handoff +
// @capacitor/browser, tasks 8.3-8.5) is wired into CartSummary.vue's CTA;
// cart.payError below surfaces either the empty-cart guard or a failed
// handoff request from that action.
const cart = useCartStore()
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter py-12">
    <h1 class="font-headline-md text-headline-md text-deep-marsala mb-8">
      Mi Carrito
    </h1>

    <!-- Persist-error banner (Judgment Day Round 2): cart.persistError is set
         by cart.js's _persist() when the storage.js set()/remove() call
         fails, and cleared again on the next successful persist -- same
         error-container convention as BookingForm.vue's data-booking-error. -->
    <div
      v-if="cart.persistError"
      data-persist-error
      class="mb-6 bg-error-container rounded-xl px-4 py-3 font-body-md text-body-md text-on-error-container"
      role="alert"
    >
      {{ cart.persistError }}
    </div>

    <!-- Pay-error banner (tasks 8.3-8.5): cart.payError is set either by the
         client-side empty-cart guard in cart.pay() or by a failed
         POST /checkout/handoff call, and cleared at the start of the next
         pay() attempt -- same error-container convention as the persist-error
         banner above. -->
    <div
      v-if="cart.payError"
      data-pay-error
      class="mb-6 bg-error-container rounded-xl px-4 py-3 font-body-md text-body-md text-on-error-container"
      role="alert"
    >
      {{ cart.payError }}
    </div>

    <!-- Empty state -->
    <div v-if="cart.isEmpty" data-empty-cart class="flex flex-col items-center justify-center py-24 gap-6 text-center">
      <span class="material-symbols-outlined text-7xl text-on-surface-variant/40" aria-hidden="true">
        shopping_bag
      </span>
      <p class="font-body-lg text-body-lg text-on-surface-variant">
        Tu carrito está vacío
      </p>
      <RouterLink
        to="/products"
        data-browse-link
        class="bg-apricot-glow text-deep-marsala px-6 py-3 rounded-xl font-label-md text-label-md hover:-translate-y-0.5 transition-all active:scale-95 shadow-lg shadow-apricot-glow/20"
      >
        Explorar productos
      </RouterLink>
    </div>

    <!-- Cart with items -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Items list -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl border border-blush-canvas/30 p-6">
          <h2 class="font-title-sm text-title-sm text-on-surface-variant mb-4">
            {{ cart.count }} {{ cart.count === 1 ? 'producto' : 'productos' }}
          </h2>

          <div data-cart-items>
            <div
              v-for="item in cart.items"
              :key="item.product_id"
              data-cart-row
            >
              <CartItemRow :item="item" />
            </div>
          </div>
        </div>
      </div>

      <!-- Summary sidebar -->
      <div class="lg:col-span-1">
        <CartSummary />
      </div>
    </div>
  </div>
</template>
