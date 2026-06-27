<script setup>
// Purely presentational: receives course data + event callbacks.
// The container (CourseDetail.vue) owns store actions and auth state.
import BaseButton from '../ui/BaseButton.vue'
import BaseBadge from '../ui/BaseBadge.vue'
import StarRating from '../ui/StarRating.vue'

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
  <!-- Hero: dark primary band matching Stitch layout -->
  <section class="relative overflow-hidden bg-primary text-on-primary">
    <!-- Warm brand bloom over the dark marsala band -->
    <div class="makeup-mesh absolute inset-0 opacity-20" aria-hidden="true" />
    <div class="relative z-10 max-w-container-max mx-auto px-gutter py-10 md:py-14">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 items-start">

        <!-- Left column: info (2/3 width on lg) -->
        <div class="lg:col-span-2 space-y-6">

          <!-- Title -->
          <h1 class="font-display-lg text-headline-lg md:text-display-lg text-on-primary leading-tight">
            {{ course.title }}
          </h1>

          <!-- Short description -->
          <p class="font-body-lg text-body-lg text-on-primary/80 leading-relaxed">
            {{ course.description }}
          </p>

          <!-- Meta row: instructor + lesson count + rating (when available) -->
          <div class="flex flex-wrap items-center gap-5 font-label-md text-label-md text-on-primary/70">
            <span class="flex items-center gap-1.5">
              <span class="material-symbols-outlined text-[18px]" aria-hidden="true">person</span>
              Por {{ course.instructor?.name }}
            </span>
            <span class="flex items-center gap-1.5">
              <span class="material-symbols-outlined text-[18px]" aria-hidden="true">menu_book</span>
              {{ course.total_lessons }} lecciones
            </span>
            <!-- Stars: only when there are reviews -->
            <span v-if="course.reviews_count > 0" class="flex items-center gap-1.5">
              <StarRating :rating="course.average_rating ?? 0" size="sm" />
              <span>{{ course.average_rating?.toFixed(1) }}</span>
              <span>({{ course.reviews_count }})</span>
            </span>
          </div>

          <!-- Price -->
          <div class="font-display-lg text-[32px] text-on-primary font-bold">
            {{ formatPrice(course.price) }}
          </div>

          <!-- Enroll error -->
          <div
            v-if="enrollError"
            class="flex items-center gap-2 text-error-container font-body-md text-body-md"
            role="alert"
          >
            <span class="material-symbols-outlined text-[18px] shrink-0" aria-hidden="true">error</span>
            {{ enrollError }}
          </div>

          <!-- CTA buttons -->
          <div class="flex flex-wrap gap-3">
            <!-- Not authenticated -->
            <RouterLink
              v-if="!isAuthenticated"
              to="/login"
              class="btn-gloss inline-flex items-center gap-2 bg-apricot-glow text-deep-marsala px-8 py-4 rounded-xl font-title-md text-title-md font-bold shadow-lg shadow-apricot-glow/20 hover:-translate-y-0.5 active:scale-95 transition-all"
            >
              <span class="relative z-[1]">Inicia sesión para inscribirte</span>
            </RouterLink>

            <!-- Enrolled → go to player -->
            <RouterLink
              v-else-if="course.is_enrolled"
              :to="`/learn/${course.slug}`"
              class="btn-gloss inline-flex items-center gap-2 bg-apricot-glow text-deep-marsala px-8 py-4 rounded-xl font-title-md text-title-md font-bold shadow-lg shadow-apricot-glow/20 hover:-translate-y-0.5 active:scale-95 transition-all"
            >
              <span class="relative z-[1] inline-flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">play_circle</span>
                Ir al curso
              </span>
            </RouterLink>

            <!-- Not enrolled + paid → checkout -->
            <BaseButton
              v-else-if="isPaid(course.price)"
              variant="primary"
              @click="emit('buy')"
            >
              <span class="material-symbols-outlined text-[18px]" aria-hidden="true">shopping_cart</span>
              Comprar curso
            </BaseButton>

            <!-- Not enrolled + free → direct enroll -->
            <BaseButton
              v-else
              variant="primary"
              :disabled="enrolling"
              @click="emit('enroll')"
            >
              <svg v-if="enrolling" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              {{ enrolling ? 'Inscribiendo...' : 'Inscribirme gratis' }}
            </BaseButton>
          </div>
        </div>

        <!-- Right column: course thumbnail (1/3 width on lg) -->
        <div class="relative aspect-video rounded-xl overflow-hidden bg-primary-container shadow-2xl lg:col-span-1">
          <img
            v-if="course.thumbnail"
            :src="course.thumbnail"
            :alt="course.title"
            class="w-full h-full object-cover"
          />
          <!-- Placeholder when no thumbnail -->
          <div
            v-else
            class="w-full h-full flex items-center justify-center"
          >
            <span class="material-symbols-outlined text-[64px] text-on-primary/30" aria-hidden="true">
              play_lesson
            </span>
          </div>
        </div>

      </div>
    </div>
  </section>
</template>
