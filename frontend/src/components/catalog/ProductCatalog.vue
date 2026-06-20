<script setup>
import ProductCard from './ProductCard.vue'
import BaseButton from '../ui/BaseButton.vue'

defineProps({
  products: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  error: { type: [String, null], default: null },
  meta: { type: [Object, null], default: null },
})

defineEmits(['retry', 'page-change', 'add-to-cart'])
</script>

<template>
  <section class="py-20 bg-background">
    <div class="max-w-container-max mx-auto px-gutter">
      <!-- Loading skeleton -->
      <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12">
        <div
          v-for="i in 6"
          :key="i"
          data-skeleton
          class="bg-surface-muted rounded-2xl border border-blush-canvas/30 overflow-hidden animate-pulse"
        >
          <div class="aspect-[16/9] bg-surface-container" />
          <div class="p-5 space-y-3">
            <div class="h-4 bg-surface-container rounded w-3/4" />
            <div class="h-3 bg-surface-container rounded w-1/2" />
            <div class="h-3 bg-surface-container rounded w-full" />
          </div>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="text-center py-16">
        <span class="material-symbols-outlined text-error text-5xl mb-4" aria-hidden="true">error</span>
        <p class="font-body-lg text-body-lg text-on-surface">{{ error }}</p>
        <button
          data-retry
          type="button"
          @click="$emit('retry')"
          class="mt-4 text-primary hover:underline font-label-md text-label-md"
        >
          Intentar de nuevo
        </button>
      </div>

      <!-- Empty -->
      <div v-else-if="!products.length" class="text-center py-16">
        <span class="material-symbols-outlined text-blush-canvas text-5xl mb-4" aria-hidden="true">search_off</span>
        <p class="font-body-lg text-body-lg text-on-surface-variant">No se encontraron productos</p>
        <p class="font-body-md text-body-md text-outline mt-1">Prueba con otros filtros de búsqueda</p>
      </div>

      <!-- Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12">
        <ProductCard
          v-for="product in products"
          :key="product.id"
          :product="product"
          @add-to-cart="$emit('add-to-cart', $event)"
        />
      </div>

      <!-- Pagination -->
      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-center gap-4 mt-16"
      >
        <button
          data-page-prev
          type="button"
          :disabled="meta.current_page <= 1 || undefined"
          class="flex items-center gap-1 px-4 py-2 rounded-xl border border-blush-canvas/30 font-label-md text-label-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed hover:enabled:bg-surface-container-low"
          @click="$emit('page-change', meta.current_page - 1)"
        >
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_left</span>
          Anterior
        </button>

        <span class="font-body-md text-body-md text-on-surface-variant">
          Página {{ meta.current_page }} de {{ meta.last_page }}
        </span>

        <button
          data-page-next
          type="button"
          :disabled="meta.current_page >= meta.last_page || undefined"
          class="flex items-center gap-1 px-4 py-2 rounded-xl border border-blush-canvas/30 font-label-md text-label-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed hover:enabled:bg-surface-container-low"
          @click="$emit('page-change', meta.current_page + 1)"
        >
          Siguiente
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_right</span>
        </button>
      </div>
    </div>
  </section>
</template>
