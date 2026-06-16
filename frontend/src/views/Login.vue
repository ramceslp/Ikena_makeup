<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import AuthLayout from '../components/auth/AuthLayout.vue'
import BaseInput from '../components/ui/BaseInput.vue'
import GoogleButton from '../components/auth/GoogleButton.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const form = ref({ email: '', password: '' })
const errors = ref({})
const serverError = ref('')
const loading = ref(false)
const googleLoading = ref(false)

const googleClientId = import.meta.env.VITE_GOOGLE_CLIENT_ID
const googleEnabled = computed(() => !!googleClientId)

function validate() {
  errors.value = {}
  if (!form.value.email) {
    errors.value.email = 'El correo es requerido'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) {
    errors.value.email = 'Ingresa un correo válido'
  }
  if (!form.value.password) {
    errors.value.password = 'La contraseña es requerida'
  }
  return Object.keys(errors.value).length === 0
}

async function handleSubmit() {
  serverError.value = ''
  if (!validate()) return

  loading.value = true
  try {
    await authStore.login(form.value)
    const redirect = route.query.redirect || '/'
    router.push(redirect)
  } catch (err) {
    const data = err.response?.data
    if (data?.errors) {
      errors.value = data.errors
    } else {
      serverError.value = data?.message || 'Credenciales incorrectas. Intenta de nuevo.'
    }
  } finally {
    loading.value = false
  }
}

function handleGoogleLogin() {
  if (!googleEnabled.value) return

  googleLoading.value = true
  const script = document.createElement('script')
  script.src = 'https://accounts.google.com/gsi/client'
  script.async = true
  document.head.appendChild(script)
  script.onload = () => {
    window.google.accounts.id.initialize({
      client_id: googleClientId,
      callback: async (response) => {
        try {
          await authStore.loginWithGoogle(response.credential)
          const redirect = route.query.redirect || '/'
          router.push(redirect)
        } catch (err) {
          serverError.value = err.response?.data?.message || 'Error al iniciar sesión con Google'
        } finally {
          googleLoading.value = false
        }
      },
    })
    window.google.accounts.id.prompt()
  }
  script.onerror = () => {
    serverError.value = 'No se pudo cargar Google Sign-In'
    googleLoading.value = false
  }
}
</script>

<template>
  <AuthLayout>
    <!-- Server error alert -->
    <div
      v-if="serverError"
      role="alert"
      class="mb-5 p-3 bg-error-container border border-error/30 rounded-xl font-body-md text-body-md text-on-error-container flex items-start gap-2"
    >
      <span class="material-symbols-outlined text-[18px] shrink-0 mt-0.5" aria-hidden="true">
        error
      </span>
      {{ serverError }}
    </div>

    <!-- Login form -->
    <form class="space-y-5" @submit.prevent="handleSubmit">
      <BaseInput
        v-model="form.email"
        id="login-email"
        type="email"
        label="CORREO ELECTRÓNICO"
        placeholder="tu@academia.com"
        autocomplete="email"
        :error="errors.email"
      />

      <BaseInput
        v-model="form.password"
        id="login-password"
        type="password"
        label="CONTRASEÑA"
        placeholder="••••••••"
        autocomplete="current-password"
        :revealable="true"
        :error="errors.password"
      />

      <!-- Submit button -->
      <button
        type="submit"
        :disabled="loading"
        class="w-full bg-apricot-glow text-deep-marsala font-bold py-3.5 rounded-xl shadow-lg shadow-apricot-glow/20 hover:scale-[1.02] active:scale-[0.98] transition-all font-title-md text-title-md flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed"
      >
        <svg
          v-if="loading"
          class="animate-spin w-5 h-5 shrink-0"
          fill="none"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        {{ loading ? 'Ingresando...' : 'Iniciar sesión' }}
      </button>
    </form>

    <!-- Google button in oauth slot -->
    <template #oauth>
      <GoogleButton
        :loading="googleLoading"
        :disabled="!googleEnabled || googleLoading"
        :title="!googleEnabled ? 'Configura VITE_GOOGLE_CLIENT_ID para activar Google Sign-In' : ''"
        @click="handleGoogleLogin"
      />
    </template>
  </AuthLayout>
</template>
