<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useProfileStore } from '../stores/profile.js'
import { useMyCoursesStore } from '../stores/myCourses.js'
import { useAppointmentsStore } from '../stores/appointments.js'
import { usePushStore } from '../stores/push.js'
import { useAuthStore } from '../stores/auth.js'
import api from '../services/api.js'
import PurchaseHistory from '../components/profile/PurchaseHistory.vue'
import MyCoursesSection from '../components/profile/MyCoursesSection.vue'
import AgendaSection from '../components/profile/AgendaSection.vue'

// Ported from frontend/src/views/Profile.vue. The web version's "Información
// personal"/"Seguridad" tabs are intentionally NOT ported: App's auth store
// only supports native Google Sign-In (no email/password account, so there is
// no updateProfile()/changePassword() action to wire a form to).
//
// Three account sections now live here: "Mis cursos" (enrolled courses with
// progress, opening the web lesson player), "Mi agenda" (the customer's own
// appointments, upcoming and past) and the original "Historial de compras".
// The agenda in particular had no server endpoint at all until now —
// appointments were readable only through the admin list.
const router = useRouter()
const profileStore = useProfileStore()
const myCoursesStore = useMyCoursesStore()
const appointmentsStore = useAppointmentsStore()
const pushStore = usePushStore()
const authStore = useAuthStore()

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
    // Fired together, not awaited in sequence: the three sections are
    // independent and each renders its own loading/error state, so serializing
    // them would only make the slowest one gate the other two.
    profileStore.fetchOrders()
    myCoursesStore.fetchMyCourses()
    appointmentsStore.fetchAll()
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
// [Judgment Day fix, PR8d Round 1]: `registered`/`permissionState` alone left
// push.js's recorded `error` (register-call-failed / backend-registration-
// failed / native-registration-failed / permission-check-failed) with no UI
// consumer at all -- a permission-granted-but-registration-failed case would
// otherwise show "Pendiente" forever with zero indication anything actually
// went wrong. `error` takes priority over the pending fallback (a resolved
// failure is more informative than "still pending"), but stays behind the
// success/denied checks so a stale error field from a prior attempt never
// masks a definitively resolved state.
const notificationStatus = computed(() => {
  if (pushStore.registered) return 'Activadas'
  if (pushStore.permissionState === 'denied') return 'Desactivadas'
  if (pushStore.error) return 'Error'
  return 'Pendiente'
})

// ── Logout (destructive-adjacent) ───────────────────────────────────────
// Surfaced ONLY here, in its own spatially-separated "danger zone" card —
// never in the bottom tab bar or next to primary actions (Skill:
// destructive-nav-separation). Requires an explicit inline confirmation
// step before it fires (Skill: confirmation-dialogs) since it ends the
// session. authStore.logout() never rejects and never redirects on its own
// (see stores/auth.js's header comment) — this handler owns the
// post-logout navigation back to /login.
const confirmingLogout = ref(false)
const loggingOut = ref(false)

async function handleLogout() {
  loggingOut.value = true
  try {
    await authStore.logout()
    await router.push('/login')
  } finally {
    loggingOut.value = false
  }
}
</script>

<template>
  <div>
    <div
      v-if="connectivityState === 'error'"
      data-profile-error
      class="flex flex-col items-center gap-4 state-y px-6 text-center"
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
      class="flex flex-col items-center gap-4 state-y px-6 text-center"
    >
      <p class="font-body-lg text-body-lg text-on-surface-variant">
        Cargando...
      </p>
    </div>

    <template v-else-if="connectivityState === 'ok'">
      <section class="section-y bg-background min-h-screen">
        <div class="max-w-container-max mx-auto px-gutter flex flex-col gap-8">
          <div class="flex items-center justify-between flex-wrap gap-3">
            <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Mi perfil</h1>

            <div
              data-notification-status
              class="flex items-center gap-1.5 font-label-md text-label-md text-on-surface-variant"
            >
              <span class="material-symbols-outlined text-[18px]" aria-hidden="true">notifications</span>
              Notificaciones: {{ notificationStatus }}
            </div>
          </div>

          <!-- Mis cursos — first because it is the section a returning
               student opens the app for; the other two are records. -->
          <div data-my-courses-card class="bg-surface rounded-2xl border border-blush-canvas/30 shadow-md shadow-primary/5 p-6">
            <h2 class="font-title-lg text-title-lg text-on-surface mb-6">Mis cursos</h2>
            <MyCoursesSection />
          </div>

          <!-- Mi agenda -->
          <div data-agenda-card class="bg-surface rounded-2xl border border-blush-canvas/30 shadow-md shadow-primary/5 p-6">
            <h2 class="font-title-lg text-title-lg text-on-surface mb-6">Mi agenda</h2>
            <AgendaSection />
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

          <!-- Danger zone: logout lives in its own bordered, danger-toned
               region, well below the history card, never beside primary
               actions or in the tab bar (Skill: destructive-nav-separation). -->
          <div data-danger-zone class="bg-surface rounded-2xl border border-error/30 p-6">
            <h2 class="font-title-lg text-title-lg text-on-surface mb-1">Cerrar sesión</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant mb-4">
              Tendrás que iniciar sesión nuevamente con Google para volver a acceder a tu cuenta.
            </p>

            <button
              v-if="!confirmingLogout"
              type="button"
              data-logout-btn
              class="inline-flex items-center gap-2 min-h-11 px-5 py-3 rounded-xl border-2 border-error text-error font-label-md text-label-md transition-colors hover:bg-error hover:text-on-error focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-error focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
              @click="confirmingLogout = true"
            >
              <span class="material-symbols-outlined text-[20px]" aria-hidden="true">logout</span>
              Cerrar sesión
            </button>

            <!-- Inline confirmation (Skill: confirmation-dialogs) — no overlay/
                 modal needed for a single yes/no choice, which also avoids any
                 z-index conflict with the fixed nav bars. -->
            <div
              v-else
              data-logout-confirm
              role="alertdialog"
              aria-label="Confirmar cierre de sesión"
              class="flex flex-col gap-3"
            >
              <p class="font-body-md text-body-md text-on-surface">
                ¿Seguro que quieres cerrar sesión?
              </p>
              <div class="flex gap-3">
                <button
                  type="button"
                  data-logout-cancel
                  :disabled="loggingOut"
                  class="flex-1 min-h-11 px-4 py-3 rounded-xl border border-outline text-on-surface-variant font-label-md text-label-md transition-colors hover:bg-surface-container-low disabled:opacity-60 disabled:cursor-not-allowed"
                  @click="confirmingLogout = false"
                >
                  Cancelar
                </button>
                <button
                  type="button"
                  data-logout-confirm-btn
                  :disabled="loggingOut"
                  class="flex-1 min-h-11 px-4 py-3 rounded-xl bg-error text-on-error font-label-md text-label-md transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                  @click="handleLogout"
                >
                  {{ loggingOut ? 'Cerrando sesión…' : 'Sí, cerrar sesión' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>
