<script setup>
import { onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useInstructorStore } from '../stores/instructor.js'

const instructorStore = useInstructorStore()

const courses = computed(() => instructorStore.myCourses)
const loading = computed(() => instructorStore.loading)
const error = computed(() => instructorStore.error)

onMounted(() => {
  instructorStore.fetchMyCourses()
})

async function handlePublishToggle(course) {
  if (course.is_published) {
    await instructorStore.unpublish(course.slug)
  } else {
    await instructorStore.publish(course.slug)
  }
}

async function handleDelete(course) {
  if (!window.confirm(`¿Eliminar el curso "${course.title}"? Esta acción no se puede deshacer.`)) return
  await instructorStore.deleteCourse(course.slug)
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-5xl mx-auto px-4 py-10">
      <!-- Header -->
      <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-brand-primary">Mis cursos</h1>
        <RouterLink
          to="/instructor/courses/new"
          class="bg-brand-accent text-brand-primary px-5 py-2 rounded-lg font-semibold hover:opacity-90 transition-opacity text-sm"
        >
          + Nuevo curso
        </RouterLink>
      </div>

      <!-- Loading -->
      <div v-if="loading && courses.length === 0" class="flex justify-center py-20">
        <svg class="animate-spin w-8 h-8 text-brand-accent" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700 flex items-start gap-2">
        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p>{{ error }}</p>
      </div>

      <!-- Empty state -->
      <div v-else-if="!loading && courses.length === 0" class="text-center py-20 text-gray-500">
        <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
        <p class="font-medium">Aún no tienes cursos creados.</p>
        <p class="text-sm mt-1">Haz clic en "Nuevo curso" para comenzar.</p>
      </div>

      <!-- Course cards -->
      <ul v-else class="space-y-4">
        <li
          v-for="course in courses"
          :key="course.id"
          class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex gap-4 items-start"
        >
          <!-- Thumbnail -->
          <img
            v-if="course.thumbnail"
            :src="course.thumbnail"
            :alt="course.title"
            class="w-24 h-16 object-cover rounded-lg shrink-0"
          />
          <div
            v-else
            class="w-24 h-16 bg-brand-track rounded-lg shrink-0 flex items-center justify-center"
            aria-hidden="true"
          >
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z" />
            </svg>
          </div>

          <!-- Info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <h2 class="text-base font-semibold text-brand-primary truncate">{{ course.title }}</h2>
              <!-- Status badge: icon + label (never color-only) -->
              <span
                v-if="course.is_published"
                class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full"
              >
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                Publicado
              </span>
              <span
                v-else
                class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 bg-gray-100 border border-gray-200 px-2 py-0.5 rounded-full"
              >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                </svg>
                Borrador
              </span>
            </div>

            <p class="text-xs text-gray-400">
              {{ course.sections_count }} secciones · {{ course.lessons_count }} lecciones · {{ course.students_count }} estudiantes
            </p>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2 shrink-0 flex-wrap">
            <RouterLink
              :to="`/instructor/courses/${course.slug}/edit`"
              class="text-sm text-brand-primary border border-brand-primary px-3 py-1.5 rounded-lg hover:bg-brand-primary hover:text-white transition-colors font-medium"
            >
              Editar
            </RouterLink>

            <button
              @click="handlePublishToggle(course)"
              :disabled="loading"
              class="text-sm px-3 py-1.5 rounded-lg font-medium transition-colors disabled:opacity-50"
              :class="course.is_published
                ? 'text-gray-600 border border-gray-300 hover:bg-gray-50'
                : 'bg-brand-accent text-brand-primary hover:opacity-90'"
            >
              {{ course.is_published ? 'Despublicar' : 'Publicar' }}
            </button>

            <button
              @click="handleDelete(course)"
              :disabled="loading"
              class="text-sm text-red-600 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors font-medium disabled:opacity-50"
            >
              Eliminar
            </button>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
