<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAdminCoursesStore } from '../../stores/adminCourses.js'
import BaseButton from '../../components/ui/BaseButton.vue'

const router = useRouter()
const store = useAdminCoursesStore()

const courses = computed(() => store.courses)
const instructors = computed(() => store.instructors)
const loading = computed(() => store.loading)
const error = computed(() => store.error)

const filters = ref({
  search: '',
  instructor_id: '',
  is_published: '',
})

const busyId = ref(null)
const actionError = ref('')

async function loadCourses() {
  await store.fetchCourses(filters.value)
}

function resetFilters() {
  filters.value = { search: '', instructor_id: '', is_published: '' }
  loadCourses()
}

/**
 * Publish is the one action that can be legitimately refused (a course with no
 * lessons), so its 422 message is surfaced verbatim instead of a generic error.
 */
async function togglePublish(course) {
  busyId.value = course.id
  actionError.value = ''
  try {
    if (course.is_published) {
      await store.unpublish(course.id)
    } else {
      await store.publish(course.id)
    }
  } catch (err) {
    actionError.value =
      err.response?.data?.message || 'No se pudo cambiar el estado del curso'
  } finally {
    busyId.value = null
  }
}

async function handleDelete(course) {
  if (
    !window.confirm(
      `¿Eliminar "${course.title}"? Se borrarán sus secciones y lecciones. Esta acción no se puede deshacer.`
    )
  ) {
    return
  }
  busyId.value = course.id
  actionError.value = ''
  try {
    await store.deleteCourse(course.id)
  } catch (err) {
    actionError.value = err.response?.data?.message || 'Error al eliminar el curso'
  } finally {
    busyId.value = null
  }
}

onMounted(() => {
  store.fetchInstructors()
  loadCourses()
})
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter py-12">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Gestión de Cursos</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-1">
          Todo el catálogo de la academia, de todos los instructores (publicados y borradores).
        </p>
      </div>
      <BaseButton data-new-course-btn variant="primary" @click="router.push('/admin/courses/new')">
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
        Nuevo curso
      </BaseButton>
    </div>

    <!-- Filters -->
    <form
      data-filters
      class="mb-6 grid grid-cols-1 sm:grid-cols-4 gap-3"
      @submit.prevent="loadCourses"
    >
      <input
        v-model="filters.search"
        data-filter-search
        type="search"
        placeholder="Buscar por título..."
        aria-label="Buscar cursos por título"
        class="sm:col-span-2 w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
      />
      <select
        v-model="filters.instructor_id"
        data-filter-instructor
        aria-label="Filtrar por instructor"
        class="w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
        @change="loadCourses"
      >
        <option value="">Todos los instructores</option>
        <option v-for="i in instructors" :key="i.id" :value="i.id">{{ i.name }}</option>
      </select>
      <select
        v-model="filters.is_published"
        data-filter-status
        aria-label="Filtrar por estado"
        class="w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
        @change="loadCourses"
      >
        <option value="">Todos los estados</option>
        <option value="1">Publicados</option>
        <option value="0">Borradores</option>
      </select>
    </form>

    <!-- Errors -->
    <div
      v-if="error"
      class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container"
    >
      {{ error }}
    </div>
    <div
      v-if="actionError"
      data-action-error
      class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container"
    >
      {{ actionError }}
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">
        refresh
      </span>
    </div>

    <!-- Empty state -->
    <div v-else-if="!courses.length" data-empty-state class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-blush-canvas mb-4" aria-hidden="true">
        school
      </span>
      <p class="font-body-lg text-body-lg text-on-surface-variant">
        No hay cursos que coincidan con la búsqueda
      </p>
      <BaseButton variant="outline" size="sm" class="mt-6" @click="resetFilters">
        Limpiar filtros
      </BaseButton>
    </div>

    <!-- Courses table -->
    <div v-else class="bg-surface rounded-2xl border border-blush-canvas/20 overflow-x-auto">
      <table class="w-full">
        <thead class="border-b border-blush-canvas/20 bg-surface-container-low">
          <tr>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">
              Curso
            </th>
            <th
              class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant hidden md:table-cell"
            >
              Instructor
            </th>
            <th
              class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant hidden lg:table-cell"
            >
              Contenido
            </th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">
              Estado
            </th>
            <th class="text-right px-6 py-4 font-label-md text-label-md text-on-surface-variant">
              Acciones
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-blush-canvas/10">
          <tr
            v-for="course in courses"
            :key="course.id"
            data-course-row
            class="hover:bg-surface-container-low transition-colors"
          >
            <!-- Title -->
            <td class="px-6 py-4">
              <span class="font-body-md text-body-md text-on-surface line-clamp-1">
                {{ course.title }}
              </span>
              <span class="block font-body-sm text-body-sm text-on-surface-variant md:hidden">
                {{ course.instructor?.name ?? 'Sin instructor' }}
              </span>
            </td>

            <!-- Instructor -->
            <td class="px-6 py-4 hidden md:table-cell">
              <span data-instructor-name class="font-body-sm text-body-sm text-on-surface-variant">
                {{ course.instructor?.name ?? '—' }}
              </span>
            </td>

            <!-- Content counts -->
            <td class="px-6 py-4 hidden lg:table-cell">
              <span class="font-body-sm text-body-sm text-on-surface-variant">
                {{ course.sections_count }} secc. · {{ course.lessons_count }} lecc. ·
                {{ course.students_count }} alum.
              </span>
            </td>

            <!-- Published state -->
            <td class="px-6 py-4">
              <span
                data-status-badge
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                :class="
                  course.is_published ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
                "
              >
                {{ course.is_published ? 'Publicado' : 'Borrador' }}
              </span>
            </td>

            <!-- Actions -->
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <button
                  type="button"
                  data-publish-btn
                  :disabled="busyId === course.id"
                  :aria-label="course.is_published ? 'Despublicar curso' : 'Publicar curso'"
                  class="p-2 rounded-lg hover:bg-surface-container transition-colors text-on-surface-variant hover:text-primary disabled:opacity-50"
                  @click="togglePublish(course)"
                >
                  <span class="material-symbols-outlined text-[18px]" aria-hidden="true">
                    {{ course.is_published ? 'visibility_off' : 'visibility' }}
                  </span>
                </button>

                <!-- Deep authoring lives in the instructor editor; admins are
                     allowed in by CoursePolicy::manage, so there is no second
                     copy of the sections/lessons UI to maintain. -->
                <button
                  type="button"
                  data-content-btn
                  aria-label="Editar contenido del curso"
                  class="p-2 rounded-lg hover:bg-surface-container transition-colors text-on-surface-variant hover:text-primary"
                  @click="router.push(`/instructor/courses/${course.slug}/edit`)"
                >
                  <span class="material-symbols-outlined text-[18px]" aria-hidden="true">
                    view_list
                  </span>
                </button>

                <button
                  type="button"
                  data-edit-btn
                  aria-label="Editar datos del curso"
                  class="p-2 rounded-lg hover:bg-surface-container transition-colors text-on-surface-variant hover:text-primary"
                  @click="router.push(`/admin/courses/${course.id}/edit`)"
                >
                  <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                </button>

                <button
                  type="button"
                  data-delete-btn
                  :disabled="busyId === course.id"
                  aria-label="Eliminar curso"
                  class="p-2 rounded-lg hover:bg-error-container transition-colors text-on-surface-variant hover:text-error disabled:opacity-50"
                  @click="handleDelete(course)"
                >
                  <span class="material-symbols-outlined text-[18px]" aria-hidden="true">
                    delete
                  </span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
