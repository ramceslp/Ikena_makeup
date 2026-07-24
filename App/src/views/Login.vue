<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { signInWithGoogle } from '../services/googleAuth.js'

// Ported from frontend/src/views/Login.vue, replacing the web OAuth
// redirect (Google Identity Services script + credential prompt) with the
// native Google Sign-In sheet (see services/googleAuth.js). The app has no
// email/password login -- native Google Sign-In is the sole authentication
// method (see spec's "Native Google Sign-In and bearer session" requirement).
const router = useRouter()
const authStore = useAuthStore()

const loading = ref(false)
const errorMessage = ref('')

async function handleGoogleSignIn() {
  errorMessage.value = ''
  loading.value = true
  try {
    const { idToken } = await signInWithGoogle()
    await authStore.loginWithGoogle(idToken)
    await router.push('/')
  } catch (err) {
    if (err?.code === 'USER_CANCELLED') {
      // User dismissed the native sheet -- per spec's "Google Sign-In
      // cancelled" scenario: return to login with no token stored and no
      // crash. loginWithGoogle() above was never reached, so nothing to
      // undo; just fall through to the finally block below.
      return
    }
    errorMessage.value =
      err.response?.data?.message || 'No se pudo iniciar sesión con Google. Intenta de nuevo.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
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
