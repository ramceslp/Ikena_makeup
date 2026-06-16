<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useInstructorStore } from '../stores/instructor.js'
import { useCoursesStore } from '../stores/courses.js'

const router = useRouter()
const instructorStore = useInstructorStore()
const coursesStore = useCoursesStore()

const title = ref('')
const description = ref('')
const price = ref(0)
const thumbnail = ref('')
const categoryId = ref('')
const offersCertificate = ref(false)

const loading = computed(() => instructorStore.loading)
const validationErrors = computed(() => instructorStore.validationErrors)
const error = computed(() => instructorStore.error)

onMounted(() => {
  coursesStore.fetchCategories()
})

async function handleSubmit() {
  const payload = {
    title: title.value.trim(),
    description: description.value.trim(),
    price: Number(price.value),
    category_id: categoryId.value ? Number(categoryId.value) : null,
    offers_certificate: offersCertificate.value,
  }
  if (thumbnail.value.trim()) {
    payload.thumbnail = thumbnail.value.trim()
  }

  try {
    const course = await instructorStore.createCourse(payload)
    router.push({ name: 'InstructorCourseEdit', params: { slug: course.slug } })
  } catch {
    // Error already in store
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-2xl mx-auto px-4 py-10">
      <h1 class="text-2xl font-bold text-brand-primary mb-8">Crear nuevo curso</h1>

      <!-- Global error -->
      <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700 flex items-start gap-2 mb-6">
        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p>{{ error }}</p>
      </div>

      <form @submit.prevent="handleSubmit" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
        <!-- Title -->
        <div>
          <label for="course-title" class="block text-sm font-medium text-gray-700 mb-1">
            Título <span class="text-red-500">*</span>
          </label>
          <input
            id="course-title"
            v-model="title"
            type="text"
            required
            placeholder="Ej: Maquillaje profesional desde cero"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent"
            :class="validationErrors.title ? 'border-red-400' : 'border-gray-300'"
          />
          <p v-if="validationErrors.title" class="text-red-600 text-xs mt-1 flex items-center gap-1">
            <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            {{ Array.isArray(validationErrors.title) ? validationErrors.title[0] : validationErrors.title }}
          </p>
        </div>

        <!-- Description -->
        <div>
          <label for="course-description" class="block text-sm font-medium text-gray-700 mb-1">
            Descripción <span class="text-red-500">*</span>
          </label>
          <textarea
            id="course-description"
            v-model="description"
            required
            rows="4"
            placeholder="Describe de qué trata tu curso..."
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent resize-none"
            :class="validationErrors.description ? 'border-red-400' : 'border-gray-300'"
          />
          <p v-if="validationErrors.description" class="text-red-600 text-xs mt-1 flex items-center gap-1">
            <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            {{ Array.isArray(validationErrors.description) ? validationErrors.description[0] : validationErrors.description }}
          </p>
        </div>

        <!-- Price -->
        <div>
          <label for="course-price" class="block text-sm font-medium text-gray-700 mb-1">Precio (USD)</label>
          <input
            id="course-price"
            v-model="price"
            type="number"
            min="0"
            step="0.01"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent"
            :class="validationErrors.price ? 'border-red-400' : 'border-gray-300'"
          />
          <p v-if="validationErrors.price" class="text-red-600 text-xs mt-1 flex items-center gap-1">
            <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            {{ Array.isArray(validationErrors.price) ? validationErrors.price[0] : validationErrors.price }}
          </p>
        </div>

        <!-- Thumbnail URL -->
        <div>
          <label for="course-thumbnail" class="block text-sm font-medium text-gray-700 mb-1">URL de portada (opcional)</label>
          <input
            id="course-thumbnail"
            v-model="thumbnail"
            type="url"
            placeholder="https://ejemplo.com/imagen.jpg"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent"
            :class="validationErrors.thumbnail ? 'border-red-400' : 'border-gray-300'"
          />
          <p v-if="validationErrors.thumbnail" class="text-red-600 text-xs mt-1 flex items-center gap-1">
            <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            {{ Array.isArray(validationErrors.thumbnail) ? validationErrors.thumbnail[0] : validationErrors.thumbnail }}
          </p>
        </div>

        <!-- Category -->
        <div>
          <label for="course-category" class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
          <select
            id="course-category"
            v-model="categoryId"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent"
            :class="validationErrors.category_id ? 'border-red-400' : 'border-gray-300'"
          >
            <option value="">Sin categoría</option>
            <option v-for="cat in coursesStore.categories" :key="cat.id" :value="cat.id">
              {{ cat.name }}
            </option>
          </select>
          <p v-if="validationErrors.category_id" class="text-red-600 text-xs mt-1 flex items-center gap-1">
            <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            {{ Array.isArray(validationErrors.category_id) ? validationErrors.category_id[0] : validationErrors.category_id }}
          </p>
        </div>

        <!-- Offers certificate -->
        <div class="flex items-center gap-3">
          <input
            id="course-certificate"
            v-model="offersCertificate"
            type="checkbox"
            class="w-4 h-4 accent-brand-accent rounded"
          />
          <label for="course-certificate" class="text-sm font-medium text-gray-700 select-none cursor-pointer">
            Ofrece certificado al completar el curso
          </label>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-3 pt-2">
          <RouterLink
            to="/instructor"
            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors font-medium"
          >
            Cancelar
          </RouterLink>
          <button
            type="submit"
            :disabled="loading"
            class="bg-brand-accent text-brand-primary px-5 py-2 rounded-lg font-semibold hover:opacity-90 transition-opacity text-sm disabled:opacity-50 flex items-center gap-2"
          >
            <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ loading ? 'Creando...' : 'Crear curso' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
