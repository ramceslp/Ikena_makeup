<script setup>
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { usePostsStore } from '../stores/posts.js'
import { safeCtaUrl } from '../utils/cta.js'
import BaseButton from '../components/ui/BaseButton.vue'

// News detail keyed by :slug (push-notifications Slice 5b). This is the
// landing point for the "new post published" push notification's deep link,
// and for the "Leer más" links that Home.vue's FeaturedNewsHero and
// LatestNewsGrid have always rendered — those pointed at a route that did not
// exist until this view shipped.
//
// Mirrors views/CourseDetail.vue's loading/error/detail composition.
const route = useRoute()
const postsStore = usePostsStore()

const post = computed(() => postsStore.currentPost)
const loading = computed(() => postsStore.loading)
const error = computed(() => postsStore.error)

const ctaHref = computed(() => (post.value ? safeCtaUrl(post.value.cta_url) : null))

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
    month: 'long',
    year: 'numeric',
  })
}

// Fetches only on initial mount — the same inherited limitation as
// CourseDetail.vue/ServiceDetail.vue. No detail->detail navigation exists in
// this view.
onMounted(async () => {
  try {
    await postsStore.fetchPost(route.params.slug)
  } catch {
    // error state is set on the store and rendered below
  }
})
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter section-y-sm">
    <!-- Loading -->
    <div v-if="loading" data-loading class="state-y flex items-center justify-center">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">
        refresh
      </span>
    </div>

    <!-- Error / 404 -->
    <div v-else-if="error" data-error class="state-y text-center">
      <span class="material-symbols-outlined text-5xl text-error mb-4" aria-hidden="true">error</span>
      <p class="font-body-lg text-body-lg text-on-surface mb-4">{{ error }}</p>
      <RouterLink to="/noticias">
        <BaseButton variant="outline">Volver a noticias</BaseButton>
      </RouterLink>
    </div>

    <!-- Detail -->
    <article v-else-if="post" class="flex flex-col gap-6">
      <RouterLink
        to="/noticias"
        data-back-to-news
        class="font-label-md text-label-md text-on-surface-variant hover:text-primary flex items-center gap-1 w-fit"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
        Noticias
      </RouterLink>

      <!-- Cover -->
      <div v-if="post.cover_image_url" class="rounded-2xl overflow-hidden aspect-video bg-surface-container">
        <img :src="post.cover_image_url" :alt="post.title" class="w-full h-full object-cover" />
      </div>

      <!-- Meta -->
      <div class="flex flex-wrap items-center gap-3">
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
        <span v-if="post.author" class="font-label-sm text-label-sm text-on-surface-variant">
          por {{ post.author.name }}
        </span>
      </div>

      <h1 class="font-headline-md text-headline-md text-deep-marsala">{{ post.title }}</h1>

      <p v-if="post.excerpt" class="font-body-lg text-body-lg text-on-surface-variant">
        {{ post.excerpt }}
      </p>

      <!--
        `body` is rich text authored in the admin panel and sanitized
        SERVER-SIDE with Mews\Purifier before it is ever stored — see
        Api\Admin\PostController::cleanBody() and config/purifier.php. It is
        rendered as HTML because formatting is the point of the field; the
        trust boundary is the purifier on write, not this template on read.
      -->
      <div
        v-if="post.body"
        data-post-body
        class="prose-news font-body-md text-body-md text-on-surface"
        v-html="post.body"
      />

      <!-- External CTA -->
      <a
        v-if="ctaHref && post.cta_label"
        :href="ctaHref"
        data-cta-link
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-1 font-label-lg text-label-lg text-primary hover:text-deep-marsala transition-colors w-fit"
      >
        {{ post.cta_label }}
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">open_in_new</span>
      </a>

      <!-- Gallery -->
      <div v-if="post.images?.length" class="grid grid-cols-2 gap-4">
        <img
          v-for="image in post.images"
          :key="image.id"
          data-gallery-image
          :src="image.url"
          :alt="post.title"
          loading="lazy"
          class="w-full rounded-xl object-cover aspect-square"
        />
      </div>
    </article>
  </div>
</template>
