<script setup>
import { ref, watch } from 'vue'
import BaseButton from '../ui/BaseButton.vue'
import BaseInput from '../ui/BaseInput.vue'

const props = defineProps({
  service: {
    type: Object,
    default: null,
  },
  categories: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['submit', 'delete-image'])

// Form fields
const title = ref(props.service?.title ?? '')
const description = ref(props.service?.description ?? '')
const price = ref(props.service?.price ?? '')
const duration_hours = ref(props.service?.duration_hours ?? '')
const availability_type = ref(props.service?.availability_type ?? 'by_appointment')
const category_id = ref(props.service?.category_id ?? '')
const is_published = ref(props.service?.is_published ?? false)
const selectedFiles = ref([])

// Sync when service prop changes (edit mode switching)
watch(
  () => props.service,
  (svc) => {
    if (!svc) return
    title.value = svc.title ?? ''
    description.value = svc.description ?? ''
    price.value = svc.price ?? ''
    duration_hours.value = svc.duration_hours ?? ''
    availability_type.value = svc.availability_type ?? 'by_appointment'
    category_id.value = svc.category_id ?? ''
    is_published.value = svc.is_published ?? false
  },
)

function handleFileChange(event) {
  selectedFiles.value = Array.from(event.target.files)
}

function handleSubmit() {
  const fd = new FormData()
  fd.append('title', title.value)
  fd.append('description', description.value)
  fd.append('price', price.value)
  fd.append('duration_hours', duration_hours.value)
  fd.append('availability_type', availability_type.value)
  if (category_id.value) fd.append('category_id', category_id.value)
  fd.append('is_published', is_published.value ? '1' : '0')
  // Files are passed as a separate second argument so the parent view
  // can route them to the correct endpoint (POST /services/{id}/images).
  // Do NOT append images[] here — the backend create endpoint ignores them.
  emit('submit', fd, selectedFiles.value)
}

function handleDeleteImage(imageId) {
  emit('delete-image', imageId)
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="flex flex-col gap-6">
    <!-- Title -->
    <div class="flex flex-col gap-1">
      <label for="title" class="font-label-md text-label-md text-on-surface-variant">Título *</label>
      <input
        id="title"
        name="title"
        v-model="title"
        type="text"
        required
        placeholder="Nombre del servicio"
        class="px-4 py-2 bg-surface-container-low border border-blush-canvas/30 rounded-xl font-body-md text-body-md focus:ring-1 focus:ring-primary outline-none"
      />
    </div>

    <!-- Description -->
    <div class="flex flex-col gap-1">
      <label for="description" class="font-label-md text-label-md text-on-surface-variant">Descripción</label>
      <textarea
        id="description"
        name="description"
        v-model="description"
        rows="4"
        placeholder="Descripción del servicio"
        class="px-4 py-2 bg-surface-container-low border border-blush-canvas/30 rounded-xl font-body-md text-body-md focus:ring-1 focus:ring-primary outline-none resize-none"
      />
    </div>

    <!-- Price + Duration row -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="flex flex-col gap-1">
        <label for="price" class="font-label-md text-label-md text-on-surface-variant">Precio *</label>
        <input
          id="price"
          name="price"
          v-model="price"
          type="number"
          min="0"
          step="0.01"
          required
          placeholder="0.00"
          class="px-4 py-2 bg-surface-container-low border border-blush-canvas/30 rounded-xl font-body-md text-body-md focus:ring-1 focus:ring-primary outline-none"
        />
      </div>
      <div class="flex flex-col gap-1">
        <label for="duration_hours" class="font-label-md text-label-md text-on-surface-variant">Duración (horas) *</label>
        <input
          id="duration_hours"
          name="duration_hours"
          v-model="duration_hours"
          type="number"
          min="0"
          required
          placeholder="2"
          class="px-4 py-2 bg-surface-container-low border border-blush-canvas/30 rounded-xl font-body-md text-body-md focus:ring-1 focus:ring-primary outline-none"
        />
      </div>
    </div>

    <!-- Availability type -->
    <div class="flex flex-col gap-1">
      <label for="availability_type" class="font-label-md text-label-md text-on-surface-variant">Disponibilidad *</label>
      <select
        id="availability_type"
        name="availability_type"
        v-model="availability_type"
        required
        class="px-4 py-2 bg-surface-container-low border border-blush-canvas/30 rounded-xl font-body-md text-body-md focus:ring-1 focus:ring-primary outline-none"
      >
        <option value="by_appointment">Por cita previa</option>
        <option value="immediate">Disponibilidad inmediata</option>
      </select>
    </div>

    <!-- Category -->
    <div class="flex flex-col gap-1">
      <label for="category_id" class="font-label-md text-label-md text-on-surface-variant">Categoría</label>
      <select
        id="category_id"
        name="category_id"
        v-model="category_id"
        class="px-4 py-2 bg-surface-container-low border border-blush-canvas/30 rounded-xl font-body-md text-body-md focus:ring-1 focus:ring-primary outline-none"
      >
        <option value="">Sin categoría</option>
        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
          {{ cat.name }}
        </option>
      </select>
    </div>

    <!-- Is published toggle -->
    <div class="flex items-center gap-3">
      <input
        id="is_published"
        name="is_published"
        v-model="is_published"
        type="checkbox"
        data-published-toggle
        class="w-4 h-4 accent-primary"
      />
      <label for="is_published" class="font-body-md text-body-md text-on-surface">
        Publicado
      </label>
    </div>

    <!-- Existing images (edit mode) -->
    <div v-if="service?.images?.length" class="flex flex-col gap-2">
      <span class="font-label-md text-label-md text-on-surface-variant">Imágenes actuales</span>
      <div class="flex flex-wrap gap-3">
        <div
          v-for="img in service.images"
          :key="img.id"
          data-existing-image
          class="relative w-20 h-20 rounded-lg overflow-hidden border border-blush-canvas/30"
        >
          <img :src="img.url" :alt="`Imagen ${img.sort_order}`" class="w-full h-full object-cover" />
          <button
            type="button"
            data-delete-image
            @click="handleDeleteImage(img.id)"
            class="absolute top-0.5 right-0.5 w-5 h-5 rounded-full bg-error text-on-error flex items-center justify-center hover:bg-error/80 transition-colors"
            :aria-label="`Eliminar imagen ${img.sort_order}`"
          >
            <span class="material-symbols-outlined text-[12px]" aria-hidden="true">close</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Image upload -->
    <div class="flex flex-col gap-1">
      <label for="images" class="font-label-md text-label-md text-on-surface-variant">Subir imágenes (máx. 10)</label>
      <input
        id="images"
        name="images"
        type="file"
        multiple
        accept="image/jpeg,image/png,image/webp"
        @change="handleFileChange"
        class="font-body-md text-body-md text-on-surface-variant"
      />
      <span v-if="selectedFiles.length" class="font-label-sm text-label-sm text-outline">
        {{ selectedFiles.length }} archivo(s) seleccionado(s)
      </span>
    </div>

    <!-- Submit -->
    <div class="flex gap-3 justify-end">
      <BaseButton type="submit" variant="primary" :disabled="loading">
        {{ service ? 'Guardar cambios' : 'Crear servicio' }}
      </BaseButton>
    </div>
  </form>
</template>
