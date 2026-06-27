<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCoursesStore } from '../stores/courses.js'

const route = useRoute()
const router = useRouter()
const coursesStore = useCoursesStore()

// ── State ────────────────────────────────────────────────────────────────────
const loading = ref(true)
const error = ref('')
const courseTitle = ref('')
const coursePrice = ref(null)   // amount in cents (integer)
const boxReady = ref(false)
const provider = ref('')              // 'payphone' | 'fake'
const clientTransactionId = ref('')   // needed for the local (fake) confirm flow
const simulating = ref(false)

// ── PayPhone asset loader ─────────────────────────────────────────────────────
const PAYPHONE_CSS = 'https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.css'
const PAYPHONE_JS  = 'https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.js'

function injectPayPhoneAssets() {
  return new Promise((resolve, reject) => {
    // CSS — idempotent: only inject once
    if (!document.querySelector(`link[href="${PAYPHONE_CSS}"]`)) {
      const link = document.createElement('link')
      link.rel = 'stylesheet'
      link.href = PAYPHONE_CSS
      document.head.appendChild(link)
    }

    // JS module — idempotent: only inject once
    if (!document.querySelector(`script[src="${PAYPHONE_JS}"]`)) {
      const script = document.createElement('script')
      script.type = 'module'
      script.src = PAYPHONE_JS
      script.onload = resolve
      script.onerror = () => reject(new Error('No se pudo cargar el módulo de PayPhone'))
      document.head.appendChild(script)
    } else {
      // Script tag already present — resolve immediately (may already be loaded)
      resolve()
    }
  })
}

// Poll until PPaymentButtonBox is available on window (module scripts are async).
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

// ── Local (fake) payment simulation ───────────────────────────────────────────
// Routes through the same callback PayPhone redirects to, so confirmation,
// enrollment and the success screen all reuse the existing, tested flow.
function simulatePayment() {
  simulating.value = true
  router.push({
    path: '/payment/callback',
    query: { id: '1', clientTransactionId: clientTransactionId.value },
  })
}

// ── Mount logic ───────────────────────────────────────────────────────────────
onMounted(async () => {
  const slug = route.params.slug

  try {
    // 1. Call checkout endpoint
    const data = await coursesStore.checkout(slug)
    // data = { order_id, provider, config, course_title?, price? }

    // Capture display data; config is the raw provider checkout config
    const config = data.config
    provider.value = data.provider
    clientTransactionId.value = config.clientTransactionId || ''
    courseTitle.value = config.reference || data.course_title || ''
    coursePrice.value = config.amount ?? null

    // Local/sandbox driver (PAYMENT_DRIVER=fake): PayPhone is not configured.
    // Skip the widget entirely and let the user confirm through the same
    // /payments/confirm flow PayPhone uses (see simulatePayment()).
    if (data.provider !== 'payphone') {
      loading.value = false
      return
    }

    // 2. Load PayPhone assets
    await injectPayPhoneAssets()

    // 3. Wait for constructor to be globally available
    await waitForConstructor()

    // 4. Turn off loading and wait for Vue to mount #pp-button into the DOM.
    //    PayPhone renders with React internally; calling .render() before the
    //    container exists throws React error #299 (target container is not a DOM element).
    loading.value = false
    await nextTick()

    // 5. Build responseUrl
    const responseUrl =
      import.meta.env.VITE_PAYMENT_CALLBACK_URL ||
      (window.location.origin + '/payment/callback')

    // 6. Render the payment box (the #pp-button container now exists)
    new window.PPaymentButtonBox({
      ...config,
      responseUrl,
    }).render('pp-button')

    boxReady.value = true
  } catch (err) {
    const status = err.response?.status

    // 409 = already enrolled → go to player
    if (status === 409) {
      router.replace({ path: `/learn/${slug}`, query: { msg: 'ya_inscrito' } })
      return
    }

    // 422 = free course → use regular enroll flow
    if (status === 422) {
      router.replace({ path: `/courses/${slug}`, query: { msg: 'curso_gratis' } })
      return
    }

    error.value = err.message || coursesStore.error || 'Error al cargar el proceso de pago'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="min-h-screen bg-background">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

      <!-- Loading state -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-24 gap-4">
        <svg class="animate-spin w-10 h-10 text-apricot-glow" fill="none" viewBox="0 0 24 24" aria-hidden="true">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <p class="text-on-surface-variant text-sm">Preparando el pago...</p>
      </div>

      <!-- Error state -->
      <div v-else-if="error" class="rounded-xl border border-error/30 bg-error-container p-8 text-center">
        <!-- Alert icon + color: WCAG compliant -->
        <svg class="w-12 h-12 text-error mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-on-error-container font-medium mb-2">No se pudo cargar el proceso de pago</p>
        <p class="text-on-error-container/80 text-sm mb-6">{{ error }}</p>
        <button
          @click="() => router.go(0)"
          class="btn-gloss bg-apricot-glow text-deep-marsala px-6 py-2 rounded-lg font-semibold hover:opacity-90 transition-opacity text-sm"
        >
          <span class="relative z-[1]">Reintentar</span>
        </button>
      </div>

      <!-- Payment box -->
      <div v-else>
        <!-- Course summary header -->
        <div class="mb-6">
          <RouterLink
            :to="`/courses/${route.params.slug}`"
            class="inline-flex items-center gap-1.5 text-deep-marsala text-sm hover:underline mb-4"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver al curso
          </RouterLink>

          <h1 class="text-2xl font-bold text-deep-marsala mb-1">Completar compra</h1>

          <div v-if="courseTitle || coursePrice !== null"
               class="rounded-xl bg-surface-container-lowest border border-blush-canvas/40 p-5 mt-4 flex items-center justify-between gap-4">
            <p class="text-on-surface font-medium">{{ courseTitle }}</p>
            <p v-if="coursePrice !== null" class="text-deep-marsala font-bold text-lg whitespace-nowrap">
              ${{ (coursePrice / 100).toFixed(2) }}
            </p>
          </div>
        </div>

        <!-- Payment area -->
        <div class="bg-surface-container-lowest rounded-xl border border-blush-canvas/40 p-6">

          <!-- Local/sandbox mode: PayPhone not configured (PAYMENT_DRIVER=fake) -->
          <template v-if="provider !== 'payphone'">
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 mb-5 text-sm text-amber-800">
              Modo de prueba local (sin pasarela real). El pago se simulará y la inscripción se completará igual.
            </div>
            <button
              @click="simulatePayment"
              :disabled="simulating"
              class="btn-gloss w-full bg-apricot-glow text-deep-marsala px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition-opacity disabled:opacity-60"
            >
              <span class="relative z-[1]">{{ simulating ? 'Procesando...' : 'Simular pago y completar compra' }}</span>
            </button>
          </template>

          <!-- PayPhone widget mount point -->
          <template v-else>
            <div id="pp-button" />
            <!-- Fallback while box initialises after assets have loaded -->
            <div v-if="!boxReady" class="flex items-center justify-center py-8">
              <svg class="animate-spin w-6 h-6 text-apricot-glow" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </div>
          </template>
        </div>
      </div>

    </div>
  </div>
</template>
