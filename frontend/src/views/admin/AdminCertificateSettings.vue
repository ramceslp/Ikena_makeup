<script setup>
import { ref, computed, onMounted } from 'vue'
import { useCertificateSettingsStore } from '../../stores/certificateSettings.js'
import AdminCertificateSettingsForm from '../../components/admin/AdminCertificateSettingsForm.vue'

const store = useCertificateSettingsStore()

const settings = computed(() => store.settings)
const loading = computed(() => store.loading)

const saving = ref(false)
const saveError = ref('')
const saveSuccess = ref(false)

onMounted(() => store.fetchAdminSettings())

async function onSubmit(formData) {
  saving.value = true
  saveError.value = ''
  saveSuccess.value = false
  try {
    await store.updateSettings(formData)
    saveSuccess.value = true
  } catch (err) {
    saveError.value = err.response?.data?.message || 'Error al guardar la configuración'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl mx-auto px-gutter py-12">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Certificados</h1>
      <p class="font-body-md text-body-md text-on-surface-variant mt-1">
        Configura la marca de los certificados: logo, nombre del negocio, textos, firmante y diseño.
      </p>
    </div>

    <!-- States -->
    <div v-if="saveError" class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container">
      {{ saveError }}
    </div>
    <div v-if="saveSuccess" class="mb-4 p-4 bg-surface-container rounded-xl font-body-md text-body-md text-primary" role="status">
      Configuración guardada correctamente.
    </div>

    <!-- Loading -->
    <div v-if="loading && !settings" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <!-- Form -->
    <div v-else class="bg-surface rounded-2xl border border-blush-canvas/20 p-6">
      <AdminCertificateSettingsForm :settings="settings" :loading="saving" @submit="onSubmit" />
    </div>
  </div>
</template>
