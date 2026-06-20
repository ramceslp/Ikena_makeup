<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useProductsStore } from '../stores/products.js'
import ProductFilters from '../components/catalog/ProductFilters.vue'
import ProductCatalog from '../components/catalog/ProductCatalog.vue'

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

onMounted(() => {
  productsStore.fetchCategories()
  productsStore.fetchProducts()
})

onBeforeUnmount(() => {
  clearTimeout(debounceTimer)
})
</script>

<template>
  <div>
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
  </div>
</template>
