<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useProductsStore } from '../stores/products.js'
import api from '../services/api.js'
import ProductFilters from '../components/catalog/ProductFilters.vue'
import ProductCatalog from '../components/catalog/ProductCatalog.vue'

// Ported from frontend/src/views/Products.vue (mobile-capacitor-setup Phase
// 7), with one App-specific addition: a connectivity probe, reusing the same
// three-state (checking/ok/error) pattern Home.vue established in Phase 6
// (see Home.vue's file-level comment) rather than inventing a new
// offline-detection approach, per the spec's "Catalog unreachable" scenario
// ("shows an explicit connectivity error, not stale/cached data"). The
// catalog's own `error` state (from productsStore.fetchProducts) covers
// ordinary HTTP-level failures; this probe specifically distinguishes a
// genuine no-response network failure so the empty catalog is never
// mistaken for "no products match your filters".
const productsStore = useProductsStore()

const search = ref('')
const minPrice = ref('')
const maxPrice = ref('')
const sort = ref('newest')
const category = ref('')
const stockState = ref('')

let debounceTimer = null

function buildFilters() {
  return {
    search: search.value,
    min_price: minPrice.value,
    max_price: maxPrice.value,
    sort: sort.value,
    category: category.value,
    stock_state: stockState.value,
    page: 1,
  }
}

function applyFilters() {
  productsStore.fetchProducts(buildFilters())
}

// Search and price inputs are debounced; select filters apply immediately
watch(search, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 400)
})

watch([minPrice, maxPrice], () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 400)
})

watch([sort, category, stockState], applyFilters)

function goToPage(page) {
  productsStore.fetchProducts({ ...buildFilters(), page })
}

const connectivityState = ref('checking') // 'checking' | 'ok' | 'error'
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

async function init() {
  await checkConnectivity()
  if (connectivityState.value === 'ok') {
    productsStore.fetchCategories()
    productsStore.fetchProducts()
  }
}

onMounted(init)

onBeforeUnmount(() => {
  clearTimeout(debounceTimer)
})
</script>

<template>
  <div>
    <div
      v-if="connectivityState === 'error'"
      data-catalog-error
      class="flex flex-col items-center gap-4 py-24 px-6 text-center"
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
      class="flex flex-col items-center gap-4 py-24 px-6 text-center"
    >
      <p class="font-body-lg text-body-lg text-on-surface-variant">
        Cargando...
      </p>
    </div>

    <template v-else-if="connectivityState === 'ok'">
      <!-- Page header -->
      <section class="py-16 bg-gradient-to-b from-blush-canvas/20 to-background">
        <div class="max-w-container-max mx-auto px-gutter text-center">
          <h1 class="font-headline-lg text-headline-lg text-deep-marsala mb-3">
            Catálogo de Productos
          </h1>
          <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto">
            Descubre nuestros productos profesionales de maquillaje seleccionados especialmente para ti.
          </p>
        </div>
      </section>

      <ProductFilters
        v-model:search="search"
        v-model:min-price="minPrice"
        v-model:max-price="maxPrice"
        v-model:sort="sort"
        v-model:category="category"
        v-model:stock-state="stockState"
        :categories="productsStore.categories"
      />

      <ProductCatalog
        :products="productsStore.products"
        :loading="productsStore.loading"
        :error="productsStore.error"
        :meta="productsStore.productMeta"
        @retry="applyFilters"
        @page-change="goToPage"
      />
    </template>
  </div>
</template>
