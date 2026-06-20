<script setup>
import { computed, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useProductsStore } from '../stores/products.js'
import ServiceGallery from '../components/service/ServiceGallery.vue'
import BaseBadge from '../components/ui/BaseBadge.vue'
import BaseButton from '../components/ui/BaseButton.vue'

const route = useRoute()
const router = useRouter()
const productsStore = useProductsStore()

const product = computed(() => productsStore.currentProduct)
const loading = computed(() => productsStore.loading)
const error = computed(() => productsStore.error)

function formatPrice(price) {
  const num = parseFloat(price)
  if (isNaN(num)) return '$0.00'
  return `$${num.toFixed(2)}`
}

onMounted(async () => {
  try {
    await productsStore.fetchProduct(route.params.slug)
  } catch {
    // error state is set on the store
  }
})
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter py-16">
    <!-- Loading -->
    <div v-if="loading" data-loading class="flex items-center justify-center py-32">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <!-- Error / 404 -->
    <div v-else-if="error" class="text-center py-32">
      <span class="material-symbols-outlined text-5xl text-error mb-4" aria-hidden="true">error</span>
      <p class="font-body-lg text-body-lg text-on-surface mb-4">{{ error }}</p>
      <BaseButton variant="outline" @click="$router.push('/products')">
        Volver al catálogo
      </BaseButton>
    </div>

    <!-- Product Detail -->
    <div v-else-if="product" class="grid grid-cols-1 lg:grid-cols-2 gap-12">
      <!-- Gallery -->
      <div>
        <ServiceGallery :images="product.images ?? []" />
      </div>

      <!-- Info -->
      <div class="flex flex-col gap-6">
        <!-- Back link -->
        <RouterLink to="/products" data-back-to-catalog class="font-label-md text-label-md text-on-surface-variant hover:text-primary flex items-center gap-1 w-fit">
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
          Volver al catálogo
        </RouterLink>

        <!-- Category + stock badges -->
        <div class="flex flex-wrap gap-2">
          <BaseBadge v-if="product.category" variant="secondary">
            {{ product.category.name }}
          </BaseBadge>
          <span
            v-if="product.stock_state"
            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
            :class="{
              'bg-red-100 text-red-700': product.stock_state === 'Agotado',
              'bg-amber-100 text-amber-700': product.stock_state === 'Últimas unidades',
              'bg-green-100 text-green-700': product.stock_state === 'En Stock',
            }"
          >
            {{ product.stock_state }}
          </span>
        </div>

        <!-- Title -->
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala">
          {{ product.title }}
        </h1>

        <!-- Price row -->
        <div class="flex items-center gap-4">
          <span class="font-display-lg text-display-lg text-primary">
            {{ formatPrice(product.price) }}
          </span>
          <span class="font-body-md text-body-md text-on-surface-variant flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">inventory_2</span>
            {{ product.stock_qty }} en stock
          </span>
        </div>

        <!-- Description -->
        <div class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
          {{ product.description }}
        </div>

      </div>
    </div>
  </div>
</template>
