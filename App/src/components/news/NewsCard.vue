<script setup>
import { ref } from 'vue'
import BaseBadge from '../ui/BaseBadge.vue'

// Catalog card for a single news post. Mirrors components/course/CourseCard.vue's
// composition (cover + meta row + title + excerpt), adapted to the fields
// PostCardResource returns (type/published_at instead of price/rating --
// see backend/app/Http/Resources/PostCardResource.php).
defineProps({
  post: {
    type: Object,
    required: true,
  },
})

const typeLabel = {
  noticia: 'Noticia',
  nuevo_curso: 'Nuevo curso',
  oferta: 'Oferta',
  evento: 'Evento',
  lanzamiento: 'Lanzamiento',
  certificacion: 'Certificación',
  contenido: 'Contenido',
}

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('es-EC', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

function truncate(text, length = 120) {
  if (!text) return ''
  return text.length > length ? `${text.slice(0, length)}...` : text
}

// A cover_image_url that is present but fails to LOAD is a different case
// from one that is absent, and it looks far worse: the browser renders its
// broken-image glyph plus the alt text, which overflows the thumbnail and
// collides with the "Destacada" badge. Seen for real on the emulator when a
// seeded placeholder host returned a 500.
//
// Falling back to the same neutral block used when there is no cover at all
// makes a dead image indistinguishable from no image, which is the right
// outcome — a reader gains nothing from knowing the fetch failed.
const coverFailed = ref(false)
</script>

<template>
  <RouterLink
    :to="`/noticias/${post.slug}`"
    data-news-card
    class="group flex flex-col bg-surface-muted rounded-2xl overflow-hidden border border-blush-canvas/30 shadow-md shadow-primary/5 transition-all duration-500 hover:shadow-xl hover:shadow-primary/10 hover:-translate-y-0.5 no-underline"
  >
    <!-- Cover -->
    <div class="relative aspect-video overflow-hidden bg-surface-container">
      <img
        v-if="post.cover_image_url && !coverFailed"
        :src="post.cover_image_url"
        :alt="post.title"
        data-cover-image
        class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
        loading="lazy"
        @error="coverFailed = true"
      />
      <div
        v-else
        data-cover-placeholder
        class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blush-canvas/30 to-primary/20"
      >
        <span class="material-symbols-outlined text-4xl text-primary/50" aria-hidden="true">newspaper</span>
      </div>

      <BaseBadge v-if="post.is_featured" variant="accent" class="absolute top-3 left-3">
        Destacada
      </BaseBadge>
    </div>

    <!-- Content -->
    <div class="p-6 flex flex-col flex-grow">
      <div class="flex flex-wrap items-center gap-3 mb-3">
        <span class="font-label-sm text-label-sm text-primary">
          {{ typeLabel[post.type] || post.type }}
        </span>
        <span
          v-if="post.published_at"
          data-published-at
          class="font-label-sm text-label-sm text-on-surface-variant"
        >
          {{ formatDate(post.published_at) }}
        </span>
      </div>

      <h3 class="font-title-md text-title-md text-on-surface mb-2 group-hover:text-primary transition-colors">
        {{ post.title }}
      </h3>

      <p v-if="post.excerpt" class="font-body-md text-body-md text-on-surface-variant">
        {{ truncate(post.excerpt) }}
      </p>
    </div>
  </RouterLink>
</template>
