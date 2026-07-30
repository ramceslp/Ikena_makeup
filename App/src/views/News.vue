<script setup>
import { ref, watch, onMounted, onBeforeUnmount, computed } from 'vue'
import { usePostsStore } from '../stores/posts.js'
import NewsCard from '../components/news/NewsCard.vue'
import BaseButton from '../components/ui/BaseButton.vue'

// News catalog (push-notifications Slice 5b). Mirrors views/Courses.vue's
// composition minus the filter panel: the public /api/posts endpoint accepts
// only `search` and `page` (backend Api\PostController::index), so no type or
// sort control is offered — a filter the API cannot honour would be worse
// than none.
//
// Uses the vertical-rhythm classes (.section-y-sm / .state-y) from style.css
// rather than hand-written py-* utilities, per the documented spacing contract.
const postsStore = usePostsStore()

const search = ref('')

const posts = computed(() => postsStore.posts)
const meta = computed(() => postsStore.meta)
const loading = computed(() => postsStore.loading)
const error = computed(() => postsStore.error)

const currentPage = computed(() => meta.value?.current_page ?? 1)
const lastPage = computed(() => meta.value?.last_page ?? 1)

let debounceTimer = null

function load(page = 1) {
  postsStore.fetchPosts({ search: search.value, page })
}

// Search is debounced — same 400ms as views/Courses.vue.
watch(search, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => load(1), 400)
})

// Without this, a pending debounce fires after the view is gone and mutates
// a store the user has navigated away from.
onBeforeUnmount(() => clearTimeout(debounceTimer))

onMounted(() => load())
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter section-y-sm">
    <!-- Header -->
    <header class="mb-6">
      <h1 class="font-headline-md text-headline-md text-deep-marsala">Noticias</h1>
      <p class="font-body-md text-body-md text-on-surface-variant mt-1">
        Novedades, cursos nuevos, ofertas y eventos de Ikena.
      </p>
    </header>

    <!-- Search -->
    <div class="relative mb-6">
      <span
        class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]"
        aria-hidden="true"
      >
        search
      </span>
      <input
        v-model="search"
        data-news-search
        type="search"
        placeholder="Buscar noticias"
        aria-label="Buscar noticias"
        class="w-full pl-12 pr-4 py-3 rounded-xl border border-blush-canvas/40 bg-surface font-body-md text-body-md focus:outline-none focus:border-primary"
      />
    </div>

    <!-- Loading -->
    <div v-if="loading" data-loading class="state-y flex items-center justify-center">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">
        refresh
      </span>
    </div>

    <!-- Error -->
    <div v-else-if="error" data-error class="state-y text-center">
      <span class="material-symbols-outlined text-5xl text-error mb-4" aria-hidden="true">error</span>
      <p class="font-body-lg text-body-lg text-on-surface mb-4">{{ error }}</p>
      <BaseButton variant="outline" @click="load(currentPage)">Reintentar</BaseButton>
    </div>

    <!-- Empty -->
    <div v-else-if="!posts.length" data-empty-state class="state-y text-center">
      <span class="material-symbols-outlined text-5xl text-blush-canvas mb-4" aria-hidden="true">
        newspaper
      </span>
      <p class="font-body-lg text-body-lg text-on-surface-variant">
        {{ search ? 'No encontramos noticias con esa búsqueda' : 'Todavía no hay noticias' }}
      </p>
    </div>

    <!-- Grid -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-6">
      <NewsCard v-for="post in posts" :key="post.id" :post="post" />
    </div>

    <!-- Pagination -->
    <div v-if="!loading && !error && lastPage > 1" class="flex items-center justify-center gap-4 mt-8">
      <BaseButton
        data-prev-page
        variant="outline"
        :disabled="currentPage <= 1"
        @click="load(currentPage - 1)"
      >
        Anterior
      </BaseButton>
      <span class="font-body-md text-body-md text-on-surface-variant">
        {{ currentPage }} / {{ lastPage }}
      </span>
      <BaseButton
        data-next-page
        variant="outline"
        :disabled="currentPage >= lastPage"
        @click="load(currentPage + 1)"
      >
        Siguiente
      </BaseButton>
    </div>
  </div>
</template>
