<script setup>
import { ref, onMounted } from 'vue'
import { useCoursesStore } from '../../stores/courses.js'

const coursesStore = useCoursesStore()

const courses = ref([])

onMounted(async () => {
  // Fetch 3 most-recent courses using per_page limit
  await coursesStore.fetchCourses({ page: 1, per_page: 3, sort: 'newest' })
  courses.value = (coursesStore.courses ?? []).slice(0, 3)
})

function formatPrice(price) {
  const num = parseFloat(price)
  if (!num || num === 0) return 'Gratis'
  return `$${num.toFixed(2)}`
}
</script>

<template>
  <section data-featured-courses class="py-20 bg-surface-muted">
    <div class="max-w-container-max mx-auto px-gutter">
      <!-- Section header -->
      <div v-reveal class="flex items-end justify-between mb-10">
        <div>
          <p class="font-label-sm text-label-sm text-primary uppercase tracking-widest mb-2">
            Formación Artística
          </p>
          <h2 class="font-headline-lg text-headline-lg text-deep-marsala">
            Cursos Destacados
          </h2>
        </div>
        <router-link
          to="/cursos"
          class="font-label-lg text-label-lg text-primary hover:text-deep-marsala transition-colors flex items-center gap-1"
        >
          Ver todos
          <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
        </router-link>
      </div>

      <!-- Courses grid -->
      <div v-if="courses.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <router-link
          v-for="(course, i) in courses"
          :key="course.id"
          v-reveal="i"
          :to="`/courses/${course.slug}`"
          data-course-card
          class="group flex flex-col bg-surface rounded-2xl overflow-hidden border border-blush-canvas/30 shadow-md shadow-primary/5 hover:shadow-xl hover:shadow-primary/10 hover:-translate-y-1 transition-all duration-300 no-underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface-muted"
        >
          <!-- Thumbnail -->
          <div class="aspect-video bg-blush-canvas/10 overflow-hidden">
            <img
              v-if="course.thumbnail"
              :src="course.thumbnail"
              :alt="course.title"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blush-canvas/30 to-primary/20">
              <span class="material-symbols-outlined text-4xl text-primary/40" aria-hidden="true">school</span>
            </div>
          </div>

          <!-- Card body -->
          <div class="flex flex-col flex-grow p-5 space-y-2">
            <span v-if="course.category" class="font-label-sm text-label-sm text-on-surface-variant">
              {{ course.category.name }}
            </span>
            <h3 class="font-title-md text-title-md text-deep-marsala group-hover:text-primary transition-colors line-clamp-2">
              {{ course.title }}
            </h3>
            <p class="font-title-md text-title-md text-primary mt-auto pt-2">
              {{ formatPrice(course.price) }}
            </p>
          </div>
        </router-link>
      </div>

      <!-- Empty state -->
      <div v-else class="text-center py-12">
        <p class="font-body-lg text-body-lg text-on-surface-variant">
          Próximamente nuevos cursos disponibles.
        </p>
      </div>
    </div>
  </section>
</template>
