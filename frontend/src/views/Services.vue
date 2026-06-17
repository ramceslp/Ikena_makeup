<script setup>
import { ref, watch, onMounted } from 'vue'
import { useServicesStore } from '../stores/services.js'
import ServiceFilters from '../components/service/ServiceFilters.vue'
import ServiceCatalog from '../components/service/ServiceCatalog.vue'

const servicesStore = useServicesStore()

const search = ref('')
const minPrice = ref('')
const maxPrice = ref('')
const sort = ref('newest')
const category = ref('')
const availability = ref('')

let debounceTimer = null

function buildFilters() {
  return {
    search: search.value,
    min_price: minPrice.value,
    max_price: maxPrice.value,
    sort: sort.value,
    category: category.value,
    availability: availability.value,
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

watch([minPrice, maxPrice, sort, category, availability], applyFilters)

function goToPage(page) {
  servicesStore.fetchServices({ ...buildFilters(), page })
}

onMounted(() => {
  servicesStore.fetchCategories()
  servicesStore.fetchServices()
})
</script>

<template>
  <div>
    <!-- Page header -->
    <section class="py-16 bg-gradient-to-b from-blush-canvas/20 to-background">
      <div class="max-w-container-max mx-auto px-gutter text-center">
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala mb-3">
          Servicios de Maquillaje
        </h1>
        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto">
          Explora nuestros servicios profesionales de maquillaje para todo tipo de ocasión.
        </p>
      </div>
    </section>

    <ServiceFilters
      v-model:search="search"
      v-model:min-price="minPrice"
      v-model:max-price="maxPrice"
      v-model:sort="sort"
      v-model:category="category"
      v-model:availability="availability"
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
  </div>
</template>
