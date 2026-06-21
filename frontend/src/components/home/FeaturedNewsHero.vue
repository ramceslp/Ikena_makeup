<script setup>
import { ref, onMounted, computed } from 'vue'
import { usePostsStore } from '../../stores/posts.js'
import { safeCtaUrl } from '../../utils/cta.js'

const postsStore = usePostsStore()

const post = ref(null)

onMounted(async () => {
  post.value = await postsStore.fetchFeatured()
})

const ctaHref = computed(() => {
  if (!post.value) return null
  return safeCtaUrl(post.value.cta_url)
})

const slugLink = computed(() => {
  if (!post.value) return null
  return `/noticias/${post.value.slug}`
})
</script>

<template>
  <section data-featured-news-hero class="relative overflow-hidden min-h-[480px] flex items-center bg-surface-muted">
    <!-- Background image when available -->
    <div v-if="post?.cover_image_url" class="absolute inset-0 z-0">
      <div class="absolute inset-0 bg-gradient-to-r from-background via-background/60 to-transparent z-10" />
      <img
        :src="post.cover_image_url"
        alt=""
        aria-hidden="true"
        class="w-full h-full object-cover object-center"
      />
    </div>
    <div v-else class="absolute inset-0 bg-gradient-to-br from-deep-marsala/10 to-blush-canvas/20 z-0" />

    <div class="relative z-20 w-full max-w-container-max mx-auto px-gutter py-20" v-if="post">
      <div class="max-w-2xl space-y-6">
        <!-- Type badge -->
        <div class="inline-flex items-center gap-2">
          <span
            data-type-badge
            class="px-3 py-1 rounded-full font-label-sm text-label-sm bg-blush-canvas/30 text-deep-marsala uppercase tracking-widest border border-blush-canvas/40"
          >
            {{ post.type?.replace(/_/g, ' ') }}
          </span>
        </div>

        <!-- Title -->
        <h2 class="font-display-lg text-display-lg text-primary leading-tight">
          {{ post.title }}
        </h2>

        <!-- Excerpt -->
        <p v-if="post.excerpt" class="font-body-lg text-body-lg text-on-surface-variant max-w-lg">
          {{ post.excerpt }}
        </p>

        <!-- CTA -->
        <div class="flex flex-wrap gap-4 pt-2">
          <a
            v-if="ctaHref"
            :href="ctaHref"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-deep-marsala text-white font-label-lg text-label-lg hover:bg-deep-marsala/90 transition-colors"
          >
            {{ post.cta_label }}
          </a>
          <router-link
            v-else
            :to="slugLink"
            class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-deep-marsala text-white font-label-lg text-label-lg hover:bg-deep-marsala/90 transition-colors"
          >
            Leer más
          </router-link>
        </div>
      </div>
    </div>
  </section>
</template>
