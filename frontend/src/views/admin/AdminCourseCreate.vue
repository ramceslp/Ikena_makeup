<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAdminCoursesStore } from '../../stores/adminCourses.js'
import AdminCourseForm from '../../components/admin/AdminCourseForm.vue'

const router = useRouter()
const store = useAdminCoursesStore()

const instructors = computed(() => store.instructors)
const categories = computed(() => store.categories)
const validationErrors = computed(() => store.validationErrors)

const loading = ref(false)
const error = ref('')

async function handleSubmit(payload) {
  if (loading.value) return
  loading.value = true
  error.value = ''
  try {
    const course = await store.createCourse(payload)
    // Land on the edit view rather than the list: a freshly created course is
    // an empty draft, and the next thing the admin needs is its content.
    router.push(`/admin/courses/${course.id}/edit`)
  } catch (err) {
    if (err.response?.status !== 422) {
      error.value = err.response?.data?.message || 'Error al crear el curso'
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  // The store outlives the view: clear any 422s left over from a previous
  // form so a fresh create page never opens pre-marked with errors.
  store._clearErrors()
  store.fetchInstructors()
  store.fetchCategories()
})
</script>

<template>
  <div class="max-w-2xl mx-auto px-gutter py-12">
    <div class="mb-8">
      <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Nuevo Curso</h1>
      <p class="font-body-md text-body-md text-on-surface-variant mt-1">
        Se crea como borrador. Vas a poder cargar secciones y lecciones antes de publicarlo.
      </p>
    </div>

    <div
      v-if="error"
      data-create-error
      class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container"
    >
      {{ error }}
    </div>

    <div class="bg-surface rounded-2xl border border-blush-canvas/20 p-8">
      <AdminCourseForm
        :instructors="instructors"
        :categories="categories"
        :loading="loading"
        :validation-errors="validationErrors"
        submit-label="Crear curso"
        @submit="handleSubmit"
        @cancel="router.push('/admin/courses')"
      />
    </div>
  </div>
</template>
