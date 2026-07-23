<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

// mobile-capacitor-setup PR 4 — additive web surface that resumes a checkout
// started from the (future) mobile app. See design.md "Checkout Handoff"
// (Decision 1) for the full contract.
//
// Deliberately NOT the shared services/api.js singleton: that instance's
// request interceptor injects Authorization from localStorage('auth_token')
// on every request (see services/api.js). This view runs in a separate,
// logged-out browser session opened from the app link — reusing api.js would
// let a stale/unrelated auth_token in this browser silently override the
// short-lived, ability-scoped confirm_token returned by redeem(), which would
// either break the flow or (worse) send the wrong user's credentials. A
// dedicated axios instance with no interceptors avoids that risk entirely.
const route = useRoute()

const httpClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// sessionStorage key used to carry the confirm_token + order_id across the
// full-page redirect PayPhone performs when the payment box completes
// (responseUrl triggers a real navigation, so in-memory component state does
// not survive it — sessionStorage does, and is cleared as soon as it's used).
const SESSION_KEY = 'ikena_checkout_resume'

const RESTART_SUFFIX = 'Reinicia el proceso de compra desde la aplicación.'

// ── State ────────────────────────────────────────────────────────────────────
const phase = ref('loading') // 'loading' | 'payphone' | 'confirming' | 'success' | 'error' | 'expired'
const errorMessage = ref('')
const boxReady = ref(false)
const clientTransactionId = ref('')

// ── PayPhone asset loader ──────────────────────────────────────────────────
// Adapted/duplicated from Checkout.vue (frontend/src/views/Checkout.vue) per
// the additive-only constraint for this route — Checkout.vue itself is not
// modified so its loader logic cannot be extracted into a shared composable.
const PAYPHONE_CSS = 'https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.css'
const PAYPHONE_JS = 'https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.js'

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

// ── Fragment / session helpers ──────────────────────────────────────────────

// Per design (threat-model note): the token travels in the URL FRAGMENT, not
// a query param, precisely because fragments are never sent to the server —
// read from window.location.hash directly (not vue-router's route.hash).
function readTokenFromFragment() {
  const hash = window.location.hash || ''
  const raw = hash.startsWith('#') ? hash.slice(1) : hash
  const params = new URLSearchParams(raw)
  return params.get('token') || null
}

function persistSession(data) {
  try {
    sessionStorage.setItem(SESSION_KEY, JSON.stringify(data))
  } catch {
    // sessionStorage unavailable (privacy mode, etc.) — confirm step will
    // simply fail gracefully with the generic error state.
  }
}

function readSession() {
  try {
    return JSON.parse(sessionStorage.getItem(SESSION_KEY) || 'null')
  } catch {
    return null
  }
}

function clearSession() {
  try {
    sessionStorage.removeItem(SESSION_KEY)
  } catch {
    // no-op
  }
}

// ── Redeem flow ──────────────────────────────────────────────────────────────

async function redeemToken(token) {
  try {
    const { data } = await httpClient.post('/checkout/handoff/redeem', { token })
    const result = data.data

    clientTransactionId.value = result.config?.clientTransactionId || ''

    persistSession({
      confirm_token: result.confirm_token,
      order_id: result.order_id,
    })

    if (result.provider !== 'payphone') {
      phase.value = 'error'
      errorMessage.value =
        'Este método de pago no está disponible en este flujo. ' + RESTART_SUFFIX
      return
    }

    phase.value = 'payphone'
    await nextTick()

    await injectPayPhoneAssets()
    await waitForConstructor()
    await nextTick()

    const responseUrl = window.location.origin + '/checkout/resume'

    new window.PPaymentButtonBox({
      ...result.config,
      responseUrl,
    }).render('pp-button')

    boxReady.value = true
  } catch (err) {
    handleRedeemError(err)
  }
}

function handleRedeemError(err) {
  const status = err.response?.status
  const backendMessage = err.response?.data?.message

  if (status === 410) {
    phase.value = 'expired'
    errorMessage.value = 'Este enlace de pago ha expirado. ' + RESTART_SUFFIX
    return
  }

  if (status === 409) {
    phase.value = 'expired'
    errorMessage.value = 'Este enlace de pago ya fue utilizado. ' + RESTART_SUFFIX
    return
  }

  if (status === 404) {
    phase.value = 'expired'
    errorMessage.value = 'Este enlace de pago no es válido. ' + RESTART_SUFFIX
    return
  }

  phase.value = 'error'
  errorMessage.value = backendMessage
    ? backendMessage + ' ' + RESTART_SUFFIX
    : 'No se pudo cargar el proceso de pago. ' + RESTART_SUFFIX
}

// ── Confirm flow (return leg of the PayPhone redirect) ──────────────────────

async function confirmPayment(id, clientTxId, confirmToken) {
  phase.value = 'confirming'
  try {
    await httpClient.post(
      '/payments/confirm',
      { id, clientTransactionId: clientTxId },
      { headers: { Authorization: `Bearer ${confirmToken}` } }
    )
    phase.value = 'success'
  } catch (err) {
    phase.value = 'error'
    errorMessage.value =
      (err.response?.data?.message || 'No se pudo confirmar el pago.') + ' ' + RESTART_SUFFIX
  } finally {
    clearSession()
  }
}

// ── Mount ────────────────────────────────────────────────────────────────────

onMounted(async () => {
  const returningId = route.query.id
  const returningClientTransactionId = route.query.clientTransactionId

  // Returning from the payment provider's redirect — confirm using the
  // confirm_token stashed in sessionStorage before the redirect happened.
  if (returningId && returningClientTransactionId) {
    const session = readSession()

    if (!session?.confirm_token) {
      phase.value = 'expired'
      errorMessage.value = 'No se pudo recuperar la sesión de pago. ' + RESTART_SUFFIX
      return
    }

    await confirmPayment(returningId, returningClientTransactionId, session.confirm_token)
    return
  }

  // First visit — redeem the token carried in the URL fragment.
  const token = readTokenFromFragment()

  if (!token) {
    phase.value = 'expired'
    errorMessage.value = 'No se encontró un enlace de pago válido. ' + RESTART_SUFFIX
    return
  }

  await redeemToken(token)
})
</script>

<template>
  <div class="min-h-screen bg-background">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

      <!-- Loading / confirming -->
      <div
        v-if="phase === 'loading' || phase === 'confirming'"
        class="flex flex-col items-center justify-center py-24 gap-4"
      >
        <svg class="animate-spin w-10 h-10 text-apricot-glow" fill="none" viewBox="0 0 24 24" aria-hidden="true">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <p class="text-on-surface-variant text-sm">
          {{ phase === 'confirming' ? 'Confirmando tu pago...' : 'Cargando tu compra...' }}
        </p>
      </div>

      <!-- Expired / reused / invalid / missing token -->
      <div
        v-else-if="phase === 'expired'"
        class="rounded-xl border border-error/30 bg-error-container p-8 text-center"
      >
        <svg class="w-12 h-12 text-error mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-on-error-container font-medium mb-2">Este enlace de pago ya no es válido</p>
        <p class="text-on-error-container/80 text-sm">{{ errorMessage }}</p>
      </div>

      <!-- Generic error -->
      <div
        v-else-if="phase === 'error'"
        class="rounded-xl border border-error/30 bg-error-container p-8 text-center"
      >
        <svg class="w-12 h-12 text-error mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-on-error-container font-medium mb-2">No se pudo completar el pago</p>
        <p class="text-on-error-container/80 text-sm">{{ errorMessage }}</p>
      </div>

      <!-- Success -->
      <div
        v-else-if="phase === 'success'"
        class="bg-surface-container-lowest rounded-2xl border border-blush-canvas/40 p-10 text-center"
      >
        <div class="w-16 h-16 rounded-full bg-apricot-glow flex items-center justify-center mx-auto mb-6" aria-hidden="true">
          <svg class="w-8 h-8 text-deep-marsala" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd"
              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
              clip-rule="evenodd" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-deep-marsala mb-2">Pago confirmado</h1>
        <p class="text-on-surface-variant text-sm">Ya puedes volver a la aplicación.</p>
      </div>

      <!-- PayPhone widget mount point -->
      <div v-else-if="phase === 'payphone'" class="bg-surface-container-lowest rounded-xl border border-blush-canvas/40 p-6">
        <div id="pp-button" />
        <div v-if="!boxReady" class="flex items-center justify-center py-8">
          <svg class="animate-spin w-6 h-6 text-apricot-glow" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>
      </div>

    </div>
  </div>
</template>
