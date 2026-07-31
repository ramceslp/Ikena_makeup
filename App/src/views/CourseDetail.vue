<script setup>
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCoursesStore } from '../stores/courses.js'
import { formatPrice } from '../utils/formatPrice.js'
import CourseCurriculum from '../components/course/CourseCurriculum.vue'
import BaseBadge from '../components/ui/BaseBadge.vue'
import BaseButton from '../components/ui/BaseButton.vue'

// Course detail keyed by :slug, with an enrollment CTA.
//
// This view shipped read-only because no enroll path existed in the app at
// all: free enrollment was never ported, and paid enrollment had no server
// support (the checkout handoff only understood product_cart and appointment,
// because CheckoutController::checkout kept its logic inline instead of in a
// reusable Action). Both gaps are closed now — CourseCheckoutAction backs a
// `course` handoff type — so the CTA below is finally wired to something real.
//
// The app still has no /learn lesson player. An enrolled user is pointed at
// the profile's "Mis cursos" section, which opens the web player in the
// system browser.
const route = useRoute()
const router = useRouter()
const coursesStore = useCoursesStore()

const course = computed(() => coursesStore.currentCourse)
const loading = computed(() => coursesStore.loading)
const error = computed(() => coursesStore.error)

const isEnrolled = computed(() => !!course.value?.is_enrolled)
const isFree = computed(() => {
  const price = parseFloat(course.value?.price)
  return !Number.isFinite(price) || price <= 0
})

// Scoped to THIS course, not a global "a handoff happened" flag — otherwise
// handing off course A leaves course B showing A's browser panel with no way
// to enroll (see stores/courses.js's enrollHandoffCourseId comment).
const handedOff = computed(
  () => course.value != null && coursesStore.enrollHandoffCourseId === course.value.id
)

const ctaLabel = computed(() => {
  if (coursesStore.enrolling) return isFree.value ? 'Inscribiendo…' : 'Abriendo el pago…'
  return isFree.value ? 'Inscribirme gratis' : 'Inscribirme'
})

async function handleEnroll() {
  const outcome = await coursesStore.enroll(course.value)

  // A free enrollment is instantly usable, so take the user straight to where
  // they can actually start it. A paid one is not enrolled yet — payment is
  // still open in the browser — so the view stays put and shows the
  // handed-off panel instead.
  if (outcome === 'enrolled') {
    await router.push('/profile')
  }
}

function retryEnroll() {
  coursesStore.enrollHandoffCourseId = null
  coursesStore.enrollError = null
}

// Fetches only on initial mount (same inherited limitation as ServiceDetail.vue/
// ProductDetail.vue) -- a detail->detail navigation reusing this route
// component would not re-fetch. No such navigation exists in this view.
onMounted(async () => {
  try {
    await coursesStore.fetchCourse(route.params.slug)
  } catch {
    // error state is set on the store
  }
})
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter section-y-sm">
    <!-- Loading -->
    <div v-if="loading" data-loading class="state-y flex items-center justify-center">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <!-- Error / 404 -->
    <div v-else-if="error" class="state-y text-center">
      <span class="material-symbols-outlined text-5xl text-error mb-4" aria-hidden="true">error</span>
      <p class="font-body-lg text-body-lg text-on-surface mb-4">{{ error }}</p>
      <RouterLink to="/cursos">
        <BaseButton variant="outline">Volver al catálogo</BaseButton>
      </RouterLink>
    </div>

    <!-- Course Detail -->
    <div v-else-if="course" class="flex flex-col gap-8">
      <!-- Back link -->
      <RouterLink
        to="/cursos"
        data-back-to-catalog
        class="font-label-md text-label-md text-on-surface-variant hover:text-primary flex items-center gap-1 w-fit"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
        Volver al catálogo
      </RouterLink>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        <!-- Thumbnail -->
        <div class="relative aspect-video rounded-2xl overflow-hidden bg-surface-container">
          <img
            v-if="course.thumbnail"
            :src="course.thumbnail"
            :alt="course.title"
            class="w-full h-full object-cover"
          />
          <div
            v-else
            class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blush-canvas/30 to-primary/20"
          >
            <span class="material-symbols-outlined text-[64px] text-primary/40" aria-hidden="true">school</span>
          </div>
        </div>

        <!-- Info -->
        <div class="flex flex-col gap-6">
          <!-- Badges -->
          <div class="flex flex-wrap gap-2">
            <BaseBadge v-if="course.category" variant="secondary">
              {{ course.category.name }}
            </BaseBadge>
            <BaseBadge v-if="course.is_bestseller" variant="accent">
              Más vendido
            </BaseBadge>
            <BaseBadge v-if="course.offers_certificate" variant="blush">
              Con certificado
            </BaseBadge>
            <BaseBadge v-if="course.is_enrolled" variant="primary">
              Ya estás inscrito
            </BaseBadge>
          </div>

          <!-- Title -->
          <h1 class="font-headline-lg text-headline-lg text-deep-marsala">
            {{ course.title }}
          </h1>

          <!-- Instructor + lessons + rating row -->
          <div class="flex flex-wrap items-center gap-5 font-label-md text-label-md text-on-surface-variant">
            <span class="flex items-center gap-1.5">
              <span class="material-symbols-outlined text-[18px]" aria-hidden="true">person</span>
              Por {{ course.instructor?.name }}
            </span>
            <span class="flex items-center gap-1.5">
              <span class="material-symbols-outlined text-[18px]" aria-hidden="true">menu_book</span>
              {{ course.total_lessons }} lecciones
            </span>
            <span v-if="course.average_rating" class="flex items-center gap-1.5">
              <span
                class="material-symbols-outlined text-[18px]"
                aria-hidden="true"
                style="font-variation-settings: 'FILL' 1;"
              >star</span>
              {{ course.average_rating.toFixed(1) }} ({{ course.reviews_count }})
            </span>
          </div>

          <!-- Price -->
          <span class="font-display-lg text-display-lg text-primary">
            {{ formatPrice(course.price) }}
          </span>

          <!-- Description -->
          <div class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
            {{ course.description }}
          </div>

          <!-- ── Enrollment CTA ──────────────────────────────────────────── -->

          <!-- Already enrolled: no purchase action, just the way in. The app
               has no lesson player, so the route is the profile's "Mis cursos"
               section, which opens the web player in the system browser. -->
          <div v-if="isEnrolled" data-course-enrolled class="flex flex-col gap-3">
            <RouterLink to="/profile" data-go-to-my-courses>
              <BaseButton variant="primary" size="lg" class="w-full">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">play_circle</span>
                Ir a mis cursos
              </BaseButton>
            </RouterLink>
          </div>

          <!-- Paid checkout opened in the browser. Worded as "not yet
               enrolled", because the Enrollment is created only after the
               gateway approves. -->
          <div
            v-else-if="handedOff"
            data-course-handed-off
            class="flex flex-col items-center gap-3 text-center bg-surface-container-low rounded-2xl p-6"
          >
            <span class="material-symbols-outlined text-4xl text-primary" aria-hidden="true">open_in_new</span>
            <p class="font-body-md text-body-md text-on-surface-variant">
              Abrimos el pago en tu navegador. Quedarás inscrito en cuanto completes el pago;
              el enlace vence en 10 minutos.
            </p>
            <button
              type="button"
              data-enroll-retry
              class="font-label-md text-label-md text-primary underline min-h-11 px-4"
              @click="retryEnroll"
            >
              Volver a intentar
            </button>
          </div>

          <div v-else class="flex flex-col gap-3">
            <BaseButton
              data-enroll-btn
              variant="primary"
              size="lg"
              class="w-full"
              :loading="coursesStore.enrolling"
              @click="handleEnroll"
            >
              <span class="material-symbols-outlined text-[20px]" aria-hidden="true">
                {{ isFree ? 'school' : 'open_in_new' }}
              </span>
              {{ ctaLabel }}
            </BaseButton>

            <p
              v-if="coursesStore.enrollError"
              data-enroll-error
              class="bg-error-container rounded-xl px-4 py-3 font-body-md text-body-md text-on-error-container"
              role="alert"
            >
              {{ coursesStore.enrollError }}
            </p>

            <p v-if="!isFree" class="font-body-sm text-body-sm text-on-surface-variant flex items-start gap-1.5">
              <span class="material-symbols-outlined text-[16px] mt-0.5" aria-hidden="true">open_in_new</span>
              El pago se completa de forma segura en tu navegador.
            </p>
          </div>
        </div>
      </div>

      <!-- Curriculum -->
      <div>
        <h2 class="font-headline-lg text-headline-lg text-deep-marsala section-header">
          Contenido del curso
        </h2>
        <CourseCurriculum :sections="course.sections ?? []" />
      </div>
    </div>
  </div>
</template>
