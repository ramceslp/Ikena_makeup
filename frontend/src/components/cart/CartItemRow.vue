<script setup>
import { computed } from 'vue'
import { useCartStore } from '../../stores/cart.js'

const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
})

const cart = useCartStore()

const lineTotal = computed(() => {
  const price = parseFloat(props.item.price)
  return `$${(price * props.item.quantity).toFixed(2)}`
})

const unitPrice = computed(() => {
  const price = parseFloat(props.item.price)
  return `$${price.toFixed(2)}`
})

const canDecrement = computed(() => props.item.quantity > 1)
const canIncrement = computed(() => props.item.quantity < props.item.stock_qty)

function decrement() {
  if (canDecrement.value) {
    cart.updateQuantity(props.item.product_id, props.item.quantity - 1)
  }
}

function increment() {
  if (canIncrement.value) {
    cart.updateQuantity(props.item.product_id, props.item.quantity + 1)
  }
}

function remove() {
  cart.removeItem(props.item.product_id)
}
</script>

<template>
  <div class="flex items-center gap-4 py-4 border-b border-blush-canvas/20 last:border-b-0">
    <!-- Thumbnail -->
    <div class="w-16 h-16 rounded-xl overflow-hidden bg-surface-container flex-shrink-0">
      <img
        v-if="item.thumbnail"
        :src="item.thumbnail"
        :alt="item.title"
        class="w-full h-full object-cover"
      />
      <div
        v-else
        class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blush-canvas/30 to-primary/20"
      >
        <svg class="w-7 h-7 text-primary/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
          />
        </svg>
      </div>
    </div>

    <!-- Info -->
    <div class="flex-grow min-w-0">
      <p class="font-title-sm text-title-sm text-deep-marsala truncate">{{ item.title }}</p>
      <p class="font-body-sm text-body-sm text-on-surface-variant">{{ unitPrice }}</p>
    </div>

    <!-- Qty stepper -->
    <div class="flex items-center gap-2">
      <button
        data-qty-dec
        :disabled="!canDecrement"
        @click="decrement"
        class="w-8 h-8 rounded-lg border border-blush-canvas/40 flex items-center justify-center text-on-surface-variant hover:bg-surface-container transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
        aria-label="Disminuir cantidad"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
        </svg>
      </button>
      <span data-qty-value class="font-label-md text-label-md text-on-surface w-6 text-center">
        {{ item.quantity }}
      </span>
      <button
        data-qty-inc
        :disabled="!canIncrement"
        @click="increment"
        class="w-8 h-8 rounded-lg border border-blush-canvas/40 flex items-center justify-center text-on-surface-variant hover:bg-surface-container transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
        aria-label="Aumentar cantidad"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
      </button>
    </div>

    <!-- Line total -->
    <div class="text-right min-w-[4.5rem]">
      <p class="font-title-sm text-title-sm text-primary">{{ lineTotal }}</p>
    </div>

    <!-- Remove -->
    <button
      data-remove-btn
      @click="remove"
      class="p-1.5 rounded-lg text-on-surface-variant hover:text-error hover:bg-error/10 transition-colors"
      aria-label="Eliminar producto"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
      </svg>
    </button>
  </div>
</template>
