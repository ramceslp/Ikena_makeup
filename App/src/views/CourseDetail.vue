<script setup>
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useCoursesStore } from '../stores/courses.js'
import { formatPrice } from '../utils/formatPrice.js'
import CourseCurriculum from '../components/course/CourseCurriculum.vue'
import BaseBadge from '../components/ui/BaseBadge.vue'
import BaseButton from '../components/ui/BaseButton.vue'

// Read-only course detail keyed by :slug. This app has no /learn lesson
// player, no checkout/enroll flow for courses, and no review UI (see
// stores/courses.js's file-level comment for the full boundary), so this
// view is informational only -- title, curriculum outline, instructor,
// rating -- with no purchase CTA. Mirrors views/ProductDetail.vue's own
// precedent of shipping without a CTA when the underlying action isn't wired
// yet (see that file's header comment), and views/ServiceDetail.vue's
// loading/error/detail composition otherwise.
const route = useRoute()
const coursesStore = useCoursesStore()

const course = computed(() => coursesStore.currentCourse)
const loading = computed(() => coursesStore.loading)
const error = computed(() => coursesStore.error)

// Fetches only on initial mount (same inherited limitation as ServiceDetail.vue/
// ProductDetail.vue) -- a detail->detail navigation reusing this route
// component would not re-fetch. No such navigation exists in this view.
onMounted(async () => {
  try {
    await coursesStore.fetchCourse(route.params.slug)
  } catch {
    // error state is set on the store
  }
})
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter section-y-sm">
    <!-- Loading -->
    <div v-if="loading" data-loading class="state-y flex items-center justify-center">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <!-- Error / 404 -->
    <div v-else-if="error" class="state-y text-center">
      <span class="material-symbols-outlined text-5xl text-error mb-4" aria-hidden="true">error</span>
      <p class="font-body-lg text-body-lg text-on-surface mb-4">{{ error }}</p>
      <RouterLink to="/cursos">
        <BaseButton variant="outline">Volver al catálogo</BaseButton>
      </RouterLink>
    </div>

    <!-- Course Detail -->
    <div v-else-if="course" class="flex flex-col gap-8">
      <!-- Back link -->
      <RouterLink
        to="/cursos"
        data-back-to-catalog
        class="font-label-md text-label-md text-on-surface-variant hover:text-primary flex items-center gap-1 w-fit"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
        Volver al catálogo
      </RouterLink>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        <!-- Thumbnail -->
        <div class="relative aspect-video rounded-2xl overflow-hidden bg-surface-container">
          <img
            v-if="course.thumbnail"
            :src="course.thumbnail"
            :alt="course.title"
            class="w-full h-full object-cover"
          />
          <div
            v-else
            class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blush-canvas/30 to-primary/20"
          >
            <span class="material-symbols-outlined text-[64px] text-primary/40" aria-hidden="true">school</span>
          </div>
        </div>

        <!-- Info -->
        <div class="flex flex-col gap-6">
          <!-- Badges -->
          <div class="flex flex-wrap gap-2">
            <BaseBadge v-if="course.category" variant="secondary">
              {{ course.category.name }}
            </BaseBadge>
            <BaseBadge v-if="course.is_bestseller" variant="accent">
              Más vendido
            </BaseBadge>
            <BaseBadge v-if="course.offers_certificate" variant="blush">
              Con certificado
            </BaseBadge>
            <BaseBadge v-if="course.is_enrolled" variant="primary">
              Ya estás inscrito
            </BaseBadge>
          </div>

          <!-- Title -->
          <h1 class="font-headline-lg text-headline-lg text-deep-marsala">
            {{ course.title }}
          </h1>

          <!-- Instructor + lessons + rating row -->
          <div class="flex flex-wrap items-center gap-5 font-label-md text-label-md text-on-surface-variant">
            <span class="flex items-center gap-1.5">
              <span class="material-symbols-outlined text-[18px]" aria-hidden="true">person</span>
              Por {{ course.instructor?.name }}
            </span>
            <span class="flex items-center gap-1.5">
              <span class="material-symbols-outlined text-[18px]" aria-hidden="true">menu_book</span>
              {{ course.total_lessons }} lecciones
            </span>
            <span v-if="course.average_rating" class="flex items-center gap-1.5">
              <span
                class="material-symbols-outlined text-[18px]"
                aria-hidden="true"
                style="font-variation-settings: 'FILL' 1;"
              >star</span>
              {{ course.average_rating.toFixed(1) }} ({{ course.reviews_count }})
            </span>
          </div>

          <!-- Price -->
          <span class="font-display-lg text-display-lg text-primary">
            {{ formatPrice(course.price) }}
          </span>

          <!-- Description -->
          <div class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
            {{ course.description }}
          </div>
        </div>
      </div>

      <!-- Curriculum -->
      <div>
        <h2 class="font-headline-lg text-headline-lg text-deep-marsala section-header">
          Contenido del curso
        </h2>
        <CourseCurriculum :sections="course.sections ?? []" />
      </div>
    </div>
  </div>
</template>
