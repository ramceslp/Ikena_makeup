<script setup>
import PurchaseRow from './PurchaseRow.vue'

const props = defineProps({
  orders: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})
</script>

<template>
  <!-- Loading skeleton -->
  <div v-if="loading" class="space-y-3">
    <div
      v-for="i in 3"
      :key="i"
      class="bg-surface-muted rounded-2xl border border-blush-canvas/30 p-4 flex items-center gap-4 animate-pulse"
    >
      <div class="w-20 h-14 bg-surface-container rounded-lg flex-shrink-0" />
      <div class="flex-1 space-y-2">
        <div class="h-4 bg-surface-container rounded w-3/4" />
        <div class="h-3 bg-surface-container rounded w-1/3" />
      </div>
      <div class="flex flex-col items-end gap-1">
        <div class="h-4 bg-surface-container rounded w-16" />
        <div class="h-4 bg-surface-container rounded w-12" />
      </div>
    </div>
  </div>

  <!-- Empty state -->
  <div v-else-if="!orders.length" class="text-center py-16">
    <span class="material-symbols-outlined text-blush-canvas text-5xl mb-3" aria-hidden="true">
      receipt_long
    </span>
    <p class="font-body-lg text-body-lg text-on-surface">Aún no tienes compras.</p>
  </div>

  <!-- Orders list -->
  <div v-else class="space-y-3">
    <PurchaseRow v-for="order in orders" :key="order.id" :order="order" />
  </div>
</template>
