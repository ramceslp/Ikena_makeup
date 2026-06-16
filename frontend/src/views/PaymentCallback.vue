<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCoursesStore } from '../stores/courses.js'

const route = useRoute()
const router = useRouter()
const coursesStore = useCoursesStore()

// ── State ─────────────────────────────────────────────────────────────────────
const status = ref('loading')   // 'loading' | 'success' | 'error'
const courseSlug = ref('')
const errorMessage = ref('')

// ── On mount: read query params and confirm payment ───────────────────────────
onMounted(async () => {
  const { id, clientTransactionId } = route.query

  if (!id || !clientTransactionId) {
    errorMessage.value = 'Parámetros de pago inválidos o ausentes.'
    status.value = 'error'
    return
  }

  try {
    // POST /payments/confirm → { status, enrolled, course_slug }
    const data = await coursesStore.confirmPayment({
      id: Number(id),
      clientTransactionId,
    })

    courseSlug.value = data.course_slug || ''

    if (data.enrolled) {
      status.value = 'success'
    } else {
      errorMessage.value = 'El pago no fue aprobado. Por favor, intenta de nuevo.'
      status.value = 'error'
    }
  } catch (err) {
    errorMessage.value =
      err.response?.data?.message ||
      coursesStore.error ||
      'Error al confirmar el pago. Intenta de nuevo.'
    status.value = 'error'
  }
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center">

      <!-- Loading / confirming -->
      <div v-if="status === 'loading'" class="flex flex-col items-center gap-4 py-16">
        <svg class="animate-spin w-12 h-12 text-brand-accent" fill="none" viewBox="0 0 24 24" aria-hidden="true">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <p class="text-gray-600">Confirmando tu pago...</p>
      </div>

      <!-- Success -->
      <div v-else-if="status === 'success'"
           class="bg-white rounded-2xl border border-brand-secondary/40 shadow-sm p-10">
        <!-- Check icon + brand-accent: color + icon pair for WCAG -->
        <div class="w-16 h-16 rounded-full bg-brand-accent flex items-center justify-center mx-auto mb-6" aria-hidden="true">
          <svg class="w-8 h-8 text-brand-primary" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd"
              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
              clip-rule="evenodd" />
          </svg>
        </div>

        <h1 class="text-2xl font-bold text-brand-primary mb-2">¡Pago exitoso!</h1>
        <p class="text-gray-600 mb-8">Ya tienes acceso a tu curso. ¡Empieza a aprender!</p>

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
          <RouterLink
            v-if="courseSlug"
            :to="`/learn/${courseSlug}`"
            class="inline-flex items-center justify-center gap-2 bg-brand-accent text-brand-primary px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition-opacity"
          >
            <!-- Play icon -->
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
              <path fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"
                clip-rule="evenodd" />
            </svg>
            Ir al curso
          </RouterLink>

          <RouterLink
            to="/my-courses"
            class="inline-flex items-center justify-center gap-2 border border-brand-primary text-brand-primary px-6 py-3 rounded-lg font-semibold hover:bg-brand-primary/5 transition-colors"
          >
            Mis cursos
          </RouterLink>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="status === 'error'"
           class="bg-white rounded-2xl border border-red-200 shadow-sm p-10">
        <!-- Alert icon + red: color + icon pair for WCAG -->
        <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-6" aria-hidden="true">
          <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>

        <h1 class="text-2xl font-bold text-red-700 mb-2">Pago no completado</h1>
        <p class="text-gray-600 mb-8">{{ errorMessage }}</p>

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
          <!-- Retry: back to checkout if we know the course slug -->
          <RouterLink
            v-if="courseSlug"
            :to="`/checkout/${courseSlug}`"
            class="inline-flex items-center justify-center gap-2 bg-brand-accent text-brand-primary px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition-opacity"
          >
            Reintentar
          </RouterLink>
          <button
            v-else
            @click="router.back()"
            class="inline-flex items-center justify-center gap-2 bg-brand-accent text-brand-primary px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition-opacity"
          >
            Reintentar
          </button>

          <!-- Back to course detail if slug is known -->
          <RouterLink
            v-if="courseSlug"
            :to="`/courses/${courseSlug}`"
            class="inline-flex items-center justify-center gap-2 border border-brand-primary text-brand-primary px-6 py-3 rounded-lg font-semibold hover:bg-brand-primary/5 transition-colors"
          >
            Volver al curso
          </RouterLink>
        </div>
      </div>

    </div>
  </div>
</template>
