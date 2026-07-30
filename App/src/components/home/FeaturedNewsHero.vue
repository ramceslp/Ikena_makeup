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

// A cover_image_url that is present but fails to LOAD is a different case
// from one that is absent, and the hero handles only the second. Without
// this the browser draws its broken-image glyph across the whole backdrop
// AND the signature gradient-mesh fallback below never runs, because its
// v-else only tests whether the URL exists.
//
// Treating a failed load as no image at all restores the intended fallback:
// the hero simply renders on the gradient, which is a deliberate design,
// not a degraded state.
const coverFailed = ref(false)
</script>

<template>
  <!--
    overflow-clip, NOT overflow-hidden: `hidden` would make this a scroll
    container and freeze the `.velo-media` scroll timeline inside it. See the
    "Velo" block in style.css.
  -->
  <section data-featured-news-hero class="relative overflow-clip min-h-[400px] flex items-center bg-surface-muted">
    <!-- Background image when available -->
    <div v-if="post?.cover_image_url && !coverFailed" data-hero-media class="absolute inset-0 z-0 overflow-clip">
      <img
        :src="post.cover_image_url"
        alt=""
        aria-hidden="true"
        data-hero-cover
        class="velo-media w-full h-full object-cover object-center"
        @error="coverFailed = true"
      />
      <!-- Legibility veil (left → right) — lifts as the hero scrolls away -->
      <div data-hero-veil class="velo-veil absolute inset-0 bg-gradient-to-r from-background via-background/60 to-transparent z-10" />
      <div class="makeup-mesh absolute inset-0 z-10 opacity-40 mix-blend-screen" aria-hidden="true" />
      <div class="absolute inset-0 bg-gradient-to-t from-deep-marsala/25 via-transparent to-transparent z-10" />
    </div>
    <!-- Fallback: signature gradient mesh. Also the destination when a cover
         URL exists but fails to load — see coverFailed. -->
    <div v-else data-hero-gradient class="absolute inset-0 z-0 bg-surface-muted overflow-clip">
      <div class="makeup-mesh absolute -inset-[10%]" aria-hidden="true" />
    </div>

    <!-- Bespoke height, not the .section-y tier: the hero is the first
         impression and earns a taller, more generous scale than the rest of
         the home sections. Compacted less aggressively than the ~40% cut
         applied elsewhere (480px min-h / py-20 -> 400px min-h / py-16) so it
         still reads as "hero", while leaving enough of the viewport for the
         next section to peek in on a 375x667 phone (content-priority). -->
    <div data-hero-content class="relative z-20 w-full max-w-container-max mx-auto px-gutter py-16" v-if="post">
      <div class="max-w-2xl space-y-6">
        <div class="inline-flex items-center gap-2">
          <span
            data-type-badge
            class="px-3 py-1 rounded-full font-label-sm text-label-sm bg-blush-canvas/30 text-deep-marsala uppercase tracking-widest border border-blush-canvas/40"
          >
            {{ post.type?.replace(/_/g, ' ') }}
          </span>
        </div>

        <h2 class="font-display-lg text-display-lg text-primary leading-tight">
          {{ post.title }}
        </h2>

        <p v-if="post.excerpt" class="font-body-lg text-body-lg text-on-surface-variant max-w-lg">
          {{ post.excerpt }}
        </p>

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
