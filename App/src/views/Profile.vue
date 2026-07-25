<script setup>
import { ref, computed, onMounted } from 'vue'
import { useProfileStore } from '../stores/profile.js'
import { usePushStore } from '../stores/push.js'
import api from '../services/api.js'
import PurchaseHistory from '../components/profile/PurchaseHistory.vue'

// Ported from frontend/src/views/Profile.vue, trimmed to the "Historial de
// compras" tab only (mobile-capacitor-setup Phase 8, tasks 8.9-8.10). The
// web version's "Información personal"/"Seguridad" tabs are intentionally
// NOT ported: App's auth store (stores/auth.js) only supports native Google
// Sign-In (no email/password account, no updateProfile()/changePassword()
// actions exist to wire a form to), and spec.md's "Profile and History"
// requirement group is scoped entirely to purchase/appointment history — no
// scenario in this phase covers profile editing or password changes.
const profileStore = useProfileStore()
const pushStore = usePushStore()

// Same three-state connectivity probe as Home.vue/Products.vue/Services.vue
// (Phase 6-7 convention, reused verbatim rather than inventing a new
// offline-detection approach). Required here too: spec.md's Mobile App
// Boundaries explicitly names "history" alongside catalog/booking/cart in
// its "no offline mode" requirement ("The app MUST require live connectivity
// for catalog, booking, cart, and history"), so a genuine no-response
// network failure must render an explicit connectivity error, distinct from
// [Spec: no history yet]'s empty state (which means "reachable, zero rows",
// not "unreachable").
const connectivityState = ref('checking') // 'checking' | 'ok' | 'error'
const isCheckingConnectivity = ref(false)

async function checkConnectivity() {
  if (isCheckingConnectivity.value) return
  isCheckingConnectivity.value = true
  try {
    await api.get('/products', { params: { per_page: 1 } })
    connectivityState.value = 'ok'
  } catch (err) {
    connectivityState.value = err.response ? 'ok' : 'error'
  } finally {
    isCheckingConnectivity.value = false
  }
}

async function init() {
  await checkConnectivity()
  if (connectivityState.value === 'ok') {
    profileStore.fetchOrders()
  }
}

onMounted(init)

// Read-only push-notification status row — resolves PR8c's Round-1 Judgment
// Day follow-up ("PR8d's tasks should explicitly include surfacing
// push.error/permissionState somewhere in the Profile screen, or this
// becomes a third instance of this codebase's 'state set but never
// rendered' bug class, already confirmed twice in PR8a/8b").
//
// Deliberate, scoped decision: display ONLY the existing recorded state
// (push.registered / push.permissionState). NO re-request-permission action
// and NO new push.js behavior are added here. spec.md's "Profile and
// History" requirement group has zero scenarios describing a notification-
// settings action — an interactive re-request-permission flow would be
// unrequested, untested scope creep on this phase's actual spec surface
// (history only), in the very last PR before this whole SDD change merges.
// A read-only status line fully resolves the flagged bug class (the state
// now has a real UI consumer, matching cart.js's persistError/payError
// precedent of "state exists ⇒ someone renders it") without inventing new
// business behavior that has no spec scenario backing it.
const notificationStatus = computed(() => {
  if (pushStore.registered) return 'Activadas'
  if (pushStore.permissionState === 'denied') return 'Desactivadas'
  return 'Pendiente'
})
</script>

<template>
  <div>
    <div
      v-if="connectivityState === 'error'"
      data-profile-error
      class="flex flex-col items-center gap-4 py-24 px-6 text-center"
    >
      <p class="font-body-lg text-body-lg text-on-surface-variant">
        No pudimos cargar tu perfil. Verifica tu conexión e intenta de nuevo.
      </p>
      <button
        type="button"
        data-profile-retry
        :disabled="isCheckingConnectivity"
        class="btn-gloss px-6 py-3 rounded-xl bg-apricot-glow text-deep-marsala font-bold disabled:opacity-60 disabled:cursor-not-allowed"
        @click="init"
      >
        Reintentar
      </button>
    </div>

    <div
      v-else-if="connectivityState === 'checking'"
      data-profile-checking
      class="flex flex-col items-center gap-4 py-24 px-6 text-center"
    >
      <p class="font-body-lg text-body-lg text-on-surface-variant">
        Cargando...
      </p>
    </div>

    <template v-else-if="connectivityState === 'ok'">
      <section class="py-12 bg-background min-h-screen">
        <div class="max-w-container-max mx-auto px-gutter">
          <div class="mb-8 flex items-center justify-between flex-wrap gap-3">
            <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Mi perfil</h1>

            <div
              data-notification-status
              class="flex items-center gap-1.5 font-label-md text-label-md text-on-surface-variant"
            >
              <span class="material-symbols-outlined text-[18px]" aria-hidden="true">notifications</span>
              Notificaciones: {{ notificationStatus }}
            </div>
          </div>

          <div class="bg-surface rounded-2xl border border-blush-canvas/30 shadow-md shadow-primary/5 p-6">
            <h2 class="font-title-lg text-title-lg text-on-surface mb-6">Historial de compras</h2>
            <PurchaseHistory
              :orders="profileStore.orders"
              :loading="profileStore.loading"
              :error="profileStore.error"
              @retry="profileStore.fetchOrders()"
            />
          </div>
        </div>
      </section>
    </template>
  </div>
</template>
