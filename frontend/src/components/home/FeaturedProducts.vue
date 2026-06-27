<script setup>
import { ref, onMounted } from 'vue'
import { useProductsStore } from '../../stores/products.js'

const productsStore = useProductsStore()

const products = ref([])

onMounted(async () => {
  // Fetch 3 most-recent products using per_page limit
  await productsStore.fetchProducts({ page: 1, per_page: 3, sort: 'newest' })
  products.value = (productsStore.products ?? []).slice(0, 3)
})

function formatPrice(price) {
  const num = parseFloat(price)
  if (isNaN(num)) return '$0.00'
  return `$${num.toFixed(2)}`
}
</script>

<template>
  <section data-featured-products class="py-20 bg-surface-muted">
    <div class="max-w-container-max mx-auto px-gutter">
      <!-- Section header -->
      <div v-reveal class="flex items-end justify-between mb-10">
        <div>
          <p class="font-label-sm text-label-sm text-primary uppercase tracking-widest mb-2">
            Productos Profesionales
          </p>
          <h2 class="font-headline-lg text-headline-lg text-deep-marsala">
            Productos Destacados
          </h2>
        </div>
        <router-link
          to="/products"
          class="font-label-lg text-label-lg text-primary hover:text-deep-marsala transition-colors flex items-center gap-1"
        >
          Ver todos
          <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
        </router-link>
      </div>

      <!-- Products grid -->
      <div v-if="products.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div
          v-for="(product, i) in products"
          :key="product.id"
          v-reveal="i"
          data-product-card
          class="group flex flex-col bg-surface rounded-2xl overflow-hidden border border-blush-canvas/30 hover:shadow-xl hover:shadow-primary/5 hover:-translate-y-1 transition-all duration-300"
        >
          <!-- Thumbnail -->
          <router-link :to="`/products/${product.slug}`" class="block relative aspect-video bg-blush-canvas/10 overflow-hidden">
            <img
              v-if="product.thumbnail"
              :src="product.thumbnail"
              :alt="product.title"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blush-canvas/30 to-primary/20">
              <span class="material-symbols-outlined text-4xl text-primary/40" aria-hidden="true">palette</span>
            </div>
            <!-- Stock badge -->
            <span
              v-if="product.stock_state"
              class="absolute top-3 right-3 px-2 py-0.5 rounded-full text-xs font-semibold"
              :class="{
                'bg-red-100 text-red-700': product.stock_state === 'Agotado',
                'bg-amber-100 text-amber-700': product.stock_state === 'Últimas unidades',
                'bg-green-100 text-green-700': product.stock_state === 'En Stock',
              }"
            >
              {{ product.stock_state }}
            </span>
          </router-link>

          <!-- Card body -->
          <div class="flex flex-col flex-grow p-5 space-y-2">
            <router-link :to="`/products/${product.slug}`" class="no-underline">
              <h3 class="font-title-md text-title-md text-deep-marsala group-hover:text-primary transition-colors line-clamp-2">
                {{ product.title }}
              </h3>
            </router-link>
            <p class="font-title-md text-title-md text-primary mt-auto pt-2">
              {{ formatPrice(product.price) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else class="text-center py-12">
        <p class="font-body-lg text-body-lg text-on-surface-variant">
          Próximamente nuevos productos disponibles.
        </p>
      </div>
    </div>
  </section>
</template>
