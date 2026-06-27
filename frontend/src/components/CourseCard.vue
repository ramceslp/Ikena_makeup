<script setup>
import { useRouter } from 'vue-router'
import BaseBadge from './ui/BaseBadge.vue'
import BaseButton from './ui/BaseButton.vue'
import StarRating from './ui/StarRating.vue'

const props = defineProps({
  course: {
    type: Object,
    required: true,
  },
})

const router = useRouter()

function goToCourse() {
  router.push(`/courses/${props.course.slug}`)
}

function formatPrice(price) {
  const num = parseFloat(price)
  if (!num || num === 0) return 'Gratis'
  return `$${num.toFixed(2)}`
}

function excerpt(text, length = 100) {
  if (!text) return ''
  return text.length > length ? text.slice(0, length) + '...' : text
}

function isFree(price) {
  const num = parseFloat(price)
  return !num || num === 0
}
</script>

<template>
  <div
    @click="goToCourse"
    class="group flex flex-col bg-surface-muted rounded-2xl overflow-hidden border border-blush-canvas/30 cursor-pointer shadow-md shadow-primary/5 transition-all duration-500 hover:shadow-xl hover:shadow-primary/10 hover:-translate-y-0.5"
  >
    <!-- Thumbnail -->
    <div class="relative aspect-[16/9] overflow-hidden bg-surface-container">
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
        <svg class="w-12 h-12 text-primary/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"
          />
        </svg>
      </div>

      <!-- Enrolled badge (real data, not color-only: includes label) -->
      <div v-if="course.is_enrolled" class="absolute top-4 left-4">
        <BaseBadge variant="accent" pill>
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">check_circle</span>
          Inscrito
        </BaseBadge>
      </div>

      <!-- Ribbons: top-right corner, stacked vertically -->
      <div class="absolute top-4 right-4 flex flex-col items-end gap-1">
        <BaseBadge v-if="course.is_bestseller" variant="accent" pill>
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">trending_up</span>
          Bestseller
        </BaseBadge>
        <BaseBadge v-if="course.offers_certificate" variant="secondary" pill>
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">workspace_premium</span>
          Certificado
        </BaseBadge>
      </div>
    </div>

    <!-- Content -->
    <div class="p-6 flex flex-col flex-grow">
      <!-- Meta row -->
      <div class="flex flex-wrap items-center gap-4 mb-3 text-outline">
        <!-- Category label — only when present -->
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
        <span class="font-label-sm text-label-sm flex items-center gap-1">
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">layers</span>
          {{ course.sections_count }} secciones
        </span>
        <!-- Star rating: only when course has reviews -->
        <span
          v-if="course.reviews_count > 0"
          class="font-label-sm text-label-sm flex items-center gap-1"
        >
          <StarRating :rating="course.average_rating ?? 0" size="sm" />
          <span>{{ course.average_rating?.toFixed(1) }}</span>
          <span>({{ course.reviews_count }})</span>
        </span>
      </div>

      <h3 class="font-title-md text-title-md text-deep-marsala mb-2 group-hover:text-primary transition-colors line-clamp-2">
        {{ course.title }}
      </h3>
      <p class="font-body-md text-body-md text-on-surface-variant mb-6 line-clamp-2 flex-grow">
        {{ excerpt(course.description, 120) }}
      </p>

      <!-- Footer: instructor + price + CTA -->
      <div class="mt-auto border-t border-blush-canvas/20 pt-4 flex items-center justify-between">
        <div class="flex flex-col">
          <span class="font-label-sm text-label-sm text-outline">Por {{ course.instructor?.name }}</span>
          <span class="font-title-md text-title-md text-primary flex items-center gap-2">
            <BaseBadge v-if="isFree(course.price)" variant="secondary">Gratis</BaseBadge>
            <template v-else>{{ formatPrice(course.price) }}</template>
          </span>
        </div>
        <BaseButton variant="primary" size="sm">Ver detalles</BaseButton>
      </div>
    </div>
  </div>
</template>
