<script setup>
import BaseBadge from '../ui/BaseBadge.vue'
import BaseButton from '../ui/BaseButton.vue'
import { formatPrice } from '../../utils/formatPrice.js'

// Catalog card for a single course. Mirrors components/service/ServiceCard.vue's
// composition (thumbnail + meta row + title + excerpt + footer), adapted to
// the fields CourseCardResource actually returns (lessons_count instead of
// duration_hours, average_rating/reviews_count instead of availability_type
// -- see backend/app/Http/Resources/CourseCardResource.php).
const props = defineProps({
  course: {
    type: Object,
    required: true,
  },
})

function excerpt(text, length = 100) {
  if (!text) return ''
  return text.length > length ? text.slice(0, length) + '...' : text
}
</script>

<template>
  <RouterLink
    :to="`/cursos/${course.slug}`"
    data-course-card
    class="group flex flex-col bg-surface-muted rounded-2xl overflow-hidden border border-blush-canvas/30 shadow-md shadow-primary/5 transition-all duration-500 hover:shadow-xl hover:shadow-primary/10 hover:-translate-y-0.5 no-underline"
  >
    <!-- Thumbnail -->
    <div class="relative aspect-video overflow-hidden bg-surface-container">
      <img
        v-if="course.thumbnail"
        :src="course.thumbnail"
        :alt="course.title"
        class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
        loading="lazy"
      />
      <div
        v-else
        class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blush-canvas/30 to-primary/20"
      >
        <span class="material-symbols-outlined text-4xl text-primary/50" aria-hidden="true">school</span>
      </div>

      <!-- Bestseller badge overlays the thumbnail -->
      <BaseBadge v-if="course.is_bestseller" variant="accent" class="absolute top-3 left-3">
        Más vendido
      </BaseBadge>
    </div>

    <!-- Content -->
    <div class="p-6 flex flex-col flex-grow">
      <!-- Meta row -->
      <div class="flex flex-wrap items-center gap-3 mb-3 text-outline">
        <span
          v-if="course.category"
          class="font-label-sm text-label-sm flex items-center gap-1 text-on-surface-variant"
        >
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">sell</span>
          {{ course.category.name }}
        </span>

        <span class="font-label-sm text-label-sm flex items-center gap-1">
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">menu_book</span>
          {{ course.lessons_count }} lecciones
        </span>

        <span v-if="course.average_rating" class="font-label-sm text-label-sm flex items-center gap-1">
          <span
            class="material-symbols-outlined text-[14px]"
            aria-hidden="true"
            style="font-variation-settings: 'FILL' 1;"
          >star</span>
          {{ course.average_rating.toFixed(1) }} ({{ course.reviews_count }})
        </span>
      </div>

      <h3 class="font-title-md text-title-md text-deep-marsala mb-2 group-hover:text-primary transition-colors line-clamp-2">
        {{ course.title }}
      </h3>
      <p class="font-body-md text-body-md text-on-surface-variant mb-6 line-clamp-2 flex-grow">
        {{ excerpt(course.description, 120) }}
      </p>

      <!-- Footer: price + CTA -->
      <div class="mt-auto border-t border-blush-canvas/20 pt-4 flex items-center justify-between">
        <span class="font-title-md text-title-md text-primary">
          {{ formatPrice(course.price) }}
        </span>
        <BaseButton variant="primary" size="sm">Ver Curso</BaseButton>
      </div>
    </div>
  </RouterLink>
</template>
