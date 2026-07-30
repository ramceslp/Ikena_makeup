<script setup>
import { ref, watch, onMounted } from 'vue'
import { useServicesStore } from '../stores/services.js'
import api from '../services/api.js'
import ServiceFilters from '../components/service/ServiceFilters.vue'
import ServiceCatalog from '../components/service/ServiceCatalog.vue'

// Ported from frontend/src/views/Services.vue (mobile-capacitor-setup Phase
// 7). See Products.vue's file-level comment for why the connectivity probe
// is duplicated here rather than extracted into a shared composable (keeps
// this PR's diff scoped to Phase 7, doesn't reopen already-reviewed Home.vue
// from Phase 6).
const servicesStore = useServicesStore()

const search = ref('')
const minPrice = ref('')
const maxPrice = ref('')
const sort = ref('newest')
const category = ref('')
const availabilityType = ref('')

let debounceTimer = null

function buildFilters() {
  return {
    search: search.value,
    min_price: minPrice.value,
    max_price: maxPrice.value,
    sort: sort.value,
    category: category.value,
    availability_type: availabilityType.value,
    page: 1,
  }
}

function applyFilters() {
  servicesStore.fetchServices(buildFilters())
}

// Search is debounced; other filters apply immediately
watch(search, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 400)
})

watch([minPrice, maxPrice, sort, category, availabilityType], applyFilters)

function goToPage(page) {
  servicesStore.fetchServices({ ...buildFilters(), page })
}

const connectivityState = ref('checking') // 'checking' | 'ok' | 'error'
const isCheckingConnectivity = ref(false)

async function checkConnectivity() {
  if (isCheckingConnectivity.value) return
  isCheckingConnectivity.value = true
  try {
    await api.get('/services', { params: { per_page: 1 } })
    connectivityState.value = 'ok'
  } catch (err) {
    connectivityState.value = err.response ? 'ok' : 'error'
  } finally {
    isCheckingConnectivity.value = false
  }
}

async function init() {
  await checkConnectivity()
  if (connectivityState.value === 'ok') {
    servicesStore.fetchCategories()
    servicesStore.fetchServices()
  }
}

onMounted(init)
</script>

<template>
  <div>
    <div
      v-if="connectivityState === 'error'"
      data-catalog-error
      class="flex flex-col items-center gap-4 state-y px-6 text-center"
    >
      <p class="font-body-lg text-body-lg text-on-surface-variant">
        No pudimos cargar el catálogo. Verifica tu conexión e intenta de nuevo.
      </p>
      <button
        type="button"
        data-catalog-retry
        :disabled="isCheckingConnectivity"
        class="btn-gloss px-6 py-3 rounded-xl bg-apricot-glow text-deep-marsala font-bold disabled:opacity-60 disabled:cursor-not-allowed"
        @click="init"
      >
        Reintentar
      </button>
    </div>

    <div
      v-else-if="connectivityState === 'checking'"
      data-catalog-checking
      class="flex flex-col items-center gap-4 state-y px-6 text-center"
    >
      <p class="font-body-lg text-body-lg text-on-surface-variant">
        Cargando...
      </p>
    </div>

    <template v-else-if="connectivityState === 'ok'">
      <!-- Page header -->
      <section class="section-y-sm bg-gradient-to-b from-blush-canvas/20 to-background">
        <div class="max-w-container-max mx-auto px-gutter text-center">
          <h1 class="font-headline-lg text-headline-lg text-deep-marsala mb-3">
            Servicios de Maquillaje
          </h1>
          <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto truncate">
            Servicios profesionales para toda ocasión.
          </p>
        </div>
      </section>

      <ServiceFilters
        v-model:search="search"
        v-model:min-price="minPrice"
        v-model:max-price="maxPrice"
        v-model:sort="sort"
        v-model:category="category"
        v-model:availability-type="availabilityType"
        :categories="servicesStore.categories"
      />

      <ServiceCatalog
        :services="servicesStore.services"
        :loading="servicesStore.loading"
        :error="servicesStore.error"
        :meta="servicesStore.serviceMeta"
        @retry="applyFilters"
        @page-change="goToPage"
      />
    </template>
  </div>
</template>
