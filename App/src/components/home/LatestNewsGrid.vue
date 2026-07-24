<script setup>
import { ref, onMounted } from 'vue'
import { usePostsStore } from '../../stores/posts.js'
import { safeCtaUrl } from '../../utils/cta.js'

const postsStore = usePostsStore()

const posts = ref([])

onMounted(async () => {
  posts.value = (await postsStore.fetchLatest()) ?? []
})

function getCtaHref(post) {
  return safeCtaUrl(post.cta_url)
}

function getSlugLink(post) {
  return `/noticias/${post.slug}`
}
</script>

<template>
  <section data-latest-news-grid class="py-20 bg-background">
    <div class="max-w-container-max mx-auto px-gutter">
      <!-- Section header -->
      <div class="flex items-end justify-between mb-10">
        <div>
          <p class="font-label-sm text-label-sm text-primary uppercase tracking-widest mb-2">
            Últimas Noticias
          </p>
          <h2 class="font-headline-lg text-headline-lg text-deep-marsala">
            Actualidad &amp; Novedades
          </h2>
        </div>
        <router-link
          to="/noticias"
          class="font-label-lg text-label-lg text-primary hover:text-deep-marsala transition-colors flex items-center gap-1"
        >
          Ver más noticias
          <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
        </router-link>
      </div>

      <!-- Posts grid -->
      <div v-if="posts.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <article
          v-for="post in posts"
          :key="post.id"
          data-news-card
          class="group flex flex-col rounded-2xl overflow-hidden border border-blush-canvas/20 bg-surface hover:shadow-md transition-shadow"
        >
          <!-- Cover image -->
          <div class="aspect-video bg-blush-canvas/10 overflow-hidden">
            <img
              v-if="post.cover_image_url"
              :src="post.cover_image_url"
              :alt="post.title"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
              <span class="material-symbols-outlined text-4xl text-blush-canvas/40" aria-hidden="true">article</span>
            </div>
          </div>

          <!-- Card body -->
          <div class="flex flex-col flex-grow p-5 space-y-3">
            <!-- Type badge -->
            <span
              data-type-badge
              class="self-start px-2 py-0.5 rounded-full font-label-sm text-label-sm bg-blush-canvas/20 text-deep-marsala border border-blush-canvas/30 uppercase tracking-widest"
            >
              {{ post.type?.replace(/_/g, ' ') }}
            </span>

            <!-- Title -->
            <h3 class="font-title-lg text-title-lg text-on-surface leading-snug">
              {{ post.title }}
            </h3>

            <!-- Excerpt -->
            <p v-if="post.excerpt" class="font-body-md text-body-md text-on-surface-variant line-clamp-2 flex-grow">
              {{ post.excerpt }}
            </p>

            <!-- CTA -->
            <div class="pt-2">
              <a
                v-if="getCtaHref(post) && post.cta_label"
                :href="getCtaHref(post)"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 font-label-md text-label-md text-primary hover:text-deep-marsala transition-colors"
              >
                {{ post.cta_label }}
                <span class="material-symbols-outlined text-sm" aria-hidden="true">open_in_new</span>
              </a>
              <router-link
                v-else
                :to="getSlugLink(post)"
                class="inline-flex items-center gap-1 font-label-md text-label-md text-primary hover:text-deep-marsala transition-colors"
              >
                Leer más
                <span class="material-symbols-outlined text-sm" aria-hidden="true">arrow_forward</span>
              </router-link>
            </div>
          </div>
        </article>
      </div>

      <!-- Empty state -->
      <div v-else data-news-empty class="text-center py-12">
        <p class="font-body-lg text-body-lg text-on-surface-variant">
          Próximamente nuevas noticias.
        </p>
      </div>
    </div>
  </section>
</template>
