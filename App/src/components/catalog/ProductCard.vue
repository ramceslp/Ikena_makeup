<script setup>
import { RouterLink } from 'vue-router'
import BaseButton from '../ui/BaseButton.vue'
import AddToCartButton from '../cart/AddToCartButton.vue'
import { formatPrice } from '../../utils/formatPrice.js'

// Ported from frontend/src/components/catalog/ProductCard.vue
// (mobile-capacitor-setup Phase 7). Uses the shared services/formatPrice.js
// util (Phase 5/6 convention — see stores/shared/buildParams.js) instead of a
// local duplicate.
//
// The "add to cart" CTA was deliberately absent when this file shipped
// (cart.js did not exist on that branch yet) and stayed absent after the cart
// landed, so the catalog had no way to fill it. AddToCartButton.vue now owns
// that CTA and its stock/quantity states — see its header comment.
defineProps({
  product: {
    type: Object,
    required: true,
  },
})
</script>

<template>
  <div
    data-product-card
    class="group flex flex-col bg-surface-muted rounded-2xl overflow-hidden border border-blush-canvas/30 shadow-md shadow-primary/5 transition-all duration-500 hover:shadow-xl hover:shadow-primary/10 hover:-translate-y-0.5"
  >
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

      <!-- Footer: price + CTAs. Two rows rather than one: a third control on
           the price row squeezed every element below a comfortable tap size on
           a phone-width grid (Skill: touch-target-size). "Añadir al carrito"
           gets the full-width primary row because it is the action this card
           exists to offer; "Ver Detalles" stays secondary next to the price. -->
      <div class="mt-auto border-t border-blush-canvas/20 pt-4 flex flex-col gap-3">
        <div class="flex items-center justify-between gap-2">
          <span class="font-title-md text-title-md text-primary tabular-nums">
            {{ formatPrice(product.price) }}
          </span>
          <RouterLink :to="`/products/${product.slug}`">
            <BaseButton variant="outline" size="sm">Ver Detalles</BaseButton>
          </RouterLink>
        </div>

        <AddToCartButton :product="product" size="sm" full-width />
      </div>
    </div>
  </div>
</template>
