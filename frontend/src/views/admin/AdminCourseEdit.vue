<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAdminCoursesStore } from '../../stores/adminCourses.js'
import AdminCourseForm from '../../components/admin/AdminCourseForm.vue'
import BaseButton from '../../components/ui/BaseButton.vue'

const route = useRoute()
const router = useRouter()
const store = useAdminCoursesStore()

const courseId = route.params.id

const course = computed(() => store.currentCourse)
const instructors = computed(() => store.instructors)
const categories = computed(() => store.categories)
const validationErrors = computed(() => store.validationErrors)
const fetching = computed(() => store.loading && !store.currentCourse)

const saving = ref(false)
const publishing = ref(false)
const error = ref('')
const notice = ref('')

async function handleSubmit(payload) {
  if (saving.value) return
  saving.value = true
  error.value = ''
  notice.value = ''
  try {
    await store.updateCourse(courseId, payload)
    notice.value = 'Cambios guardados'
  } catch (err) {
    if (err.response?.status !== 422) {
      error.value = err.response?.data?.message || 'Error al guardar el curso'
    }
  } finally {
    saving.value = false
  }
}

/**
 * The 422 here is a real product rule ("a course with no lessons cannot be
 * published"), not a bug — so its message is shown verbatim rather than
 * replaced by a generic failure string.
 */
async function togglePublish() {
  if (publishing.value || !course.value) return
  publishing.value = true
  error.value = ''
  notice.value = ''
  try {
    if (course.value.is_published) {
      await store.unpublish(courseId)
      notice.value = 'Curso despublicado'
    } else {
      await store.publish(courseId)
      notice.value = 'Curso publicado'
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'No se pudo cambiar el estado del curso'
  } finally {
    publishing.value = false
  }
}

onMounted(async () => {
  store.fetchInstructors()
  store.fetchCategories()
  try {
    await store.fetchCourse(courseId)
  } catch {
    error.value = 'No se pudo cargar el curso'
  }
})
</script>

<template>
  <div class="max-w-2xl mx-auto px-gutter py-12">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Editar Curso</h1>
      <p class="font-body-md text-body-md text-on-surface-variant mt-1">
        Datos del catálogo. El contenido (secciones y lecciones) se edita aparte.
      </p>
    </div>

    <!-- Messages -->
    <div
      v-if="error"
      data-edit-error
      class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container"
    >
      {{ error }}
    </div>
    <div
      v-if="notice"
      data-edit-notice
      class="mb-4 p-4 bg-surface-container-low rounded-xl font-body-md text-body-md text-on-surface"
    >
      {{ notice }}
    </div>

    <!-- Loading -->
    <div v-if="fetching" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">
        refresh
      </span>
    </div>

    <template v-else-if="course">
      <!-- Status + content hand-off -->
      <div
        class="mb-6 bg-surface rounded-2xl border border-blush-canvas/20 p-6 flex flex-wrap items-center justify-between gap-4"
      >
        <div>
          <span
            data-status-badge
            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
            :class="
              course.is_published ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
            "
          >
            {{ course.is_published ? 'Publicado' : 'Borrador' }}
          </span>
          <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">
            {{ course.sections_count }} secciones · {{ course.lessons_count }} lecciones ·
            {{ course.students_count }} estudiantes
          </p>
        </div>

        <div class="flex items-center gap-3">
          <!-- Deep authoring is delegated to the instructor editor; admins are
               admitted by CoursePolicy::manage, so no second content UI exists. -->
          <BaseButton
            data-content-btn
            variant="outline"
            size="sm"
            @click="router.push(`/instructor/courses/${course.slug}/edit`)"
          >
            Editar contenido
          </BaseButton>
          <BaseButton
            data-publish-btn
            variant="solid"
            size="sm"
            :disabled="publishing"
            @click="togglePublish"
          >
            {{ course.is_published ? 'Despublicar' : 'Publicar' }}
          </BaseButton>
        </div>
      </div>

      <!-- Metadata form -->
      <div class="bg-surface rounded-2xl border border-blush-canvas/20 p-8">
        <AdminCourseForm
          :course="course"
          :instructors="instructors"
          :categories="categories"
          :loading="saving"
          :validation-errors="validationErrors"
          submit-label="Guardar cambios"
          @submit="handleSubmit"
          @cancel="router.push('/admin/courses')"
        />
      </div>
    </template>
  </div>
</template>
