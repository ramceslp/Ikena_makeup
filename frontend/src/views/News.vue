<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { usePostsStore } from '../stores/posts.js'
import { safeCtaUrl } from '../utils/cta.js'

const postsStore = usePostsStore()

const posts = computed(() => postsStore.posts)
const meta = computed(() => postsStore.postMeta)
const loading = computed(() => postsStore.loading)
const error = computed(() => postsStore.error)

const currentPage = ref(1)

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

function goToPage(page) {
  currentPage.value = page
  postsStore.fetchPosts({ page })
}

onMounted(() => {
  postsStore.fetchPosts({ page: currentPage.value })
})
</script>

<template>
  <div>
    <!-- Page header -->
    <section class="py-16 bg-gradient-to-b from-blush-canvas/20 to-background">
      <div class="max-w-container-max mx-auto px-gutter text-center">
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala mb-3">
          Noticias y Novedades
        </h1>
        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto">
          Mantente al día con las últimas novedades, cursos, ofertas y eventos de Ikena Makeup.
        </p>
      </div>
    </section>

    <div class="max-w-container-max mx-auto px-gutter py-12">
      <!-- Loading -->
      <div v-if="loading" class="text-center py-16">
        <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="text-center py-16">
        <p class="font-body-lg text-body-lg text-on-surface">{{ error }}</p>
      </div>

      <!-- Empty state -->
      <div v-else-if="!posts.length" data-empty-state class="text-center py-16">
        <span class="material-symbols-outlined text-5xl text-blush-canvas mb-4" aria-hidden="true">newspaper</span>
        <p class="font-body-lg text-body-lg text-on-surface-variant">No hay publicaciones disponibles aún.</p>
      </div>

      <!-- Posts grid -->
      <div v-else>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <article
            v-for="post in posts"
            :key="post.id"
            data-post-card
            class="bg-surface rounded-2xl border border-blush-canvas/20 overflow-hidden hover:shadow-md transition-shadow"
          >
            <!-- Cover image -->
            <div class="aspect-video bg-surface-container overflow-hidden">
              <img
                v-if="post.cover_image_url"
                :src="post.cover_image_url"
                :alt="post.title"
                class="w-full h-full object-cover"
              />
              <div
                v-else
                class="w-full h-full flex items-center justify-center"
              >
                <span class="material-symbols-outlined text-4xl text-blush-canvas" aria-hidden="true">image</span>
              </div>
            </div>

            <div class="p-5 flex flex-col gap-3">
              <!-- Type badge -->
              <span
                class="inline-flex w-fit items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                :class="typeBadgeClass[post.type] ?? 'bg-gray-100 text-gray-700'"
              >
                {{ typeLabel[post.type] ?? post.type }}
              </span>

              <!-- Title -->
              <h2 class="font-headline-sm text-headline-sm text-on-surface line-clamp-2">
                {{ post.title }}
              </h2>

              <!-- Excerpt -->
              <p v-if="post.excerpt" class="font-body-sm text-body-sm text-on-surface-variant line-clamp-3">
                {{ post.excerpt }}
              </p>

              <!-- Published date -->
              <time
                v-if="post.published_at"
                class="font-label-sm text-label-sm text-on-surface-variant"
                :datetime="post.published_at"
              >
                {{ new Date(post.published_at).toLocaleDateString('es', { year: 'numeric', month: 'long', day: 'numeric' }) }}
              </time>

              <!-- CTA -->
              <a
                v-if="post.cta_label && safeCtaUrl(post.cta_url)"
                :href="safeCtaUrl(post.cta_url)"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-auto font-label-md text-label-md text-primary hover:underline"
              >
                {{ post.cta_label }}
              </a>
              <RouterLink
                v-else
                :to="`/noticias/${post.slug}`"
                class="mt-auto font-label-md text-label-md text-primary hover:underline"
              >
                Leer más
              </RouterLink>
            </div>
          </article>
        </div>

        <!-- Pagination -->
        <div
          v-if="meta && meta.last_page > 1"
          data-pagination
          class="flex items-center justify-center gap-2 mt-12"
        >
          <button
            v-for="page in meta.last_page"
            :key="page"
            @click="goToPage(page)"
            :disabled="page === meta.current_page"
            class="w-10 h-10 rounded-xl font-label-md text-label-md transition-colors"
            :class="page === meta.current_page
              ? 'bg-primary text-on-primary cursor-default'
              : 'bg-surface border border-blush-canvas/40 text-on-surface hover:bg-surface-container'"
          >
            {{ page }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
