<script setup>
// Container: owns store wiring, route params, auth state, enroll/buy handlers.
// Presentational work is delegated to components/course/*.
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCoursesStore } from '../stores/courses.js'
import { useAuthStore } from '../stores/auth.js'

import CourseDetailHero from '../components/course/CourseDetailHero.vue'
import CurriculumAccordion from '../components/course/CurriculumAccordion.vue'
import StickyCtaSidebar from '../components/course/StickyCtaSidebar.vue'
import ReviewForm from '../components/course/ReviewForm.vue'
import ReviewList from '../components/course/ReviewList.vue'

const route = useRoute()
const router = useRouter()
const coursesStore = useCoursesStore()
const authStore = useAuthStore()

// ── Local UI state ──────────────────────────────────────────────────────────
const enrolling = ref(false)
const enrollError = ref('')
// Map of { [sectionId]: boolean } controlling accordion open state
const openSections = ref({})

// ── Derived ─────────────────────────────────────────────────────────────────
const course = computed(() => coursesStore.currentCourse)
const isAuthenticated = computed(() => authStore.isAuthenticated)

// Eligible to review: authenticated + enrolled + not the instructor + has at least one completed lesson
const isEligibleToReview = computed(() => {
  if (!isAuthenticated.value) return false
  const c = course.value
  if (!c) return false
  if (!c.is_enrolled) return false
  if (c.instructor?.id === authStore.user?.id) return false
  return c.sections?.some((s) => s.lessons?.some((l) => l.completed)) ?? false
})

// Show form if eligible OR if the user already has a review (so they can edit/delete)
const showReviewForm = computed(() => isEligibleToReview.value || !!course.value?.my_review)

// Show a hint when authenticated + not enrolled + no existing review
const showEnrollHint = computed(() => {
  if (!isAuthenticated.value) return false
  if (showReviewForm.value) return false
  const c = course.value
  return c && !c.is_enrolled
})

// ── Section accordion ────────────────────────────────────────────────────────
function toggleSection(sectionId) {
  openSections.value[sectionId] = !openSections.value[sectionId]
}

// ── Enroll: free course → direct enroll → redirect to player ────────────────
async function handleEnroll() {
  enrollError.value = ''
  enrolling.value = true
  try {
    await coursesStore.enroll(route.params.slug)
    router.push(`/learn/${route.params.slug}`)
  } catch (err) {
    enrollError.value = err.response?.data?.message || 'Error al inscribirte. Intenta de nuevo.'
  } finally {
    enrolling.value = false
  }
}

// ── Buy: paid course → navigate to checkout ──────────────────────────────────
function handleBuy() {
  router.push(`/checkout/${route.params.slug}`)
}

// ── Review handlers ──────────────────────────────────────────────────────────
async function handleReviewSubmit(payload) {
  try {
    await coursesStore.submitReview(route.params.slug, payload)
  } catch {
    // Error is stored in coursesStore.reviewError — ReviewForm displays it
  }
}

async function handleReviewDelete() {
  try {
    await coursesStore.deleteReview(route.params.slug)
  } catch {
    // Error is stored in coursesStore.reviewError
  }
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
onMounted(async () => {
  await coursesStore.fetchCourse(route.params.slug)
  // Open first section by default
  if (course.value?.sections?.length) {
    openSections.value[course.value.sections[0].id] = true
  }
  // Load reviews in parallel (no need to await sequentially)
  coursesStore.fetchReviews(route.params.slug)
})
</script>

<template>
  <div>
    <!-- Loading skeleton -->
    <div
      v-if="coursesStore.loading && !course"
      class="max-w-container-max mx-auto px-gutter py-12"
      aria-label="Cargando curso..."
      aria-busy="true"
    >
      <div class="animate-pulse space-y-6">
        <div class="h-8 bg-blush-canvas/30 rounded w-2/3" />
        <div class="h-4 bg-blush-canvas/20 rounded w-1/3" />
        <div class="aspect-video bg-blush-canvas/20 rounded-xl max-w-2xl" />
      </div>
    </div>

    <!-- Error state -->
    <div
      v-else-if="coursesStore.error && !course"
      class="max-w-container-max mx-auto px-gutter py-16 text-center"
      role="alert"
    >
      <span class="material-symbols-outlined text-[48px] text-error mx-auto block mb-4" aria-hidden="true">
        error_outline
      </span>
      <p class="font-body-lg text-body-lg text-on-surface-variant">{{ coursesStore.error }}</p>
    </div>

    <!-- Course content -->
    <div v-else-if="course">
      <!-- Hero band with CTA (full-width) -->
      <CourseDetailHero
        :course="course"
        :is-authenticated="isAuthenticated"
        :enrolling="enrolling"
        :enroll-error="enrollError"
        @enroll="handleEnroll"
        @buy="handleBuy"
      />

      <!-- Main content area: curriculum (left) + sticky sidebar (right) -->
      <div class="max-w-container-max mx-auto px-gutter py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 items-start">

          <!-- Curriculum (2/3 width on lg) -->
          <div class="lg:col-span-2">
            <!-- Course description block -->
            <div class="mb-12 space-y-4">
              <h2 class="font-headline-lg text-headline-lg text-deep-marsala">
                Sobre este curso
              </h2>
              <p class="font-body-lg text-body-lg text-on-surface-variant leading-relaxed">
                {{ course.description }}
              </p>

              <!-- Stats grid -->
              <div class="grid grid-cols-2 md:grid-cols-3 gap-6 pt-6 border-t border-blush-canvas/20">
                <div>
                  <p class="font-label-sm text-label-sm text-on-surface-variant uppercase mb-1">Lecciones</p>
                  <p class="font-title-md text-title-md text-deep-marsala">{{ course.total_lessons }} clases</p>
                </div>
                <div>
                  <p class="font-label-sm text-label-sm text-on-surface-variant uppercase mb-1">Instructor</p>
                  <p class="font-title-md text-title-md text-deep-marsala">{{ course.instructor?.name }}</p>
                </div>
                <div>
                  <p class="font-label-sm text-label-sm text-on-surface-variant uppercase mb-1">Acceso</p>
                  <p class="font-title-md text-title-md text-deep-marsala">De por vida</p>
                </div>
              </div>
            </div>

            <!-- Curriculum accordion -->
            <CurriculumAccordion
              :sections="course.sections || []"
              :open-sections="openSections"
              @toggle-section="toggleSection"
            />

            <!-- Reviews region ───────────────────────────────────────────── -->
            <div class="mt-12 space-y-6">
              <!-- Review form: eligible users or existing reviewers -->
              <ReviewForm
                v-if="showReviewForm"
                :my-review="course.my_review ?? null"
                :submitting="coursesStore.reviewSubmitting"
                :deleting="coursesStore.reviewDeleting"
                :error="coursesStore.reviewError ?? ''"
                @submit="handleReviewSubmit"
                @delete="handleReviewDelete"
              />

              <!-- Enrollment hint for authenticated non-enrolled users -->
              <p
                v-else-if="showEnrollHint"
                class="font-body-md text-body-md text-on-surface-variant bg-surface-container-low rounded-xl px-4 py-3 border border-blush-canvas/30"
              >
                Inscríbete y completa al menos una lección para dejar tu valoración.
              </p>

              <!-- Review list (always visible) -->
              <ReviewList
                :reviews="coursesStore.reviews"
                :average-rating="course.average_rating ?? null"
                :reviews-count="course.reviews_count ?? 0"
                :loading="coursesStore.reviewsLoading"
              />
            </div>
          </div>

          <!-- Sticky CTA sidebar (1/3 width on lg) -->
          <div class="lg:col-span-1">
            <StickyCtaSidebar
              :course="course"
              :is-authenticated="isAuthenticated"
              :enrolling="enrolling"
              :enroll-error="enrollError"
              @enroll="handleEnroll"
              @buy="handleBuy"
            />
          </div>

        </div>
      </div>
    </div>
  </div>
</template>
