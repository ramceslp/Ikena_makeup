<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useServicesStore } from '../../stores/services.js'
import AdminServiceForm from '../../components/admin/AdminServiceForm.vue'
import BaseButton from '../../components/ui/BaseButton.vue'

const route = useRoute()
const router = useRouter()
const servicesStore = useServicesStore()

const serviceId = computed(() => route.params.id)
const service = ref(null)
const categories = ref([])
const loading = ref(false)
const fetchError = ref('')
const saveError = ref('')

async function loadData() {
  loading.value = true
  fetchError.value = ''
  try {
    await Promise.all([
      servicesStore.fetchAdminService(serviceId.value),
      servicesStore.fetchCategories(),
    ])
    service.value = servicesStore.currentService
    categories.value = servicesStore.categories
  } catch (err) {
    fetchError.value = err.response?.data?.message || 'Error al cargar el servicio'
  } finally {
    loading.value = false
  }
}

async function handleSubmit(formData, files = []) {
  loading.value = true
  saveError.value = ''
  try {
    await servicesStore.updateService(serviceId.value, formData)
    if (files && files.length > 0) {
      await servicesStore.uploadImages(serviceId.value, files)
    }
    // Refresh the service data so the image list reflects newly uploaded images.
    await servicesStore.fetchAdminService(serviceId.value)
    service.value = servicesStore.currentService
  } catch (err) {
    saveError.value = err.response?.data?.message || 'Error al guardar el servicio'
  } finally {
    loading.value = false
  }
}

async function handleDeleteImage(imageId) {
  try {
    await servicesStore.deleteImage(serviceId.value, imageId)
    // Refresh service data to update images list
    await loadData()
  } catch (err) {
    saveError.value = err.response?.data?.message || 'Error al eliminar la imagen'
  }
}

onMounted(loadData)
</script>

<template>
  <div class="max-w-2xl mx-auto px-gutter py-12">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Editar Servicio</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-1">
          Modifica la información del servicio.
        </p>
      </div>
      <BaseButton variant="outline" @click="router.push('/admin/services')">
        Cancelar
      </BaseButton>
    </div>

    <!-- Loading -->
    <div v-if="loading && !service" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <!-- Fetch error -->
    <div v-else-if="fetchError" class="text-center py-16">
      <p class="font-body-lg text-body-lg text-on-surface">{{ fetchError }}</p>
    </div>

    <!-- Form -->
    <div v-else-if="service">
      <div v-if="saveError" class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container">
        {{ saveError }}
      </div>

      <div class="bg-surface rounded-2xl border border-blush-canvas/20 p-8">
        <AdminServiceForm
          :service="service"
          :categories="categories"
          :loading="loading"
          @submit="handleSubmit"
          @delete-image="handleDeleteImage"
        />
      </div>
    </div>
  </div>
</template>
