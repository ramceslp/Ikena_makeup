<script setup>
// Container: owns route params, store wiring, lesson navigation, and mark-complete handler.
// Presentational work is delegated to components/player/*.
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useCoursesStore } from '../stores/courses.js'
import { resolveVideo } from '../utils/video.js'

import VideoStage from '../components/player/VideoStage.vue'
import PlaylistSidebar from '../components/player/PlaylistSidebar.vue'
import LessonTabs from '../components/player/LessonTabs.vue'

const route = useRoute()
const coursesStore = useCoursesStore()

// ── Local UI state ────────────────────────────────────────────────────────────
const lessonLoading = ref(false)
const togglingLesson = ref(null)

// ── Derived ───────────────────────────────────────────────────────────────────
const course = computed(() => coursesStore.currentCourse)
const currentLesson = computed(() => coursesStore.currentLesson)

// Resolve video once here so VideoStage receives the descriptor, not the raw URL
const resolvedVideo = computed(() => resolveVideo(currentLesson.value?.video_url))

// Compute course progress from enrolled lesson data (completed / total)
const progressValue = computed(() => {
  if (!course.value?.sections) return 0
  let total = 0
  let completed = 0
  for (const section of course.value.sections) {
    for (const lesson of section.lessons ?? []) {
      total++
      if (lesson.completed) completed++
    }
  }
  return total > 0 ? Math.round((completed / total) * 100) : 0
})

// ── Lesson navigation ─────────────────────────────────────────────────────────
// A lesson is accessible when it's free or the user is enrolled.
function isAccessible(lesson) {
  return !!lesson && (lesson.is_free || course.value?.is_enrolled)
}

async function selectLesson(lesson) {
  if (currentLesson.value?.id === lesson.id) return
  // Skip locked lessons (backend returns 403); the sidebar already blocks the click.
  if (!isAccessible(lesson)) return
  lessonLoading.value = true
  try {
    await coursesStore.fetchLesson(lesson.id)
  } finally {
    lessonLoading.value = false
  }
}

// ── Practice submission ───────────────────────────────────────────────────────
async function handlePracticeSubmit({ before, after }) {
  try {
    await coursesStore.submitPractice(currentLesson.value.id, { before, after })
  } catch {
    // Error is stored in coursesStore.submissionError — displayed by PracticeSubmission via :error prop
  }
}

// ── Mark complete toggle ──────────────────────────────────────────────────────
async function handleToggleComplete(lesson) {
  if (togglingLesson.value === lesson.id) return
  togglingLesson.value = lesson.id
  try {
    await coursesStore.toggleComplete(lesson.id)
  } catch {
    // Error handled by store rollback
  } finally {
    togglingLesson.value = null
  }
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
onMounted(async () => {
  const slug = route.params.slug
  await coursesStore.fetchCourse(slug)

  // Auto-load the first ACCESSIBLE lesson (free, or any lesson if enrolled).
  // Avoids triggering a 403 on a locked first lesson for non-enrolled users.
  const firstLesson = course.value?.sections
    ?.flatMap((s) => s.lessons ?? [])
    .find(isAccessible)

  if (firstLesson) {
    lessonLoading.value = true
    try {
      await coursesStore.fetchLesson(firstLesson.id)
    } finally {
      lessonLoading.value = false
    }
  }
})
</script>

<template>
  <!-- Full-screen player shell -->
  <div class="min-h-screen bg-surface">

    <!-- Loading: initial course fetch -->
    <div
      v-if="coursesStore.loading && !course"
      class="flex items-center justify-center min-h-screen bg-deep-marsala"
      aria-label="Cargando curso..."
      aria-busy="true"
    >
      <div class="text-center">
        <svg class="animate-spin w-10 h-10 mx-auto mb-4 text-apricot-glow" fill="none" viewBox="0 0 24 24" aria-hidden="true">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <p class="font-body-md text-body-md text-blush-canvas">Cargando curso...</p>
      </div>
    </div>

    <!-- Error state -->
    <div
      v-else-if="coursesStore.error && !course"
      class="flex items-center justify-center min-h-screen text-center"
      role="alert"
    >
      <div>
        <span class="material-symbols-outlined text-[48px] text-error mx-auto block mb-4" aria-hidden="true">
          error_outline
        </span>
        <p class="font-body-md text-body-md text-on-surface-variant">{{ coursesStore.error }}</p>
      </div>
    </div>

    <!-- Empty course: no sections -->
    <div
      v-else-if="course && !course.sections?.length"
      class="flex items-center justify-center min-h-screen text-center bg-deep-marsala"
      role="status"
    >
      <div>
        <span class="material-symbols-outlined text-[48px] text-blush-canvas/50 block mx-auto mb-4" aria-hidden="true">
          video_library
        </span>
        <p class="font-body-md text-body-md text-blush-canvas/70">
          Este curso no tiene lecciones todavía.
        </p>
      </div>
    </div>

    <!-- Player layout: two-column (left: video + tabs | right: playlist) -->
    <div v-else-if="course" class="flex flex-col md:flex-row h-screen overflow-hidden">

      <!-- Left column: video stage + lesson tabs -->
      <div class="flex-1 flex flex-col min-h-0 overflow-y-auto">

        <!-- Video stage (takes remaining space above tabs) -->
        <VideoStage
          :resolved-video="resolvedVideo"
          :lesson="currentLesson"
          :loading="lessonLoading"
          :progress-value="progressValue"
        />

        <!-- Lesson tabs — only rendered when a lesson is loaded -->
        <div v-if="currentLesson && !lessonLoading" class="px-6 py-4">
          <LessonTabs
            :lesson="currentLesson"
            :submission="currentLesson?.my_submission"
            :submitting="coursesStore.submissionSubmitting"
            :error="coursesStore.submissionError || ''"
            @submit-practice="handlePracticeSubmit"
          />
        </div>
      </div>

      <!-- Right column: playlist sidebar -->
      <div class="w-full md:w-80 lg:w-96 flex-shrink-0 border-l border-white/10 overflow-hidden flex flex-col">
        <PlaylistSidebar
          :course="course"
          :active-lesson-id="currentLesson?.id"
          :toggling-lesson-id="togglingLesson"
          :progress-value="progressValue"
          class="h-full"
          @select-lesson="selectLesson"
          @toggle-complete="handleToggleComplete"
        />
      </div>

    </div>

  </div>
</template>
