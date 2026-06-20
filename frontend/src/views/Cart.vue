<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCartStore } from '../stores/cart.js'
import CartItemRow from '../components/cart/CartItemRow.vue'
import CartSummary from '../components/cart/CartSummary.vue'

const router = useRouter()
const cart = useCartStore()

// ── State ────────────────────────────────────────────────────────────────────
const checkoutLoading = ref(false)
const checkoutError = ref('')

// PayPhone asset injection (mirrors Checkout.vue)
const PAYPHONE_CSS = 'https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.css'
const PAYPHONE_JS  = 'https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.js'
const boxReady = ref(false)
const boxConfig = ref(null)

function injectPayPhoneAssets() {
  return new Promise((resolve, reject) => {
    if (!document.querySelector(`link[href="${PAYPHONE_CSS}"]`)) {
      const link = document.createElement('link')
      link.rel = 'stylesheet'
      link.href = PAYPHONE_CSS
      document.head.appendChild(link)
    }
    if (!document.querySelector(`script[src="${PAYPHONE_JS}"]`)) {
      const script = document.createElement('script')
      script.type = 'module'
      script.src = PAYPHONE_JS
      script.onload = resolve
      script.onerror = () => reject(new Error('No se pudo cargar el módulo de PayPhone'))
      document.head.appendChild(script)
    } else {
      resolve()
    }
  })
}

function waitForConstructor(timeout = 8000) {
  return new Promise((resolve, reject) => {
    const start = Date.now()
    const id = setInterval(() => {
      if (typeof window.PPaymentButtonBox === 'function') {
        clearInterval(id)
        resolve()
      } else if (Date.now() - start > timeout) {
        clearInterval(id)
        reject(new Error('PayPhone no se cargó a tiempo. Recarga la página e intenta de nuevo.'))
      }
    }, 100)
  })
}

// ── Checkout handler ──────────────────────────────────────────────────────────
async function handleCheckout() {
  // Re-entrancy guard — block double-submit while a checkout is in flight
  if (checkoutLoading.value) return

  checkoutError.value = ''
  checkoutLoading.value = true

  try {
    const data = await cart.checkout()
    // data = { order_id, provider, config }
    const config = data.data ?? data
    boxConfig.value = config.config

    await injectPayPhoneAssets()
    await waitForConstructor()

    const responseUrl =
      import.meta.env.VITE_PAYMENT_CALLBACK_URL ||
      (window.location.origin + '/payment/callback')

    // Render the payment widget on next tick (after DOM updates)
    await new Promise((resolve) => setTimeout(resolve, 0))
    new window.PPaymentButtonBox({
      ...boxConfig.value,
      responseUrl,
    }).render('pp-cart-button')

    // Clear the cart ONLY after the widget has rendered successfully.
    // Doing it earlier would leave the user with an empty cart if asset
    // injection or widget rendering fails, preventing any retry.
    cart.clear()
    // Signal the widget as ready (template checks boxReady before cart.isEmpty,
    // so the widget panel stays visible even though the cart is now empty).
    boxReady.value = true
  } catch (err) {
    const status = err.response?.status

    if (status === 401) {
      router.push({ name: 'Login', query: { redirect: '/cart' } })
      return
    }

    if (status === 409) {
      const productId = err.response?.data?.product_id
      const item = cart.items.find((i) => i.product_id === productId)
      const name = item?.title ?? String(productId)
      checkoutError.value = `Sin stock suficiente: "${name}". Ajusta la cantidad o quitalo del carrito.`
      return
    }

    if (status === 422) {
      const productId = err.response?.data?.product_id
      const item = cart.items.find((i) => i.product_id === productId)
      const name = item?.title ?? String(productId)
      checkoutError.value = `"${name}" ya no está disponible. Quítalo del carrito para continuar.`
      return
    }

    checkoutError.value =
      err.response?.data?.message ||
      err.message ||
      'Error al procesar el pago. Intenta de nuevo.'
  } finally {
    checkoutLoading.value = false
  }
}
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter py-12">
    <h1 class="font-headline-md text-headline-md text-deep-marsala mb-8">
      Mi Carrito
    </h1>

    <!-- PayPhone widget (appears after successful checkout initiation).
         Checked BEFORE empty-cart so the widget stays visible after
         cart.clear() is called at the end of the success path. -->
    <div v-if="boxReady" class="max-w-2xl mx-auto">
      <div class="mb-6">
        <button
          @click="boxReady = false; boxConfig = null"
          class="inline-flex items-center gap-1.5 text-on-surface-variant text-sm hover:text-primary transition-colors mb-4"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Volver al carrito
        </button>
        <h2 class="font-title-lg text-title-lg text-deep-marsala">Completar pago</h2>
      </div>
      <div class="bg-white rounded-2xl border border-blush-canvas/30 p-6">
        <div id="pp-cart-button" />
      </div>
    </div>

    <!-- Empty state (shown when cart has no items AND widget is not active) -->
    <div v-else-if="cart.isEmpty" data-empty-cart class="flex flex-col items-center justify-center py-24 gap-6 text-center">
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

          <!-- Checkout error -->
          <div
            v-if="checkoutError"
            data-checkout-error
            class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3"
          >
            <p class="font-body-sm text-body-sm text-red-700">{{ checkoutError }}</p>
          </div>

          <!-- Loading overlay for checkout -->
          <div
            v-if="checkoutLoading"
            data-checkout-loading
            class="flex items-center justify-center gap-3 py-8"
          >
            <svg class="animate-spin w-6 h-6 text-primary" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p class="font-body-md text-body-md text-on-surface-variant">Procesando pago...</p>
          </div>

          <!-- Item rows -->
          <div v-else data-cart-items>
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
        <CartSummary :loading="checkoutLoading" @checkout="handleCheckout" />
      </div>
    </div>
  </div>
</template>
