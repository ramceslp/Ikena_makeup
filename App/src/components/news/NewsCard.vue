<script setup>
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
        v-if="post.cover_image_url"
        :src="post.cover_image_url"
        :alt="post.title"
        class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
        loading="lazy"
      />
      <div
        v-else
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
