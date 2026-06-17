<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useServicesStore } from '../../stores/services.js'
import AdminServiceForm from '../../components/admin/AdminServiceForm.vue'

const router = useRouter()
const servicesStore = useServicesStore()

const loading = ref(false)
const error = ref('')
const categories = ref([])

async function loadCategories() {
  await servicesStore.fetchCategories()
  categories.value = servicesStore.categories
}

async function handleSubmit(formData, files = []) {
  loading.value = true
  error.value = ''
  try {
    // Two-step: create the service record first, then attach images to the new id.
    // The backend POST /admin/services ignores images[] — they must go to
    // POST /admin/services/{id}/images after the record exists.
    await servicesStore.createServiceWithImages(formData, files)
    router.push('/admin/services')
  } catch (err) {
    error.value = err.response?.data?.message || 'Error al crear el servicio'
  } finally {
    loading.value = false
  }
}

onMounted(loadCategories)
</script>

<template>
  <div class="max-w-2xl mx-auto px-gutter py-12">
    <div class="mb-8">
      <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Nuevo Servicio</h1>
      <p class="font-body-md text-body-md text-on-surface-variant mt-1">
        Completa la información para crear un nuevo servicio.
      </p>
    </div>

    <div v-if="error" class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container">
      {{ error }}
    </div>

    <div class="bg-surface rounded-2xl border border-blush-canvas/20 p-8">
      <AdminServiceForm
        :categories="categories"
        :loading="loading"
        @submit="handleSubmit"
      />
    </div>
  </div>
</template>
