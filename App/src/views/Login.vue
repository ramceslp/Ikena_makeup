<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { signInWithGoogle } from '../services/googleAuth.js'

// Ported from frontend/src/views/Login.vue, replacing the web OAuth
// redirect (Google Identity Services script + credential prompt) with the
// native Google Sign-In sheet (see services/googleAuth.js). The app has no
// email/password login -- native Google Sign-In is the sole authentication
// method (see spec's "Native Google Sign-In and bearer session" requirement).
const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const loading = ref(false)
const errorMessage = ref('')

// [DEFECT 2 fix] /login carries meta.hideChrome: true (see router/index.js),
// so neither the top bar nor the bottom tab bar render here -- correct for
// an auth screen, but it leaves the Android back gesture as the ONLY way
// out, which is invisible and fails the gesture-alternative rule (always
// provide a visible control for a critical action).
//
// Does not blindly call router.back(): a user who deep-links straight into
// /login (or whose session expired mid-app and got bounced here by the
// api.js 401 interceptor with no prior *in-app* navigation) has no history
// entry to return to, and an unguarded back() would leave the app instead
// of landing somewhere safe. vue-router's createWebHistory (see
// router/index.js) writes {back, current, forward, ...} onto
// window.history.state on every navigation, so `state.back` being non-null
// is the reliable, already-available signal that a previous in-app screen
// exists to go back to.
//
// Deliberately does NOT reuse route.query.redirect as the close target:
// `redirect` is where the auth guard wants the user to land AFTER a
// successful login (see router/index.js's resolveGuard), not where "cancel
// this login" should go.
function handleClose() {
  if (window.history.state?.back != null) {
    router.back()
  } else {
    router.push({ name: 'home' })
  }
}

async function handleGoogleSignIn() {
  errorMessage.value = ''
  loading.value = true
  try {
    const { idToken } = await signInWithGoogle()
    await authStore.loginWithGoogle(idToken)
    // [Judgment Day fix, PR8d Round 1]: honor the router guard's
    // `redirect` query (see router/index.js's resolveGuard) so a user who
    // direct-linked to an auth-gated route like /profile lands back there
    // after signing in, instead of always bouncing to Home. Matches
    // frontend/src/views/Login.vue's identical `route.query.redirect || '/'`
    // mechanism for consistency between the two codebases.
    await router.push(route.query.redirect || '/')
  } catch (err) {
    if (err?.code === 'USER_CANCELLED') {
      // User dismissed the native sheet -- per spec's "Google Sign-In
      // cancelled" scenario: return to login with no token stored and no
      // crash. loginWithGoogle() above was never reached, so nothing to
      // undo; just fall through to the finally block below.
      return
    }
    console.error('Google Sign-In failed:', err)
    errorMessage.value =
      err.response?.data?.message || 'No se pudo iniciar sesión con Google. Intenta de nuevo.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <!-- Sibling to <main>, not a child of it: this is a navigation control,
       not page content, and Vue 3 templates natively support multiple root
       nodes, so it does not force nesting inside the <main> landmark
       Login.vue already owns (Skill: heading-hierarchy / landmark
       structure -- see AppShell.vue's own comment on avoiding a second
       <main>).
       Positioned with `fixed` + `--safe-area-top` (already defined in
       style.css, floored at 8px via --app-edge-gap) because chrome is
       hidden here -- there is no top bar reserving the status-bar/notch
       inset the way there is on every other screen, so this button has to
       clear it itself (Skill: safe-area-awareness). Capped at z-10: the two
       fixed AppShell bars own z-40 and nothing in view scope may reach
       z-40 or collide with it, even though those bars aren't rendered on
       this route. -->
  <button
    type="button"
    data-login-close
    aria-label="Volver"
    class="fixed left-3 z-10 grid h-12 w-12 touch-manipulation place-items-center rounded-full border border-blush-canvas/30 bg-surface-container-low text-on-surface-variant transition-colors duration-100 ease-out active:bg-surface-container-high focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
    style="top: max(var(--safe-area-top), var(--app-edge-gap))"
    @click="handleClose"
  >
    <span class="material-symbols-outlined text-[24px]" aria-hidden="true">arrow_back</span>
  </button>

  <main class="flex min-h-screen items-center justify-center px-6">
    <div class="w-full max-w-sm space-y-6 text-center">
      <h1 class="font-title-lg text-title-lg text-deep-marsala">Ikena Makeup</h1>

      <div
        v-if="errorMessage"
        role="alert"
        data-login-error
        class="p-3 bg-error-container border border-error/30 rounded-xl text-body-md text-on-error-container"
      >
        {{ errorMessage }}
      </div>

      <button
        type="button"
        data-google-signin
        :disabled="loading"
        class="btn-gloss w-full bg-apricot-glow text-deep-marsala font-bold py-3.5 rounded-xl disabled:opacity-60 disabled:cursor-not-allowed"
        @click="handleGoogleSignIn"
      >
        {{ loading ? 'Ingresando...' : 'Continuar con Google' }}
      </button>
    </div>
  </main>
</template>
