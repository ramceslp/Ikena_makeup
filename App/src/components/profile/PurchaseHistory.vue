<script setup>
import PurchaseRow from './PurchaseRow.vue'

// Ported from frontend/src/components/profile/PurchaseHistory.vue
// (mobile-capacitor-setup Phase 8, tasks 8.9-8.10 [Spec: history loads / no
// history yet]), with two App-specific deviations:
//   1. Dropped the `v-reveal` scroll-reveal directive from the ported list —
//      same call already made for ProductCatalog.vue/ServiceCatalog.vue in
//      Phase 7. The directive is registered as of Phase 4 of the home motion
//      work, but only its `.trazo` heading variant is in use; this list still
//      renders at rest.
//   2. Added an `error` prop + `@retry` emit, in the SAME loading → error →
//      empty → list order ProductCatalog.vue already established in Phase 7.
//      The web version never surfaced a fetch failure here at all (Profile.vue's
//      onMounted has no catch around fetchOrders()) — this closes that gap for
//      the app rather than reproducing it, consistent with every other
//      API-backed screen in App/ (Products.vue/Services.vue/Home.vue) always
//      distinguishing "an error happened" from "there's nothing here".
defineProps({
  orders: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  error: { type: [String, null], default: null },
})

defineEmits(['retry'])
</script>

<template>
  <!-- Loading skeleton -->
  <div v-if="loading" class="space-y-3">
    <div
      v-for="i in 3"
      :key="i"
      data-skeleton
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

  <!-- Error -->
  <div v-else-if="error" data-history-error class="text-center state-y">
    <span class="material-symbols-outlined text-error text-5xl mb-4" aria-hidden="true">error</span>
    <p class="font-body-lg text-body-lg text-on-surface">{{ error }}</p>
    <button
      data-history-retry
      type="button"
      @click="$emit('retry')"
      class="mt-4 text-primary hover:underline font-label-md text-label-md"
    >
      Intentar de nuevo
    </button>
  </div>

  <!-- Empty state [Spec: no history yet] -->
  <div v-else-if="!orders.length" data-history-empty class="text-center state-y">
    <span class="material-symbols-outlined text-blush-canvas text-5xl mb-3" aria-hidden="true">
      receipt_long
    </span>
    <p class="font-body-lg text-body-lg text-on-surface">Aún no tienes compras.</p>
  </div>

  <!-- Orders list [Spec: history loads — reverse-chronological, as returned by the store] -->
  <div v-else data-history-list class="space-y-3">
    <PurchaseRow v-for="order in orders" :key="order.id" data-history-row :order="order" />
  </div>
</template>
