<script setup>
import { ref, onMounted } from 'vue'
import { useServicesStore } from '../../stores/services.js'

const servicesStore = useServicesStore()

const services = ref([])

onMounted(async () => {
  // Fetch 3 most-recent services using per_page limit
  await servicesStore.fetchServices({ page: 1, per_page: 3, sort: 'newest' })
  services.value = (servicesStore.services ?? []).slice(0, 3)
})

function formatPrice(price) {
  const num = parseFloat(price)
  if (!num || num === 0) return 'Gratis'
  return `$${num.toFixed(2)}`
}
</script>

<template>
  <section data-featured-services class="py-20 bg-background">
    <div class="max-w-container-max mx-auto px-gutter">
      <!-- Section header -->
      <div class="flex items-end justify-between mb-10">
        <div>
          <p class="font-label-sm text-label-sm text-primary uppercase tracking-widest mb-2">
            Experiencias Profesionales
          </p>
          <h2 class="font-headline-lg text-headline-lg text-deep-marsala">
            Servicios Destacados
          </h2>
        </div>
        <router-link
          to="/services"
          class="font-label-lg text-label-lg text-primary hover:text-deep-marsala transition-colors flex items-center gap-1"
        >
          Ver todos
          <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
        </router-link>
      </div>

      <!-- Services grid -->
      <div v-if="services.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <router-link
          v-for="service in services"
          :key="service.id"
          :to="`/services/${service.slug}`"
          data-service-card
          class="group flex flex-col bg-surface rounded-2xl overflow-hidden border border-blush-canvas/30 hover:shadow-lg transition-shadow no-underline"
        >
          <!-- Thumbnail -->
          <div class="aspect-video bg-blush-canvas/10 overflow-hidden">
            <img
              v-if="service.thumbnail"
              :src="service.thumbnail"
              :alt="service.title"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blush-canvas/30 to-primary/20">
              <span class="material-symbols-outlined text-4xl text-primary/40" aria-hidden="true">spa</span>
            </div>
          </div>

          <!-- Card body -->
          <div class="flex flex-col flex-grow p-5 space-y-2">
            <span v-if="service.category" class="font-label-sm text-label-sm text-on-surface-variant">
              {{ service.category.name }}
            </span>
            <h3 class="font-title-md text-title-md text-deep-marsala group-hover:text-primary transition-colors line-clamp-2">
              {{ service.title }}
            </h3>
            <p class="font-title-md text-title-md text-primary mt-auto pt-2">
              {{ formatPrice(service.price) }}
            </p>
          </div>
        </router-link>
      </div>

      <!-- Empty state -->
      <div v-else class="text-center py-12">
        <p class="font-body-lg text-body-lg text-on-surface-variant">
          Próximamente nuevos servicios disponibles.
        </p>
      </div>
    </div>
  </section>
</template>
