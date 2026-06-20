<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProductsStore } from '../../stores/products.js'
import BaseButton from '../../components/ui/BaseButton.vue'

const route = useRoute()
const router = useRouter()
const productsStore = useProductsStore()

const productId = computed(() => route.params.id)
const categories = computed(() => productsStore.categories)
const loading = ref(false)
const fetchError = ref('')
const saveError = ref('')
const loaded = ref(false)

const form = ref({
  title: '',
  price: '',
  stock_qty: '',
  category_id: '',
  is_published: false,
  description: '',
})
const images = ref([])
const files = ref([])
const deletingImageId = ref(null)

async function loadData() {
  loading.value = true
  fetchError.value = ''
  try {
    const [product] = await Promise.all([
      productsStore.fetchAdminProduct(productId.value),
      productsStore.fetchCategories(),
    ])
    form.value = {
      title: product.title ?? '',
      price: product.price ?? '',
      stock_qty: product.stock_qty ?? '',
      category_id: product.category?.id ?? product.category_id ?? '',
      is_published: !!product.is_published,
      description: product.description ?? '',
    }
    images.value = product.images ?? []
    loaded.value = true
  } catch (err) {
    fetchError.value = err.response?.data?.message || 'Error al cargar el producto'
  } finally {
    loading.value = false
  }
}

function onFileChange(e) {
  files.value = Array.from(e.target.files || [])
}

function buildFormData() {
  const fd = new FormData()
  // Laravel route is PATCH; multipart must be POSTed with method spoofing.
  fd.append('_method', 'PATCH')
  fd.append('title', form.value.title)
  fd.append('price', form.value.price)
  fd.append('stock_qty', form.value.stock_qty)
  fd.append('is_published', form.value.is_published ? '1' : '0')
  fd.append('category_id', form.value.category_id ?? '')
  fd.append('description', form.value.description ?? '')
  return fd
}

async function handleSubmit() {
  if (loading.value) return
  if (images.value.length + files.value.length > 10) {
    saveError.value = 'No se permiten más de 10 imágenes por producto.'
    return
  }
  loading.value = true
  saveError.value = ''
  try {
    await productsStore.updateProduct(productId.value, buildFormData())
    if (files.value.length > 0) {
      await productsStore.uploadImages(productId.value, files.value)
    }
    router.push('/admin/products')
  } catch (err) {
    saveError.value = err.response?.data?.message || 'Error al guardar el producto'
  } finally {
    loading.value = false
  }
}

async function handleDeleteImage(imageId) {
  if (deletingImageId.value === imageId) return
  deletingImageId.value = imageId
  try {
    await productsStore.deleteImage(productId.value, imageId)
    images.value = images.value.filter((img) => img.id !== imageId)
  } catch (err) {
    saveError.value = err.response?.data?.message || 'Error al eliminar la imagen'
  } finally {
    deletingImageId.value = null
  }
}

onMounted(loadData)
</script>

<template>
  <div class="max-w-2xl mx-auto px-gutter py-12">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Editar Producto</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-1">
          Modifica la información del producto.
        </p>
      </div>
      <BaseButton variant="outline" @click="router.push('/admin/products')">
        Cancelar
      </BaseButton>
    </div>

    <!-- Loading -->
    <div v-if="loading && !loaded" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <!-- Fetch error -->
    <div v-else-if="fetchError" class="text-center py-16">
      <p class="font-body-lg text-body-lg text-on-surface">{{ fetchError }}</p>
    </div>

    <!-- Form -->
    <div v-else-if="loaded">
      <div v-if="saveError" class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container">
        {{ saveError }}
      </div>

      <div class="bg-surface rounded-2xl border border-blush-canvas/20 p-8">
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- Title -->
          <div>
            <label for="title" class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Título</label>
            <input
              id="title"
              name="title"
              v-model="form.title"
              type="text"
              required
              class="w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
            />
          </div>

          <!-- Price + Stock -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="price" class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Precio</label>
              <input
                id="price"
                name="price"
                v-model="form.price"
                type="number"
                step="0.01"
                min="0"
                required
                class="w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
              />
            </div>
            <div>
              <label for="stock_qty" class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Stock</label>
              <input
                id="stock_qty"
                name="stock_qty"
                v-model="form.stock_qty"
                type="number"
                min="0"
                required
                class="w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
              />
            </div>
          </div>

          <!-- Category -->
          <div>
            <label for="category_id" class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Categoría</label>
            <select
              id="category_id"
              name="category_id"
              v-model="form.category_id"
              class="w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
            >
              <option value="">Sin categoría</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>

          <!-- Description -->
          <div>
            <label for="description" class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Descripción</label>
            <textarea
              id="description"
              name="description"
              v-model="form.description"
              rows="4"
              class="w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
            />
          </div>

          <!-- Existing images -->
          <div v-if="images.length">
            <label class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Imágenes actuales</label>
            <div class="flex flex-wrap gap-3">
              <div
                v-for="img in images"
                :key="img.id"
                data-existing-image
                class="relative w-20 h-20 rounded-lg overflow-hidden border border-blush-canvas/30 group"
              >
                <img :src="img.url" alt="" class="w-full h-full object-cover" />
                <button
                  type="button"
                  data-delete-image
                  @click="handleDeleteImage(img.id)"
                  :disabled="deletingImageId === img.id"
                  class="absolute top-0.5 right-0.5 w-6 h-6 rounded-full bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                  aria-label="Eliminar imagen"
                >
                  <span class="material-symbols-outlined text-[16px]" aria-hidden="true">close</span>
                </button>
              </div>
            </div>
          </div>

          <!-- New images -->
          <div>
            <label for="images" class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Agregar imágenes</label>
            <input
              id="images"
              type="file"
              accept="image/*"
              multiple
              @change="onFileChange"
              class="block w-full font-body-sm text-body-sm text-on-surface-variant file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-apricot-glow file:text-deep-marsala file:font-label-md"
            />
          </div>

          <!-- Published -->
          <label for="is_published" class="flex items-center gap-3 cursor-pointer">
            <input id="is_published" name="is_published" v-model="form.is_published" type="checkbox" class="w-4 h-4 rounded accent-primary" />
            <span class="font-body-md text-body-md text-on-surface">Producto publicado</span>
          </label>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-3 pt-2">
            <BaseButton type="button" variant="outline" @click="router.push('/admin/products')">
              Cancelar
            </BaseButton>
            <BaseButton type="submit" variant="primary" :disabled="loading">
              {{ loading ? 'Guardando...' : 'Guardar cambios' }}
            </BaseButton>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
