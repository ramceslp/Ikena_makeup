<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useInstructorStore } from '../stores/instructor.js'
import SectionEditor from '../components/SectionEditor.vue'

const route = useRoute()
const instructorStore = useInstructorStore()

const course = computed(() => instructorStore.currentCourse)
const loading = computed(() => instructorStore.loading)
const error = computed(() => instructorStore.error)
const validationErrors = computed(() => instructorStore.validationErrors)

// Local form fields — synced from currentCourse
const formTitle = ref('')
const formDescription = ref('')
const formPrice = ref(0)
const formThumbnail = ref('')
const savingCourse = ref(false)

// New section input
const newSectionTitle = ref('')
const addingSection = ref(false)

watch(course, (val) => {
  if (val) {
    formTitle.value = val.title ?? ''
    formDescription.value = val.description ?? ''
    formPrice.value = val.price ?? 0
    formThumbnail.value = val.thumbnail ?? ''
  }
}, { immediate: true })

onMounted(() => {
  instructorStore.fetchCourse(route.params.slug)
})

async function handleSaveCourse() {
  savingCourse.value = true
  try {
    await instructorStore.updateCourse(route.params.slug, {
      title: formTitle.value.trim(),
      description: formDescription.value.trim(),
      price: Number(formPrice.value),
      thumbnail: formThumbnail.value.trim() || undefined,
    })
  } finally {
    savingCourse.value = false
  }
}

async function handlePublishToggle() {
  if (!course.value) return
  if (course.value.is_published) {
    await instructorStore.unpublish(course.value.slug)
  } else {
    await instructorStore.publish(course.value.slug)
  }
}

async function handleAddSection() {
  const title = newSectionTitle.value.trim()
  if (!title) return
  addingSection.value = true
  try {
    await instructorStore.createSection(route.params.slug, title)
    newSectionTitle.value = ''
  } finally {
    addingSection.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-10">
      <!-- Loading initial -->
      <div v-if="loading && !course" class="flex justify-center py-20">
        <svg class="animate-spin w-8 h-8 text-brand-accent" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <!-- Error state -->
      <div v-else-if="error && !course" class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700 flex items-start gap-2">
        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p>{{ error }}</p>
      </div>

      <template v-else-if="course">
        <!-- Page header -->
        <div class="flex items-center justify-between mb-8 flex-wrap gap-3">
          <h1 class="text-2xl font-bold text-brand-primary">Editar curso</h1>
          <button
            @click="handlePublishToggle"
            :disabled="loading"
            class="text-sm px-4 py-2 rounded-lg font-medium transition-colors disabled:opacity-50 flex items-center gap-2"
            :class="course.is_published
              ? 'text-gray-600 border border-gray-300 hover:bg-gray-50'
              : 'bg-brand-accent text-brand-primary hover:opacity-90'"
          >
            <svg v-if="course.is_published" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            {{ course.is_published ? 'Despublicar' : 'Publicar' }}
          </button>
        </div>

        <!-- Course details form -->
        <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
          <h2 class="text-base font-semibold text-brand-primary mb-5">Información del curso</h2>

          <!-- Non-422 error -->
          <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-3 text-red-700 text-sm flex items-start gap-2 mb-4">
            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p>{{ error }}</p>
          </div>

          <form @submit.prevent="handleSaveCourse" class="space-y-4">
            <!-- Title -->
            <div>
              <label for="edit-title" class="block text-sm font-medium text-gray-700 mb-1">Título</label>
              <input
                id="edit-title"
                v-model="formTitle"
                type="text"
                required
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent"
                :class="validationErrors.title ? 'border-red-400' : 'border-gray-300'"
              />
              <p v-if="validationErrors.title" class="text-red-600 text-xs mt-1">
                {{ Array.isArray(validationErrors.title) ? validationErrors.title[0] : validationErrors.title }}
              </p>
            </div>

            <!-- Description -->
            <div>
              <label for="edit-description" class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
              <textarea
                id="edit-description"
                v-model="formDescription"
                rows="4"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent resize-none"
                :class="validationErrors.description ? 'border-red-400' : 'border-gray-300'"
              />
              <p v-if="validationErrors.description" class="text-red-600 text-xs mt-1">
                {{ Array.isArray(validationErrors.description) ? validationErrors.description[0] : validationErrors.description }}
              </p>
            </div>

            <!-- Price -->
            <div>
              <label for="edit-price" class="block text-sm font-medium text-gray-700 mb-1">Precio (USD)</label>
              <input
                id="edit-price"
                v-model="formPrice"
                type="number"
                min="0"
                step="0.01"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent"
                :class="validationErrors.price ? 'border-red-400' : 'border-gray-300'"
              />
              <p v-if="validationErrors.price" class="text-red-600 text-xs mt-1">
                {{ Array.isArray(validationErrors.price) ? validationErrors.price[0] : validationErrors.price }}
              </p>
            </div>

            <!-- Thumbnail -->
            <div>
              <label for="edit-thumbnail" class="block text-sm font-medium text-gray-700 mb-1">URL de portada</label>
              <input
                id="edit-thumbnail"
                v-model="formThumbnail"
                type="url"
                placeholder="https://ejemplo.com/imagen.jpg"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent"
                :class="validationErrors.thumbnail ? 'border-red-400' : 'border-gray-300'"
              />
              <p v-if="validationErrors.thumbnail" class="text-red-600 text-xs mt-1">
                {{ Array.isArray(validationErrors.thumbnail) ? validationErrors.thumbnail[0] : validationErrors.thumbnail }}
              </p>
            </div>

            <div class="flex justify-end pt-1">
              <button
                type="submit"
                :disabled="savingCourse"
                class="bg-brand-accent text-brand-primary px-5 py-2 rounded-lg font-semibold hover:opacity-90 transition-opacity text-sm disabled:opacity-50 flex items-center gap-2"
              >
                <svg v-if="savingCourse" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                {{ savingCourse ? 'Guardando...' : 'Guardar cambios' }}
              </button>
            </div>
          </form>
        </section>

        <!-- Sections -->
        <section>
          <h2 class="text-base font-semibold text-brand-primary mb-4">Secciones del curso</h2>

          <div v-if="!course.sections?.length" class="text-sm text-gray-400 mb-4">
            Aún no hay secciones. Añade la primera a continuación.
          </div>

          <div class="space-y-4 mb-6">
            <SectionEditor
              v-for="(section, idx) in course.sections"
              :key="section.id"
              :section="section"
              :section-index="idx"
              :is-first="idx === 0"
              :is-last="idx === (course.sections.length - 1)"
              :course-slug="route.params.slug"
            />
          </div>

          <!-- Add section form -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <h3 class="text-sm font-medium text-gray-700 mb-3">Añadir sección</h3>
            <div class="flex gap-2">
              <input
                v-model="newSectionTitle"
                type="text"
                placeholder="Título de la sección"
                class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent"
                @keyup.enter="handleAddSection"
              />
              <button
                @click="handleAddSection"
                :disabled="addingSection || !newSectionTitle.trim()"
                class="bg-brand-accent text-brand-primary px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-2"
              >
                <svg v-if="addingSection" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Añadir
              </button>
            </div>
          </div>
        </section>
      </template>
    </div>
  </div>
</template>
