<script setup>
import { RouterLink } from 'vue-router'
import BaseBadge from '../ui/BaseBadge.vue'
import BaseButton from '../ui/BaseButton.vue'

const props = defineProps({
  service: {
    type: Object,
    required: true,
  },
})

function formatPrice(price) {
  const num = parseFloat(price)
  if (!num || num === 0) return 'Gratis'
  return `$${num.toFixed(2)}`
}

function isFree(price) {
  const num = parseFloat(price)
  return !num || num === 0
}

function excerpt(text, length = 100) {
  if (!text) return ''
  return text.length > length ? text.slice(0, length) + '...' : text
}

function availabilityLabel(type) {
  if (type === 'immediate') return 'Disponibilidad inmediata'
  if (type === 'by_appointment') return 'Por cita previa'
  return type
}
</script>

<template>
  <RouterLink
    :to="`/services/${service.slug}`"
    class="group flex flex-col bg-surface-muted rounded-2xl overflow-hidden border border-blush-canvas/30 transition-all duration-500 hover:shadow-xl hover:shadow-primary/5 hover:-translate-y-0.5 no-underline"
  >
    <!-- Thumbnail -->
    <div class="relative aspect-[16/9] overflow-hidden bg-surface-container">
      <img
        v-if="service.thumbnail"
        :src="service.thumbnail"
        :alt="service.title"
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
    </div>

    <!-- Content -->
    <div class="p-6 flex flex-col flex-grow">
      <!-- Meta row -->
      <div class="flex flex-wrap items-center gap-3 mb-3 text-outline">
        <!-- Category badge -->
        <span
          v-if="service.category"
          class="font-label-sm text-label-sm flex items-center gap-1 text-on-surface-variant"
        >
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">sell</span>
          {{ service.category.name }}
        </span>

        <!-- Duration -->
        <span class="font-label-sm text-label-sm flex items-center gap-1">
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">schedule</span>
          {{ service.duration_hours }} Horas
        </span>

        <!-- Availability -->
        <span class="font-label-sm text-label-sm flex items-center gap-1">
          <span class="material-symbols-outlined text-[14px]" aria-hidden="true">event_available</span>
          {{ availabilityLabel(service.availability_type) }}
        </span>
      </div>

      <h3 class="font-title-md text-title-md text-deep-marsala mb-2 group-hover:text-primary transition-colors line-clamp-2">
        {{ service.title }}
      </h3>
      <p class="font-body-md text-body-md text-on-surface-variant mb-6 line-clamp-2 flex-grow">
        {{ excerpt(service.description, 120) }}
      </p>

      <!-- Footer: price + CTA -->
      <div class="mt-auto border-t border-blush-canvas/20 pt-4 flex items-center justify-between">
        <span class="font-title-md text-title-md text-primary flex items-center gap-2">
          <BaseBadge v-if="isFree(service.price)" variant="secondary">Gratis</BaseBadge>
          <template v-else>{{ formatPrice(service.price) }}</template>
        </span>
        <BaseButton variant="primary" size="sm">Ver Detalles</BaseButton>
      </div>
    </div>
  </RouterLink>
</template>
