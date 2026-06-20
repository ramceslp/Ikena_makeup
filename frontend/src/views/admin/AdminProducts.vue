<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useProductsStore } from '../../stores/products.js'
import BaseBadge from '../../components/ui/BaseBadge.vue'
import BaseButton from '../../components/ui/BaseButton.vue'

const router = useRouter()
const productsStore = useProductsStore()

const products = computed(() => productsStore.products)
const loading = computed(() => productsStore.loading)
const error = computed(() => productsStore.error)

const deleting = ref(null)
const deleteError = ref('')

async function loadProducts() {
  await productsStore.fetchProducts()
}

function stockStateClass(state) {
  return {
    'bg-red-100 text-red-700': state === 'Agotado',
    'bg-amber-100 text-amber-700': state === 'Últimas unidades',
    'bg-green-100 text-green-700': state === 'En Stock',
  }
}

async function handleDelete(id) {
  if (!window.confirm('¿Eliminar este producto? Esta acción no se puede deshacer.')) return
  deleting.value = id
  deleteError.value = ''
  try {
    await productsStore.deleteProduct(id)
    await loadProducts()
  } catch (err) {
    deleteError.value = err.response?.data?.message || 'Error al eliminar el producto'
  } finally {
    deleting.value = null
  }
}

onMounted(loadProducts)
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter py-12">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Gestión de Productos</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-1">
          Administra el catálogo de productos (publicados y borradores)
        </p>
      </div>
      <BaseButton variant="primary" @click="router.push('/admin/products/new')">
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
        Nuevo producto
      </BaseButton>
    </div>

    <!-- Errors -->
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
    <div v-else-if="!products.length" data-empty-state class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-blush-canvas mb-4" aria-hidden="true">inventory_2</span>
      <p class="font-body-lg text-body-lg text-on-surface-variant">No hay productos aún</p>
      <BaseButton variant="primary" class="mt-6" @click="router.push('/admin/products/new')">
        Crear primer producto
      </BaseButton>
    </div>

    <!-- Products table -->
    <div v-else class="bg-surface rounded-2xl border border-blush-canvas/20 overflow-hidden">
      <table class="w-full">
        <thead class="border-b border-blush-canvas/20 bg-surface-container-low">
          <tr>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">Producto</th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant hidden md:table-cell">Precio</th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant hidden md:table-cell">Stock</th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">Estado</th>
            <th class="text-right px-6 py-4 font-label-md text-label-md text-on-surface-variant">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-blush-canvas/10">
          <tr
            v-for="product in products"
            :key="product.id"
            data-product-row
            class="hover:bg-surface-container-low transition-colors"
          >
            <!-- Thumbnail + title -->
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <img
                  v-if="product.thumbnail"
                  :src="product.thumbnail"
                  :alt="product.title"
                  class="w-10 h-10 rounded-lg object-cover shrink-0"
                />
                <div
                  v-else
                  class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center shrink-0"
                >
                  <span class="material-symbols-outlined text-blush-canvas" aria-hidden="true">image</span>
                </div>
                <div class="min-w-0">
                  <span class="font-body-md text-body-md text-on-surface line-clamp-1">{{ product.title }}</span>
                  <BaseBadge v-if="product.category" variant="secondary" class="mt-0.5">{{ product.category.name }}</BaseBadge>
                </div>
              </div>
            </td>

            <!-- Price -->
            <td class="px-6 py-4 font-body-md text-body-md text-on-surface hidden md:table-cell">
              ${{ parseFloat(product.price).toFixed(2) }}
            </td>

            <!-- Stock state -->
            <td class="px-6 py-4 hidden md:table-cell">
              <span
                v-if="product.stock_state"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                :class="stockStateClass(product.stock_state)"
              >
                {{ product.stock_state }}
              </span>
            </td>

            <!-- Published badge -->
            <td class="px-6 py-4">
              <BaseBadge :variant="product.is_published ? 'accent' : 'default'">
                {{ product.is_published ? 'Publicado' : 'Borrador' }}
              </BaseBadge>
            </td>

            <!-- Actions -->
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <button
                  type="button"
                  data-edit-btn
                  @click="router.push(`/admin/products/${product.id}/edit`)"
                  class="p-2 rounded-lg hover:bg-surface-container transition-colors text-on-surface-variant hover:text-primary"
                  aria-label="Editar producto"
                >
                  <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                </button>
                <button
                  type="button"
                  data-delete-btn
                  @click="handleDelete(product.id)"
                  :disabled="deleting === product.id"
                  class="p-2 rounded-lg hover:bg-error-container transition-colors text-on-surface-variant hover:text-error"
                  aria-label="Eliminar producto"
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
