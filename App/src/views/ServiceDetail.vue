<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useServicesStore } from '../stores/services.js'
import { useBookingStore } from '../stores/booking.js'
import { formatPrice } from '../utils/formatPrice.js'
import ServiceGallery from '../components/service/ServiceGallery.vue'
import SlotPicker from '../components/booking/SlotPicker.vue'
import BookingForm from '../components/booking/BookingForm.vue'
import BaseBadge from '../components/ui/BaseBadge.vue'
import BaseButton from '../components/ui/BaseButton.vue'

// Ported from frontend/src/views/ServiceDetail.vue (mobile-capacitor-setup
// Phase 7). Uses the shared services/formatPrice.js util instead of a local
// duplicate (per Phase 5/6 convention — see stores/shared/buildParams.js).
const route = useRoute()
const servicesStore = useServicesStore()
const bookingStore = useBookingStore()

const service = computed(() => servicesStore.currentService)
const loading = computed(() => servicesStore.loading)
const error = computed(() => servicesStore.error)

const isBookable = computed(
  () => service.value?.availability_type === 'by_appointment',
)

const selectedSlot = ref(null)

function availabilityLabel(type) {
  if (type === 'immediate') return 'Disponibilidad inmediata'
  if (type === 'by_appointment') return 'Por cita previa'
  return type
}

function onSlotSelected(slot) {
  selectedSlot.value = slot
  // Clear previous booking error when user picks a new slot
  bookingStore.bookingError = null
}

// SlotPicker emits this when its own local selection goes stale — either the
// user switched days, or the store just processed a 409 slot conflict. In
// both cases the previously selected slot is no longer valid here either.
function onSelectionCleared() {
  selectedSlot.value = null
}

function onBookingSuccess(result) {
  // BookingForm renders its own confirmation state; this can emit
  // analytics / a toast if needed later.
  void result
}

// Fetches only on initial mount (same inherited limitation as frontend/'s
// equivalent view) — a detail→detail navigation reusing this route component
// would not re-fetch. No such navigation exists in this PR; add a `watch` on
// route.params.slug if/when deep-linking between detail pages is introduced.
onMounted(async () => {
  try {
    await servicesStore.fetchService(route.params.slug)
    // SlotPicker fetches its own day-summary calendar once it mounts
    // (it only mounts when isBookable is true, see template below).
  } catch {
    // 404 handled by the error state below
  }
})
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter py-16">
    <!-- Loading -->
    <div v-if="loading" data-loading class="flex items-center justify-center py-32">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <!-- Error / 404 -->
    <div v-else-if="error" class="text-center py-32">
      <span class="material-symbols-outlined text-5xl text-error mb-4" aria-hidden="true">error</span>
      <p class="font-body-lg text-body-lg text-on-surface mb-4">{{ error }}</p>
      <RouterLink to="/services">
        <BaseButton variant="outline">Volver al catálogo</BaseButton>
      </RouterLink>
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

        <!-- Booking section: only for by_appointment services -->
        <div v-if="isBookable" data-booking-section class="flex flex-col gap-6 mt-2">
          <div>
            <h2 class="font-title-md text-title-md text-on-surface mb-3">
              Selecciona un horario
            </h2>
            <!-- Slot picker (manages its own day-summary + day-slot loading state) -->
            <div data-slot-picker>
              <SlotPicker
                :service-id="service.id"
                @slot-selected="onSlotSelected"
                @selection-cleared="onSelectionCleared"
              />
            </div>
          </div>

          <!-- Booking form (always shown when bookable — disables submit until slot selected) -->
          <BookingForm
            :selected-slot="selectedSlot"
            :service="service"
            @booking-success="onBookingSuccess"
          />
        </div>

        <!-- Non-bookable CTA (immediate type) -->
        <div v-else class="mt-auto">
          <BaseButton variant="primary" size="lg" class="w-full">
            Contactar para más información
          </BaseButton>
        </div>
      </div>
    </div>
  </div>
</template>
