<script setup>
import { RouterLink } from 'vue-router'
import BaseButton from '../ui/BaseButton.vue'

const props = defineProps({
  product: {
    type: Object,
    required: true,
  },
})

function formatPrice(price) {
  const num = parseFloat(price)
  if (isNaN(num)) return '$0.00'
  return `$${num.toFixed(2)}`
}
</script>

<template>
  <div class="group flex flex-col bg-surface-muted rounded-2xl overflow-hidden border border-blush-canvas/30 transition-all duration-500 hover:shadow-xl hover:shadow-primary/5 hover:-translate-y-0.5">
    <!-- Thumbnail -->
    <RouterLink :to="`/products/${product.slug}`" class="block relative aspect-[16/9] overflow-hidden bg-surface-container">
      <img
        v-if="product.thumbnail"
        :src="product.thumbnail"
        :alt="product.title"
        class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
        loading="lazy"
      />
      <div
        v-else
        class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blush-canvas/30 to-primary/20"
      >
        <svg class="w-12 h-12 text-primary/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
          />
        </svg>
      </div>

      <!-- Stock badge overlay -->
      <span
        v-if="product.stock_state"
        class="absolute top-3 right-3 px-2 py-1 rounded-full text-xs font-semibold"
        :class="{
          'bg-red-100 text-red-700': product.stock_state === 'Agotado',
          'bg-amber-100 text-amber-700': product.stock_state === 'Últimas unidades',
          'bg-green-100 text-green-700': product.stock_state === 'En Stock',
        }"
      >
        {{ product.stock_state }}
      </span>
    </RouterLink>

    <!-- Content -->
    <div class="p-5 flex flex-col flex-grow">
      <!-- Meta row -->
      <div class="flex flex-wrap items-center gap-2 mb-3">
        <!-- Category badge -->
        <span
          v-if="product.category"
          data-category-pill
          class="font-label-sm text-label-sm flex items-center gap-1 text-on-surface-variant"
        >
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">sell</span>
          {{ product.category.name }}
        </span>
      </div>

      <RouterLink :to="`/products/${product.slug}`" class="no-underline">
        <h3 class="font-title-md text-title-md text-deep-marsala mb-2 group-hover:text-primary transition-colors line-clamp-2">
          {{ product.title }}
        </h3>
      </RouterLink>
      <p class="font-body-md text-body-md text-on-surface-variant mb-4 line-clamp-2 flex-grow">
        {{ product.description }}
      </p>

      <!-- Footer: price + CTA -->
      <div class="mt-auto border-t border-blush-canvas/20 pt-4 flex items-center justify-between gap-2">
        <span class="font-title-md text-title-md text-primary">
          {{ formatPrice(product.price) }}
        </span>
        <RouterLink :to="`/products/${product.slug}`">
          <BaseButton variant="outline" size="sm">Ver Detalles</BaseButton>
        </RouterLink>
      </div>
    </div>
  </div>
</template>
