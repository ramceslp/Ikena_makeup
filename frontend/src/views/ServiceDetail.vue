<script setup>
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useServicesStore } from '../stores/services.js'
import ServiceGallery from '../components/service/ServiceGallery.vue'
import BaseBadge from '../components/ui/BaseBadge.vue'
import BaseButton from '../components/ui/BaseButton.vue'

const route = useRoute()
const router = useRouter()
const servicesStore = useServicesStore()

const service = computed(() => servicesStore.currentService)
const loading = computed(() => servicesStore.loading)
const error = computed(() => servicesStore.error)

function availabilityLabel(type) {
  if (type === 'immediate') return 'Disponibilidad inmediata'
  if (type === 'by_appointment') return 'Por cita previa'
  return type
}

function formatPrice(price) {
  const num = parseFloat(price)
  if (!num || num === 0) return 'Gratis'
  return `$${num.toFixed(2)}`
}

onMounted(async () => {
  try {
    await servicesStore.fetchService(route.params.slug)
  } catch {
    // 404 redirect handled by error state
  }
})
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter py-16">
    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-32">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <!-- Error / 404 -->
    <div v-else-if="error" class="text-center py-32">
      <span class="material-symbols-outlined text-5xl text-error mb-4" aria-hidden="true">error</span>
      <p class="font-body-lg text-body-lg text-on-surface mb-4">{{ error }}</p>
      <BaseButton variant="outline" @click="$router.push('/services')">
        Volver al catálogo
      </BaseButton>
    </div>

    <!-- Service Detail -->
    <div v-else-if="service" class="grid grid-cols-1 lg:grid-cols-2 gap-12">
      <!-- Gallery -->
      <div>
        <ServiceGallery :images="service.images ?? []" />
      </div>

      <!-- Info -->
      <div class="flex flex-col gap-6">
        <!-- Category + availability -->
        <div class="flex flex-wrap gap-2">
          <BaseBadge v-if="service.category" variant="secondary">
            {{ service.category.name }}
          </BaseBadge>
          <BaseBadge variant="accent">
            {{ availabilityLabel(service.availability_type) }}
          </BaseBadge>
        </div>

        <!-- Title -->
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala">
          {{ service.title }}
        </h1>

        <!-- Price + duration row -->
        <div class="flex items-center gap-6">
          <span class="font-display-lg text-display-lg text-primary">
            {{ formatPrice(service.price) }}
          </span>
          <span class="font-body-lg text-body-lg text-on-surface-variant flex items-center gap-1">
            <span class="material-symbols-outlined" aria-hidden="true">schedule</span>
            {{ service.duration_hours }} Horas
          </span>
        </div>

        <!-- Description -->
        <div class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
          {{ service.description }}
        </div>

        <!-- CTA placeholder (booking is Slice 2) -->
        <div class="mt-auto">
          <BaseButton variant="primary" size="lg" disabled class="w-full">
            Reservar (próximamente)
          </BaseButton>
          <p class="font-label-sm text-label-sm text-outline text-center mt-2">
            Sistema de reservas disponible próximamente
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
