<script setup>
// Sticky CTA sidebar — price block + enroll/buy actions.
// Mirrors the Stitch design: sticky positioned card with price + CTA buttons.
// Purely presentational: all actions are emitted to the container.
import BaseButton from '../ui/BaseButton.vue'

const props = defineProps({
  course: { type: Object, required: true },
  isAuthenticated: { type: Boolean, default: false },
  enrolling: { type: Boolean, default: false },
  enrollError: { type: String, default: '' },
})

const emit = defineEmits(['enroll', 'buy'])

function formatPrice(price) {
  const num = parseFloat(price)
  if (!num || num === 0) return 'Gratis'
  return `$${num.toFixed(2)}`
}

function isPaid(price) {
  return parseFloat(price) > 0
}
</script>

<template>
  <!-- sticky-card: same sticky offset as Stitch (top: 100px) -->
  <aside
    class="sticky top-24 bg-surface-container-lowest rounded-3xl shadow-2xl shadow-primary/5 border border-blush-canvas/10 p-8 flex flex-col gap-6"
    aria-label="Resumen del curso"
  >
    <!-- Price -->
    <div>
      <div class="font-display-lg text-display-lg text-deep-marsala font-bold leading-none">
        {{ formatPrice(course.price) }}
      </div>
    </div>

    <!-- Enroll error -->
    <p
      v-if="enrollError"
      class="flex items-center gap-2 font-label-md text-label-md text-error"
      role="alert"
    >
      <span class="material-symbols-outlined text-[16px] shrink-0" aria-hidden="true">error</span>
      {{ enrollError }}
    </p>

    <!-- CTA -->
    <div class="flex flex-col gap-3">
      <!-- Not authenticated -->
      <RouterLink
        v-if="!isAuthenticated"
        to="/login"
        class="w-full text-center bg-apricot-glow text-deep-marsala font-title-md text-title-md font-bold py-5 rounded-2xl shadow-lg shadow-apricot-glow/30 hover:scale-[1.02] active:scale-95 transition-all duration-200 uppercase tracking-wide"
      >
        Inicia sesión
      </RouterLink>

      <!-- Enrolled -->
      <RouterLink
        v-else-if="course.is_enrolled"
        :to="`/learn/${course.slug}`"
        class="w-full text-center bg-apricot-glow text-deep-marsala font-title-md text-title-md font-bold py-5 rounded-2xl shadow-lg shadow-apricot-glow/30 hover:scale-[1.02] active:scale-95 transition-all duration-200 uppercase tracking-wide flex items-center justify-center gap-2"
      >
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">play_circle</span>
        Ir al curso
      </RouterLink>

      <!-- Not enrolled + paid -->
      <button
        v-else-if="isPaid(course.price)"
        class="w-full bg-apricot-glow text-deep-marsala font-title-md text-title-md font-bold py-5 rounded-2xl shadow-lg shadow-apricot-glow/30 hover:scale-[1.02] active:scale-95 transition-all duration-200 uppercase tracking-wide flex items-center justify-center gap-2"
        @click="emit('buy')"
      >
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">shopping_cart</span>
        Comprar curso
      </button>

      <!-- Not enrolled + free -->
      <button
        v-else
        :disabled="enrolling"
        class="w-full bg-apricot-glow text-deep-marsala font-title-md text-title-md font-bold py-5 rounded-2xl shadow-lg shadow-apricot-glow/30 hover:scale-[1.02] active:scale-95 transition-all duration-200 uppercase tracking-wide flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed"
        @click="emit('enroll')"
      >
        <svg v-if="enrolling" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24" aria-hidden="true">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        {{ enrolling ? 'Inscribiendo...' : 'Inscribirme gratis' }}
      </button>
    </div>

    <!-- What's included — static info (all courses have lessons; no backend fields for extras) -->
    <div class="border-t border-blush-canvas/20 pt-6 space-y-3">
      <p class="font-label-md text-label-md text-deep-marsala uppercase">
        Este curso incluye:
      </p>
      <ul class="space-y-2">
        <li class="flex items-center gap-3 font-body-md text-body-md text-on-surface-variant">
          <span class="material-symbols-outlined text-primary text-[20px]" aria-hidden="true">menu_book</span>
          {{ course.total_lessons }} lecciones
        </li>
        <li class="flex items-center gap-3 font-body-md text-body-md text-on-surface-variant">
          <span class="material-symbols-outlined text-primary text-[20px]" aria-hidden="true">all_inclusive</span>
          Acceso de por vida
        </li>
      </ul>
    </div>
  </aside>
</template>
