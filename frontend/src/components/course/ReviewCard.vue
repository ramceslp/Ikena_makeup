<script setup>
// Presentational: renders a single review row.
// Receives a review object; does NOT touch stores or router.
import StarRating from '../ui/StarRating.vue'

const props = defineProps({
  review: {
    type: Object,
    required: true,
    // expected shape: { id, rating, body, created_at, user: { id, name, avatar } }
  },
})

function formatDate(dateStr) {
  try {
    return new Intl.DateTimeFormat('es', { dateStyle: 'medium' }).format(new Date(dateStr))
  } catch {
    return dateStr
  }
}

function initial(name) {
  return name?.charAt(0)?.toUpperCase() ?? '?'
}
</script>

<template>
  <div class="bg-surface-muted rounded-2xl border border-blush-canvas/30 p-5 space-y-3">
    <!-- Author row -->
    <div class="flex items-center gap-3">
      <!-- Avatar -->
      <img
        v-if="review.user?.avatar"
        :src="review.user.avatar"
        :alt="review.user.name"
        class="w-10 h-10 rounded-full object-cover shrink-0"
        loading="lazy"
      />
      <div
        v-else
        class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center shrink-0"
        aria-hidden="true"
      >
        <span class="font-title-md text-title-md text-primary">{{ initial(review.user?.name) }}</span>
      </div>

      <!-- Name + date + stars -->
      <div class="min-w-0 flex-1">
        <p class="font-label-md text-label-md text-deep-marsala leading-tight">
          {{ review.user?.name }}
        </p>
        <div class="flex items-center gap-2 mt-0.5">
          <StarRating :rating="review.rating" size="sm" />
          <span class="font-label-sm text-label-sm text-on-surface-variant">
            {{ formatDate(review.created_at) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Body -->
    <p
      v-if="review.body"
      class="font-body-md text-body-md text-on-surface-variant leading-relaxed"
    >
      {{ review.body }}
    </p>
  </div>
</template>
