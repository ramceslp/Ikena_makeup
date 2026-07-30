<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useCoursesStore } from '../stores/courses.js'
import api from '../services/api.js'
import CourseFilters from '../components/course/CourseFilters.vue'
import CourseCatalog from '../components/course/CourseCatalog.vue'

// Courses catalog view. Mirrors views/Services.vue's composition (connectivity
// probe + page header + filters + catalog) -- see that file's header comment
// for why the probe exists and is duplicated per-view rather than shared.
// Uses the vertical-rhythm classes (.section-y-sm / .state-y) instead of
// hand-written py-* utilities -- see style.css's documented spacing contract.
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

// Search is debounced; other filters apply immediately
watch(search, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 400)
})

watch([minPrice, maxPrice, sort, category], applyFilters)

function goToPage(page) {
  coursesStore.fetchCourses({ ...buildFilters(), page })
}

const connectivityState = ref('checking') // 'checking' | 'ok' | 'error'
const isCheckingConnectivity = ref(false)

async function checkConnectivity() {
  if (isCheckingConnectivity.value) return
  isCheckingConnectivity.value = true
  try {
    await api.get('/courses', { params: { per_page: 1 } })
    connectivityState.value = 'ok'
  } catch (err) {
    connectivityState.value = err.response ? 'ok' : 'error'
  } finally {
    isCheckingConnectivity.value = false
  }
}

async function init() {
  await checkConnectivity()
  if (connectivityState.value === 'ok') {
    coursesStore.fetchCategories()
    coursesStore.fetchCourses()
  }
}

onMounted(init)

onBeforeUnmount(() => {
  clearTimeout(debounceTimer)
})
</script>

<template>
  <div>
    <div
      v-if="connectivityState === 'error'"
      data-catalog-error
      class="state-y flex flex-col items-center gap-4 px-6 text-center"
    >
      <p class="font-body-lg text-body-lg text-on-surface-variant">
        No pudimos cargar el catálogo. Verifica tu conexión e intenta de nuevo.
      </p>
      <button
        type="button"
        data-catalog-retry
        :disabled="isCheckingConnectivity"
        class="btn-gloss px-6 py-3 rounded-xl bg-apricot-glow text-deep-marsala font-bold disabled:opacity-60 disabled:cursor-not-allowed"
        @click="init"
      >
        Reintentar
      </button>
    </div>

    <div
      v-else-if="connectivityState === 'checking'"
      data-catalog-checking
      class="state-y flex flex-col items-center gap-4 px-6 text-center"
    >
      <p class="font-body-lg text-body-lg text-on-surface-variant">
        Cargando...
      </p>
    </div>

    <template v-else-if="connectivityState === 'ok'">
      <!-- Page header -->
      <section class="section-y-sm bg-gradient-to-b from-blush-canvas/20 to-background">
        <div class="max-w-container-max mx-auto px-gutter text-center">
          <h1 class="font-headline-lg text-headline-lg text-deep-marsala mb-3">
            Cursos de Maquillaje
          </h1>
          <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto truncate">
            Aprende con instructores expertos, a tu ritmo.
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

      <CourseCatalog
        :courses="coursesStore.courses"
        :loading="coursesStore.loading"
        :error="coursesStore.error"
        :meta="coursesStore.meta"
        @retry="applyFilters"
        @page-change="goToPage"
      />
    </template>
  </div>
</template>
