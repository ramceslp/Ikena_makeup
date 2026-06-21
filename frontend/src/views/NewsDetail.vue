<script setup>
import { computed, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { usePostsStore } from '../stores/posts.js'
import { safeCtaUrl } from '../utils/cta.js'

const route = useRoute()
const postsStore = usePostsStore()

const post = computed(() => postsStore.currentPost)
const loading = computed(() => postsStore.loading)
const error = computed(() => postsStore.error)

// Color mapping for the 7 post types
const typeBadgeClass = {
  noticia: 'bg-blue-100 text-blue-700',
  nuevo_curso: 'bg-purple-100 text-purple-700',
  oferta: 'bg-amber-100 text-amber-700',
  evento: 'bg-green-100 text-green-700',
  lanzamiento: 'bg-rose-100 text-rose-700',
  certificacion: 'bg-teal-100 text-teal-700',
  contenido: 'bg-gray-100 text-gray-700',
}

const typeLabel = {
  noticia: 'Noticia',
  nuevo_curso: 'Nuevo Curso',
  oferta: 'Oferta',
  evento: 'Evento',
  lanzamiento: 'Lanzamiento',
  certificacion: 'Certificación',
  contenido: 'Contenido',
}

onMounted(async () => {
  try {
    await postsStore.fetchPost(route.params.slug)
  } catch {
    // error state is set on the store
  }
})
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter py-16">
    <!-- Loading -->
    <div v-if="loading" data-loading class="flex items-center justify-center py-32">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <!-- 404 / error state -->
    <div v-else-if="error" data-not-found class="text-center py-32">
      <span class="material-symbols-outlined text-5xl text-error mb-4" aria-hidden="true">error</span>
      <p class="font-body-lg text-body-lg text-on-surface mb-4">{{ error }}</p>
      <RouterLink to="/noticias" class="font-label-md text-label-md text-primary hover:underline">
        Volver a noticias
      </RouterLink>
    </div>

    <!-- Post detail -->
    <article v-else-if="post" class="max-w-3xl mx-auto">
      <!-- Back link -->
      <RouterLink
        to="/noticias"
        class="font-label-md text-label-md text-on-surface-variant hover:text-primary flex items-center gap-1 w-fit mb-8"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
        Volver a noticias
      </RouterLink>

      <!-- Type badge -->
      <span
        data-type-badge
        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold mb-4"
        :class="typeBadgeClass[post.type] ?? 'bg-gray-100 text-gray-700'"
      >
        {{ typeLabel[post.type] ?? post.type }}
      </span>

      <!-- Title -->
      <h1 class="font-headline-lg text-headline-lg text-deep-marsala mb-4">
        {{ post.title }}
      </h1>

      <!-- Meta: author + date -->
      <div class="flex items-center gap-4 mb-8 font-body-sm text-body-sm text-on-surface-variant">
        <span v-if="post.author">{{ post.author }}</span>
        <time v-if="post.published_at" :datetime="post.published_at">
          {{ new Date(post.published_at).toLocaleDateString('es', { year: 'numeric', month: 'long', day: 'numeric' }) }}
        </time>
      </div>

      <!-- Cover image -->
      <div v-if="post.cover_image_url" class="mb-8 rounded-2xl overflow-hidden">
        <img
          data-cover-image
          :src="post.cover_image_url"
          :alt="post.title"
          class="w-full aspect-video object-cover"
        />
      </div>

      <!-- Body (sanitized HTML rendered directly — backend already sanitized) -->
      <div
        data-post-body
        class="prose prose-lg max-w-none font-body-md text-body-md text-on-surface leading-relaxed"
        v-html="post.body"
      />

      <!-- CTA -->
      <div v-if="post.cta_label && safeCtaUrl(post.cta_url)" class="mt-8">
        <a
          :href="safeCtaUrl(post.cta_url)"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-2 bg-apricot-glow text-deep-marsala px-6 py-3 rounded-xl font-label-md text-label-md hover:-translate-y-0.5 transition-all shadow-lg shadow-apricot-glow/20"
        >
          {{ post.cta_label }}
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">open_in_new</span>
        </a>
      </div>
    </article>
  </div>
</template>
