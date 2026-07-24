<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/api.js'
import FeaturedNewsHero from '../components/home/FeaturedNewsHero.vue'
import LatestNewsGrid from '../components/home/LatestNewsGrid.vue'
import FeaturedCourses from '../components/home/FeaturedCourses.vue'
import FeaturedServices from '../components/home/FeaturedServices.vue'
import FeaturedProducts from '../components/home/FeaturedProducts.vue'
import NewsletterCta from '../components/home/NewsletterCta.vue'

// Ported from frontend/src/views/Home.vue, with one App-specific addition:
// a connectivity probe. The app has "no offline mode" (spec non-goal), so a
// genuine network-level failure (no response ever reached the device --
// airplane mode, unreachable dev host) must render an explicit retry/error
// state, per the "Home load fails offline" scenario. Without this probe,
// each ported section would just silently fall back to its own "coming
// soon" empty state (see FeaturedCourses.vue etc.) -- which looks identical
// to "there's genuinely no content yet" and would violate the spec's
// requirement for a distinguishable retry/error affordance when offline.
//
// A real HTTP error response (4xx/5xx -- the server WAS reached) is NOT a
// connectivity failure: axios only omits `error.response` when the request
// never got a response at all. In that case this falls through to the
// sections below, each handling its own empty/error UI exactly like the
// ported web version.
const hasConnectivityError = ref(false)

async function checkConnectivity() {
  try {
    await api.get('/products', { params: { per_page: 1 } })
    hasConnectivityError.value = false
  } catch (err) {
    hasConnectivityError.value = !err.response
  }
}

onMounted(checkConnectivity)
</script>

<template>
  <div>
    <div
      v-if="hasConnectivityError"
      data-home-error
      class="flex flex-col items-center gap-4 py-24 px-6 text-center"
    >
      <p class="font-body-lg text-body-lg text-on-surface-variant">
        No pudimos cargar tu inicio. Verifica tu conexión e intenta de nuevo.
      </p>
      <button
        type="button"
        data-home-retry
        class="btn-gloss px-6 py-3 rounded-xl bg-apricot-glow text-deep-marsala font-bold"
        @click="checkConnectivity"
      >
        Reintentar
      </button>
    </div>

    <template v-else>
      <!-- 1. Featured news hero -->
      <FeaturedNewsHero />

      <!-- 2. Latest news grid -->
      <LatestNewsGrid />

      <!-- 3. Featured courses (3 most-recent, link → /cursos) -->
      <FeaturedCourses />

      <!-- 4. Featured services (3 most-recent, link → /services) -->
      <FeaturedServices />

      <!-- 5. Featured products (3 most-recent, link → /products) -->
      <FeaturedProducts />

      <!-- 6. Newsletter CTA -->
      <NewsletterCta />
    </template>
  </div>
</template>
