<script setup>
// Presentational: displays the course playlist grouped by section.
// Active lesson is highlighted in apricot-glow. Emits select-lesson and toggle-complete
// so the container handles all store interactions.
const props = defineProps({
  // Full course object: { title, is_enrolled, sections: [{ id, title, lessons: [...] }] }
  course: { type: Object, default: null },
  // ID of the currently playing lesson
  activeLessonId: { type: [Number, String], default: null },
  // ID of the lesson currently being toggled (shows spinner)
  togglingLessonId: { type: [Number, String], default: null },
  // Course completion percentage (0–100) for the sidebar header
  progressValue: { type: Number, default: 0 },
})

const emit = defineEmits([
  // Emitted with the lesson object when a lesson row is clicked
  'select-lesson',
  // Emitted with the lesson object when the completion checkbox is clicked
  'toggle-complete',
])

// A lesson is locked when it isn't free and the user isn't enrolled.
// The backend enforces this with a 403; here we mirror it in the UI so locked
// rows aren't clickable (no silent failed fetch).
function isLocked(lesson) {
  return !lesson.is_free && !props.course?.is_enrolled
}

function formatDuration(seconds) {
  if (!seconds) return '0:00'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}:${s.toString().padStart(2, '0')}`
}
</script>

<template>
  <aside
    v-if="course"
    class="bg-deep-marsala flex flex-col overflow-hidden h-full"
    aria-label="Contenido del curso"
  >
    <!-- Sidebar header: course title + progress -->
    <div class="px-6 py-5 border-b border-white/10 shrink-0">
      <h2 class="font-headline-lg text-headline-lg-mobile text-white leading-tight">
        Contenido del Curso
      </h2>
      <p class="font-label-sm text-label-sm text-blush-canvas/70 mt-0.5 truncate">
        {{ course.title }}
      </p>

      <!-- Progress strip + label -->
      <div v-if="progressValue > 0" class="flex items-center gap-3 mt-3">
        <div
          class="flex-1 h-1.5 bg-white/10 rounded-full overflow-hidden"
          role="progressbar"
          :aria-valuenow="progressValue"
          aria-valuemin="0"
          aria-valuemax="100"
          :aria-label="`Progreso: ${progressValue}%`"
        >
          <div
            class="h-full bg-apricot-glow rounded-full transition-all duration-500"
            :style="{ width: `${progressValue}%` }"
          />
        </div>
        <span class="font-label-sm text-label-sm text-apricot-glow shrink-0 uppercase tracking-wider">
          {{ progressValue }}% completado
        </span>
      </div>
    </div>

    <!-- Scrollable lesson list -->
    <div class="flex-1 overflow-y-auto py-2 px-2">
      <div
        v-for="section in course.sections"
        :key="section.id"
        class="mb-4"
      >
        <!-- Section header label -->
        <p class="px-4 py-3 font-label-sm text-label-sm text-white/50 uppercase tracking-widest">
          {{ section.title }}
        </p>

        <!-- Lesson rows -->
        <div class="space-y-1">
          <div
            v-for="lesson in section.lessons"
            :key="lesson.id"
            class="flex items-center gap-3 px-3 py-3 rounded-xl transition-all group"
            :class="[
              isLocked(lesson)
                ? 'bg-white/5 border border-white/10 text-white/40 cursor-not-allowed'
                : (activeLessonId === lesson.id
                    ? 'bg-apricot-glow text-deep-marsala shadow-lg scale-[1.01] cursor-pointer'
                    : 'bg-white/5 border border-white/10 text-white hover:bg-white/10 cursor-pointer'),
            ]"
            @click="!isLocked(lesson) && emit('select-lesson', lesson)"
            role="button"
            :aria-disabled="isLocked(lesson) ? 'true' : undefined"
            :aria-current="activeLessonId === lesson.id ? 'true' : undefined"
          >
            <!-- Locked lesson: lock icon instead of completion toggle -->
            <span
              v-if="isLocked(lesson)"
              class="material-symbols-outlined shrink-0 text-[18px] text-white/40"
              aria-hidden="true"
            >
              lock
            </span>

            <!-- Completion toggle button -->
            <button
              v-else
              class="shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-apricot-glow"
              :class="activeLessonId === lesson.id
                ? (lesson.completed ? 'bg-deep-marsala border-deep-marsala' : 'border-deep-marsala/50 hover:border-deep-marsala')
                : (lesson.completed ? 'bg-apricot-glow border-apricot-glow' : 'border-blush-canvas/50 hover:border-apricot-glow')"
              :disabled="togglingLessonId === lesson.id"
              :aria-label="lesson.completed ? 'Marcar como pendiente' : 'Marcar como completada'"
              @click.stop="emit('toggle-complete', lesson)"
            >
              <!-- Completed: check icon (icon + color, not color-only) -->
              <svg
                v-if="lesson.completed && togglingLessonId !== lesson.id"
                class="w-3 h-3"
                :class="activeLessonId === lesson.id ? 'text-deep-marsala' : 'text-deep-marsala'"
                fill="currentColor"
                viewBox="0 0 20 20"
                aria-hidden="true"
              >
                <path
                  fill-rule="evenodd"
                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                  clip-rule="evenodd"
                />
              </svg>
              <!-- Toggling: spinner -->
              <svg
                v-else-if="togglingLessonId === lesson.id"
                class="animate-spin w-3 h-3"
                :class="activeLessonId === lesson.id ? 'text-deep-marsala' : 'text-blush-canvas'"
                fill="none"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </button>

            <!-- Lesson text block -->
            <div class="flex-1 min-w-0">
              <p
                class="font-body-md text-body-md truncate"
                :class="activeLessonId === lesson.id ? 'font-semibold' : ''"
              >
                {{ lesson.title }}
              </p>
              <div class="flex items-center gap-2 mt-0.5">
                <!-- Free badge -->
                <span
                  v-if="lesson.is_free"
                  class="font-label-sm text-[10px] px-1.5 py-0.5 rounded"
                  :class="activeLessonId === lesson.id
                    ? 'bg-deep-marsala/20 text-deep-marsala'
                    : 'bg-blush-canvas/20 text-blush-canvas'"
                >
                  Gratis
                </span>
                <!-- Locked hint -->
                <span
                  v-if="isLocked(lesson)"
                  class="font-label-sm text-[10px] px-1.5 py-0.5 rounded bg-white/10 text-white/50"
                >
                  Bloqueada
                </span>
                <!-- Duration -->
                <span
                  class="font-label-sm text-label-sm"
                  :class="activeLessonId === lesson.id ? 'text-deep-marsala/70 opacity-80' : 'text-white/50'"
                >
                  {{ formatDuration(lesson.duration) }}
                </span>
                <!-- "En reproducción" label for active -->
                <span
                  v-if="activeLessonId === lesson.id"
                  class="font-label-sm text-[10px] text-deep-marsala/70"
                >
                  · En reproducción
                </span>
              </div>
            </div>

            <!-- Play circle icon (hidden for locked lessons) -->
            <span
              v-if="!isLocked(lesson)"
              class="material-symbols-outlined text-[20px] shrink-0 transition-opacity"
              :class="activeLessonId === lesson.id
                ? 'opacity-100 text-deep-marsala'
                : 'opacity-0 group-hover:opacity-100 text-blush-canvas/70'"
              aria-hidden="true"
              style="font-variation-settings: 'FILL' 1;"
            >
              play_circle
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Instructor CTA strip at the bottom — only shows instructor name from real API data -->
    <div
      v-if="course.instructor?.name"
      class="px-6 py-4 bg-white/5 border-t border-white/10 shrink-0"
    >
      <div class="flex items-center gap-3">
        <span
          class="material-symbols-outlined text-blush-canvas text-[32px]"
          aria-hidden="true"
        >
          account_circle
        </span>
        <div>
          <p class="font-body-md text-body-md text-white">{{ course.instructor.name }}</p>
          <p class="font-label-sm text-label-sm text-white/50">Instructor</p>
        </div>
      </div>
    </div>
  </aside>
</template>
