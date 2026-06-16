<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useCoursesStore } from '../stores/courses.js'
import EnrolledCourseRow from '../components/mycourses/EnrolledCourseRow.vue'

const router = useRouter()
const coursesStore = useCoursesStore()

const myCourses = computed(() => coursesStore.myCourses)

// Navigate to the player for the given course slug — same route as before.
function goToCourse(slug) {
  router.push(`/learn/${slug}`)
}

onMounted(() => {
  coursesStore.fetchMyCourses()
})
</script>

<template>
  <section class="py-16 bg-background min-h-screen">
    <div class="max-w-container-max mx-auto px-gutter">

      <!-- Page header -->
      <div class="mb-10">
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Mis Cursos</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-1">
          Tu progreso de aprendizaje
        </p>
      </div>

      <!-- Loading skeleton -->
      <div v-if="coursesStore.loading" class="space-y-4">
        <div
          v-for="i in 3"
          :key="i"
          class="bg-surface-muted rounded-2xl border border-blush-canvas/10 p-6 flex items-center gap-6 animate-pulse"
        >
          <div class="w-20 h-14 bg-surface-container rounded-lg flex-shrink-0" />
          <div class="flex-1 space-y-3">
            <div class="h-4 bg-surface-container rounded w-3/4" />
            <div class="h-3 bg-surface-container rounded w-1/2" />
            <div class="h-2 bg-surface-container rounded w-1/3" />
          </div>
        </div>
      </div>

      <!-- Error state -->
      <div v-else-if="coursesStore.error" class="text-center py-16">
        <span
          class="material-symbols-outlined text-error text-5xl mb-4"
          aria-hidden="true"
        >error</span>
        <p class="font-body-lg text-body-lg text-on-surface">{{ coursesStore.error }}</p>
        <button
          @click="coursesStore.fetchMyCourses()"
          class="mt-4 font-label-md text-label-md text-primary hover:underline"
        >
          Intentar de nuevo
        </button>
      </div>

      <!-- Empty state -->
      <div v-else-if="!myCourses.length" class="text-center py-20">
        <span
          class="material-symbols-outlined text-blush-canvas text-6xl mb-4"
          aria-hidden="true"
        >menu_book</span>
        <p class="font-body-lg text-body-lg text-on-surface font-medium mb-2">
          Aún no estás inscrito en ningún curso
        </p>
        <p class="font-body-md text-body-md text-outline mb-6">
          ¡Explora el catálogo y empieza a aprender!
        </p>
        <RouterLink
          to="/"
          class="inline-flex items-center gap-2 bg-apricot-glow text-deep-marsala px-6 py-3 rounded-xl font-title-md text-title-md shadow-lg shadow-apricot-glow/20 hover:-translate-y-0.5 transition-all"
        >
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">explore</span>
          Explorar cursos
        </RouterLink>
      </div>

      <!-- Enrolled courses list -->
      <div v-else class="space-y-4">
        <EnrolledCourseRow
          v-for="course in myCourses"
          :key="course.id"
          :course="course"
          @continue="goToCourse"
        />
      </div>

    </div>
  </section>
</template>
