<script setup>
import ProgressBar from '../ui/ProgressBar.vue'
import BaseButton from '../ui/BaseButton.vue'

// Presentational component — receives a single enrolled course object.
// Fields sourced strictly from MyCourseResource:
//   id, title, slug, thumbnail, instructor.name,
//   total_lessons, completed_lessons, progress_percentage
const props = defineProps({
  course: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['continue'])

function handleContinue() {
  emit('continue', props.course.slug)
}
</script>

<template>
  <div
    class="bg-surface-muted p-6 rounded-2xl border border-blush-canvas/10 flex flex-col md:flex-row md:items-center justify-between gap-6 hover:border-blush-canvas/40 transition-colors"
  >
    <!-- Left: thumbnail + info -->
    <div class="flex items-center gap-6">
      <!-- Thumbnail -->
      <div class="w-20 h-14 bg-surface-dim rounded-lg overflow-hidden flex-shrink-0">
        <img
          v-if="course.thumbnail"
          :src="course.thumbnail"
          :alt="course.title"
          class="w-full h-full object-cover"
          loading="lazy"
        />
        <div
          v-else
          class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blush-canvas/30 to-primary/20"
          aria-hidden="true"
        >
          <span class="material-symbols-outlined text-primary/50 text-2xl">play_circle</span>
        </div>
      </div>

      <!-- Course details -->
      <div class="min-w-0">
        <h3 class="font-title-md text-title-md text-deep-marsala font-semibold leading-snug line-clamp-2">
          {{ course.title }}
        </h3>
        <p class="font-label-sm text-label-sm text-on-surface-variant mt-0.5">
          Por {{ course.instructor?.name }}
        </p>

        <!-- Progress row -->
        <div class="flex items-center gap-3 mt-2">
          <div class="w-32">
            <ProgressBar :value="course.progress_percentage" />
          </div>
          <span
            class="font-label-sm text-label-sm"
            :class="course.progress_percentage === 100 ? 'text-primary' : 'text-on-surface-variant'"
          >
            {{ course.progress_percentage }}% COMPLETADO
          </span>
        </div>

        <!-- Lessons count -->
        <p class="font-label-sm text-label-sm text-outline mt-1">
          {{ course.completed_lessons }} / {{ course.total_lessons }} lecciones
        </p>
      </div>
    </div>

    <!-- Right: CTA -->
    <div class="flex-shrink-0">
      <BaseButton variant="primary" size="sm" @click="handleContinue">
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">play_arrow</span>
        Continuar
      </BaseButton>
    </div>
  </div>
</template>
