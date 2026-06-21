<script setup>
import { ref, watch, onMounted } from 'vue'
import { useCoursesStore } from '../stores/courses.js'
import CourseFilters from '../components/home/CourseFilters.vue'
import CourseCatalog from '../components/home/CourseCatalog.vue'

const coursesStore = useCoursesStore()

const search = ref('')
const minPrice = ref('')
const maxPrice = ref('')
const sort = ref('newest')
const category = ref('')

let debounceTimer = null

function buildFilters() {
  return {
    search: search.value,
    min_price: minPrice.value,
    max_price: maxPrice.value,
    sort: sort.value,
    category: category.value,
    page: 1,
  }
}

function applyFilters() {
  coursesStore.fetchCourses(buildFilters())
}

// Search is debounced; price/sort/category apply immediately
watch(search, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 400)
})

watch([minPrice, maxPrice, sort, category], applyFilters)

function goToPage(page) {
  coursesStore.fetchCourses({ ...buildFilters(), page })
}

onMounted(() => {
  coursesStore.fetchCategories()
  coursesStore.fetchCourses()
})
</script>

<template>
  <div>
    <!-- Page header -->
    <section class="py-16 bg-gradient-to-b from-blush-canvas/20 to-background">
      <div class="max-w-container-max mx-auto px-gutter text-center">
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala mb-3">
          Catálogo de Cursos
        </h1>
        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto">
          Descubre todos nuestros cursos profesionales de maquillaje artístico.
        </p>
      </div>
    </section>

    <CourseFilters
      v-model:search="search"
      v-model:min-price="minPrice"
      v-model:max-price="maxPrice"
      v-model:sort="sort"
      v-model:category="category"
      :categories="coursesStore.categories"
    />

    <div>
      <CourseCatalog
        :courses="coursesStore.courses"
        :loading="coursesStore.loading"
        :error="coursesStore.error"
        :meta="coursesStore.meta"
        @retry="applyFilters"
        @page-change="goToPage"
      />
    </div>
  </div>
</template>
