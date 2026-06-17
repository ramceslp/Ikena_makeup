<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useServicesStore } from '../../stores/services.js'
import BaseBadge from '../../components/ui/BaseBadge.vue'
import BaseButton from '../../components/ui/BaseButton.vue'

const router = useRouter()
const servicesStore = useServicesStore()

const services = computed(() => servicesStore.services)
const loading = computed(() => servicesStore.loading)
const error = computed(() => servicesStore.error)

const deleting = ref(null)
const deleteError = ref('')

async function loadServices() {
  await servicesStore.fetchAdminServices()
}

async function handleDelete(id) {
  if (!confirm('¿Eliminar este servicio? Esta acción no se puede deshacer.')) return
  deleting.value = id
  deleteError.value = ''
  try {
    await servicesStore.deleteService(id)
    await loadServices()
  } catch (err) {
    deleteError.value = err.response?.data?.message || 'Error al eliminar el servicio'
  } finally {
    deleting.value = null
  }
}

onMounted(loadServices)
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter py-12">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Gestión de Servicios</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-1">
          Administra el catálogo de servicios (publicados e inéditos)
        </p>
      </div>
      <BaseButton variant="primary" @click="router.push('/admin/services/new')">
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
        Nuevo servicio
      </BaseButton>
    </div>

    <!-- Error -->
    <div v-if="error" class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container">
      {{ error }}
    </div>
    <div v-if="deleteError" class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container">
      {{ deleteError }}
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <!-- Empty -->
    <div v-else-if="!services.length" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-blush-canvas mb-4" aria-hidden="true">inventory_2</span>
      <p class="font-body-lg text-body-lg text-on-surface-variant">No hay servicios aún</p>
      <BaseButton variant="primary" class="mt-6" @click="router.push('/admin/services/new')">
        Crear primer servicio
      </BaseButton>
    </div>

    <!-- Services table -->
    <div v-else class="bg-surface rounded-2xl border border-blush-canvas/20 overflow-hidden">
      <table class="w-full">
        <thead class="border-b border-blush-canvas/20 bg-surface-container-low">
          <tr>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">Título</th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant hidden md:table-cell">Precio</th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant hidden md:table-cell">Categoría</th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">Estado</th>
            <th class="text-right px-6 py-4 font-label-md text-label-md text-on-surface-variant">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-blush-canvas/10">
          <tr
            v-for="svc in services"
            :key="svc.id"
            class="hover:bg-surface-container-low transition-colors"
          >
            <!-- Thumbnail + title -->
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <img
                  v-if="svc.thumbnail"
                  :src="svc.thumbnail"
                  :alt="svc.title"
                  class="w-10 h-10 rounded-lg object-cover shrink-0"
                />
                <div
                  v-else
                  class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center shrink-0"
                >
                  <span class="material-symbols-outlined text-blush-canvas" aria-hidden="true">image</span>
                </div>
                <span class="font-body-md text-body-md text-on-surface line-clamp-1">{{ svc.title }}</span>
              </div>
            </td>

            <!-- Price -->
            <td class="px-6 py-4 font-body-md text-body-md text-on-surface hidden md:table-cell">
              ${{ parseFloat(svc.price).toFixed(2) }}
            </td>

            <!-- Category -->
            <td class="px-6 py-4 hidden md:table-cell">
              <BaseBadge v-if="svc.category" variant="secondary">{{ svc.category.name }}</BaseBadge>
              <span v-else class="font-label-sm text-label-sm text-outline">—</span>
            </td>

            <!-- Published badge -->
            <td class="px-6 py-4">
              <BaseBadge :variant="svc.is_published ? 'accent' : 'default'">
                {{ svc.is_published ? 'Publicado' : 'Borrador' }}
              </BaseBadge>
            </td>

            <!-- Actions -->
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <button
                  type="button"
                  @click="router.push(`/admin/services/${svc.id}/edit`)"
                  class="p-2 rounded-lg hover:bg-surface-container transition-colors text-on-surface-variant hover:text-primary"
                  aria-label="Editar servicio"
                >
                  <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                </button>
                <button
                  type="button"
                  @click="handleDelete(svc.id)"
                  :disabled="deleting === svc.id"
                  class="p-2 rounded-lg hover:bg-error-container transition-colors text-on-surface-variant hover:text-error"
                  aria-label="Eliminar servicio"
                >
                  <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
