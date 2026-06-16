<script setup>
// Presentational: displays the aggregated review summary + list of ReviewCard.
// Receives all data as props; container owns store wiring.
import StarRating from '../ui/StarRating.vue'
import ReviewCard from './ReviewCard.vue'

defineProps({
  reviews: { type: Array, default: () => [] },
  averageRating: { type: Number, default: null },
  reviewsCount: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
})
</script>

<template>
  <section class="space-y-6">
    <h2 class="font-headline-lg text-headline-lg text-deep-marsala">Valoraciones</h2>

    <!-- Summary header -->
    <div v-if="reviewsCount > 0" class="flex items-center gap-3">
      <span class="font-display-lg text-[40px] text-deep-marsala leading-none font-bold">
        {{ averageRating?.toFixed(1) }}
      </span>
      <div class="flex flex-col gap-1">
        <StarRating :rating="averageRating ?? 0" size="md" />
        <span class="font-label-sm text-label-sm text-on-surface-variant">
          ({{ reviewsCount }} valoracion{{ reviewsCount !== 1 ? 'es' : '' }})
        </span>
      </div>
    </div>

    <!-- Zero-state -->
    <p
      v-else-if="!loading"
      class="font-body-md text-body-md text-on-surface-variant"
    >
      Aún no hay valoraciones.
    </p>

    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-4" aria-label="Cargando valoraciones..." aria-busy="true">
      <div
        v-for="i in 3"
        :key="i"
        class="bg-surface-muted rounded-2xl border border-blush-canvas/30 p-5 animate-pulse space-y-3"
      >
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-surface-container" />
          <div class="flex-1 space-y-2">
            <div class="h-3 bg-surface-container rounded w-1/4" />
            <div class="h-3 bg-surface-container rounded w-1/3" />
          </div>
        </div>
        <div class="h-4 bg-surface-container rounded w-3/4" />
      </div>
    </div>

    <!-- Review cards -->
    <div v-else-if="reviews.length > 0" class="space-y-4">
      <ReviewCard
        v-for="review in reviews"
        :key="review.id"
        :review="review"
      />
    </div>
  </section>
</template>
