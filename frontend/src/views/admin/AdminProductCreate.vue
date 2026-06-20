<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useProductsStore } from '../../stores/products.js'
import BaseButton from '../../components/ui/BaseButton.vue'

const router = useRouter()
const productsStore = useProductsStore()

const categories = computed(() => productsStore.categories)
const loading = ref(false)
const error = ref('')

const form = ref({
  title: '',
  price: '',
  stock_qty: '',
  category_id: '',
  is_published: false,
  description: '',
})
const files = ref([])

function onFileChange(e) {
  files.value = Array.from(e.target.files || [])
}

function buildFormData() {
  const fd = new FormData()
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
  if (files.value.length > 10) {
    error.value = 'No se permiten más de 10 imágenes por producto.'
    return
  }
  loading.value = true
  error.value = ''
  try {
    await productsStore.createProductWithImages(buildFormData(), files.value)
    router.push('/admin/products')
  } catch (err) {
    error.value = err.response?.data?.message || 'Error al crear el producto'
  } finally {
    loading.value = false
  }
}

onMounted(() => productsStore.fetchCategories())
</script>

<template>
  <div class="max-w-2xl mx-auto px-gutter py-12">
    <div class="mb-8">
      <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Nuevo Producto</h1>
      <p class="font-body-md text-body-md text-on-surface-variant mt-1">
        Completa la información para crear un nuevo producto.
      </p>
    </div>

    <div v-if="error" class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container">
      {{ error }}
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

        <!-- Images -->
        <div>
          <label for="images" class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Imágenes</label>
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
          <span class="font-body-md text-body-md text-on-surface">Publicar producto</span>
        </label>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3 pt-2">
          <BaseButton type="button" variant="outline" @click="router.push('/admin/products')">
            Cancelar
          </BaseButton>
          <BaseButton type="submit" variant="primary" :disabled="loading">
            {{ loading ? 'Guardando...' : 'Crear producto' }}
          </BaseButton>
        </div>
      </form>
    </div>
  </div>
</template>
