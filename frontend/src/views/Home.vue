<script setup>
import { ref, watch, onMounted } from 'vue'
import { useCoursesStore } from '../stores/courses.js'
import HeroSection from '../components/home/HeroSection.vue'
import CourseFilters from '../components/home/CourseFilters.vue'
import CourseCatalog from '../components/home/CourseCatalog.vue'
import NewsletterCta from '../components/home/NewsletterCta.vue'

const coursesStore = useCoursesStore()

const search = ref('')
const minPrice = ref('')
const maxPrice = ref('')
const sort = ref('newest')

const catalogRef = ref(null)

let debounceTimer = null

function buildFilters() {
  return {
    search: search.value,
    min_price: minPrice.value,
    max_price: maxPrice.value,
    sort: sort.value,
    page: 1,
  }
}

function applyFilters() {
  coursesStore.fetchCourses(buildFilters())
}

// Search is debounced; price/sort apply immediately (original behavior preserved)
watch(search, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 400)
})

watch([minPrice, maxPrice, sort], applyFilters)

function goToPage(page) {
  coursesStore.fetchCourses({ ...buildFilters(), page })
}

function scrollToCatalog() {
  catalogRef.value?.scrollIntoView({ behavior: 'smooth' })
}

onMounted(() => {
  coursesStore.fetchCourses()
})
</script>

<template>
  <div>
    <HeroSection @explore="scrollToCatalog" />

    <CourseFilters
      v-model:search="search"
      v-model:min-price="minPrice"
      v-model:max-price="maxPrice"
      v-model:sort="sort"
    />

    <div ref="catalogRef">
      <CourseCatalog
        :courses="coursesStore.courses"
        :loading="coursesStore.loading"
        :error="coursesStore.error"
        :meta="coursesStore.meta"
        @retry="applyFilters"
        @page-change="goToPage"
      />
    </div>

    <NewsletterCta />
  </div>
</template>
