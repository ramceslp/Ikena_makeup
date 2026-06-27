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
    <div v-if="post?.cover_image_url" class="absolute inset-0 z-0 overflow-hidden">
      <img
        :src="post.cover_image_url"
        alt=""
        aria-hidden="true"
        class="w-full h-full object-cover object-center"
      />
      <!-- Legibility veil (left → right) -->
      <div class="absolute inset-0 bg-gradient-to-r from-background via-background/60 to-transparent z-10" />
      <!-- Warm highlighter bloom over the photo -->
      <div class="makeup-mesh absolute inset-0 z-10 opacity-40 mix-blend-screen" aria-hidden="true" />
      <!-- Depth vignette anchoring the content -->
      <div class="absolute inset-0 bg-gradient-to-t from-deep-marsala/25 via-transparent to-transparent z-10" />
    </div>
    <!-- Fallback: signature gradient mesh -->
    <div v-else class="absolute inset-0 z-0 bg-surface-muted overflow-hidden">
      <div class="makeup-mesh absolute -inset-[10%]" aria-hidden="true" />
    </div>

    <div data-hero-content class="relative z-20 w-full max-w-container-max mx-auto px-gutter py-20" v-if="post">
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
            v-if="ctaHref && post.cta_label"
            :href="ctaHref"
            target="_blank"
            rel="noopener noreferrer"
            class="btn-gloss inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-deep-marsala text-white font-label-lg text-label-lg hover:bg-deep-marsala/90 transition-colors"
          >
            <span class="relative z-[1]">{{ post.cta_label }}</span>
          </a>
          <router-link
            v-else
            :to="slugLink"
            class="btn-gloss inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-deep-marsala text-white font-label-lg text-label-lg hover:bg-deep-marsala/90 transition-colors"
          >
            <span class="relative z-[1]">Leer más</span>
          </router-link>
        </div>
      </div>
    </div>
  </section>
</template>
