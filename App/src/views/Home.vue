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
//
// Three-state model (not a boolean): the probe starts in 'checking', and
// NEITHER the error banner NOR the dashboard sections render while it is
// pending. This matters because the 6 dashboard sections each fire their own
// API calls from their own onMounted -- rendering them eagerly (as a
// `hasConnectivityError` boolean starting `false` would) lets them mount and
// fetch in parallel with, instead of gated behind, this connectivity probe,
// defeating the whole point of having a probe.
const connectivityState = ref('checking') // 'checking' | 'ok' | 'error'

// In-flight guard: prevents two concurrent checkConnectivity() runs (e.g. a
// user double-tapping "Reintentar") from racing, where whichever resolves
// last would win regardless of which tap was most recent. Also disables the
// retry button for the duration of a check.
const isCheckingConnectivity = ref(false)

async function checkConnectivity() {
  if (isCheckingConnectivity.value) return
  isCheckingConnectivity.value = true
  try {
    await api.get('/products', { params: { per_page: 1 } })
    connectivityState.value = 'ok'
  } catch (err) {
    connectivityState.value = err.response ? 'ok' : 'error'
  } finally {
    isCheckingConnectivity.value = false
  }
}

onMounted(checkConnectivity)
</script>

<template>
  <div>
    <div
      v-if="connectivityState === 'error'"
      data-home-error
      class="flex flex-col items-center gap-4 py-24 px-6 text-center"
    >
      <p class="font-body-lg text-body-lg text-on-surface-variant">
        No pudimos cargar tu inicio. Verifica tu conexión e intenta de nuevo.
      </p>
      <button
        type="button"
        data-home-retry
        :disabled="isCheckingConnectivity"
        class="btn-gloss px-6 py-3 rounded-xl bg-apricot-glow text-deep-marsala font-bold disabled:opacity-60 disabled:cursor-not-allowed"
        @click="checkConnectivity"
      >
        Reintentar
      </button>
    </div>

    <!-- 'checking': sections must not mount/fetch until the connectivity
         probe resolves, but the user still needs to see that something is
         happening instead of a blank screen. -->
    <div
      v-else-if="connectivityState === 'checking'"
      data-home-checking
      class="flex flex-col items-center gap-4 py-24 px-6 text-center"
    >
      <p class="font-body-lg text-body-lg text-on-surface-variant">
        Cargando...
      </p>
    </div>

    <template v-else-if="connectivityState === 'ok'">
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
