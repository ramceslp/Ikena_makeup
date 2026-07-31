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
          <h2 class="trazo font-headline-lg text-headline-lg text-deep-marsala">
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

      <!-- Courses deck. Each card sticks a step below the previous one, so the
           reader meets one course at a time instead of comparing three at once.
           The trailing space is what gives the last card room to be read before
           the section ends. -->
      <div v-if="courses.length > 0" data-course-stack class="apiladas flex flex-col gap-5 pb-[22vh]">
        <router-link
          v-for="(course, i) in courses"
          :key="course.id"
          :to="`/courses/${course.slug}`"
          :style="{ '--stack-index': i }"
          data-course-card
          class="apilada group grid sm:grid-cols-[14rem_1fr] gap-5 p-5 bg-surface rounded-2xl overflow-clip border border-blush-canvas/30 shadow-lg shadow-primary/10 hover:shadow-xl hover:shadow-primary/20 hover:-translate-y-1 transition-all duration-300 no-underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface-muted"
        >
          <!-- Thumbnail -->
          <div class="aspect-video sm:aspect-auto sm:h-40 bg-blush-canvas/10 rounded-xl overflow-clip">
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
          <div class="flex flex-col justify-center gap-2">
            <span v-if="course.category" class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest">
              {{ course.category.name }}
            </span>
            <h3 class="font-headline-lg text-title-md md:text-headline-lg text-deep-marsala group-hover:text-primary transition-colors">
              {{ course.title }}
            </h3>
            <p class="font-title-md text-title-md text-primary">
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
