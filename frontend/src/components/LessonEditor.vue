<script setup>
import { ref, computed, watch } from 'vue'
import { useInstructorStore } from '../stores/instructor.js'
import VideoUrlInput from './VideoUrlInput.vue'

const props = defineProps({
  lesson: { type: Object, required: true },
  sectionId: { type: Number, required: true },
  lessonIndex: { type: Number, required: true },
  isFirst: { type: Boolean, default: false },
  isLast: { type: Boolean, default: false },
})

const instructorStore = useInstructorStore()

const saving = ref(false)
const expanded = ref(false)

// Local form state
const formTitle = ref(props.lesson.title ?? '')
const formDescription = ref(props.lesson.description ?? '')
const formVideoUrl = ref(props.lesson.video_url ?? '')
const formDuration = ref(props.lesson.duration ?? 0)
const formIsFree = ref(props.lesson.is_free ?? false)
const formIsPractice = ref(props.lesson.is_practice ?? false)

// Sync if lesson prop changes (e.g., after optimistic updates)
watch(() => props.lesson, (val) => {
  formTitle.value = val.title ?? ''
  formDescription.value = val.description ?? ''
  formVideoUrl.value = val.video_url ?? ''
  formDuration.value = val.duration ?? 0
  formIsFree.value = val.is_free ?? false
  formIsPractice.value = val.is_practice ?? false
})

const lessonValidationErrors = computed(() => {
  // validationErrors in store are keyed by field; there's no per-lesson namespacing
  // We show store-level errors as-is, which cover the current active action
  return instructorStore.validationErrors
})

async function handleSave() {
  saving.value = true
  try {
    await instructorStore.updateLesson(props.lesson.id, {
      title: formTitle.value.trim(),
      description: formDescription.value.trim(),
      video_url: formVideoUrl.value.trim() || null,
      duration: Number(formDuration.value) || null,
      is_free: formIsFree.value,
      is_practice: formIsPractice.value,
    })
  } finally {
    saving.value = false
  }
}

async function handleDelete() {
  if (!window.confirm(`¿Eliminar la lección "${props.lesson.title}"?`)) return
  await instructorStore.deleteLesson(props.lesson.id)
}

async function moveUp() {
  if (props.isFirst || !instructorStore.currentCourse) return
  const section = instructorStore.currentCourse.sections.find((s) => s.id === props.sectionId)
  if (!section) return
  const newOrder = section.lessons.map((l) => l.id)
  const tmp = newOrder[props.lessonIndex - 1]
  newOrder[props.lessonIndex - 1] = newOrder[props.lessonIndex]
  newOrder[props.lessonIndex] = tmp
  await instructorStore.reorderLessons(props.sectionId, newOrder)
}

async function moveDown() {
  if (props.isLast || !instructorStore.currentCourse) return
  const section = instructorStore.currentCourse.sections.find((s) => s.id === props.sectionId)
  if (!section) return
  const newOrder = section.lessons.map((l) => l.id)
  const tmp = newOrder[props.lessonIndex + 1]
  newOrder[props.lessonIndex + 1] = newOrder[props.lessonIndex]
  newOrder[props.lessonIndex] = tmp
  await instructorStore.reorderLessons(props.sectionId, newOrder)
}
</script>

<template>
  <div class="border-b border-gray-100 last:border-b-0">
    <!-- Lesson row header -->
    <div class="flex items-center gap-2 px-4 py-2">
      <!-- Reorder buttons -->
      <div class="flex flex-col gap-0.5 shrink-0">
        <button
          @click="moveUp"
          :disabled="isFirst"
          class="w-5 h-4 flex items-center justify-center rounded text-gray-300 hover:text-brand-primary disabled:opacity-20 disabled:cursor-not-allowed transition-colors"
          aria-label="Mover lección arriba"
        >
          <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
          </svg>
        </button>
        <button
          @click="moveDown"
          :disabled="isLast"
          class="w-5 h-4 flex items-center justify-center rounded text-gray-300 hover:text-brand-primary disabled:opacity-20 disabled:cursor-not-allowed transition-colors"
          aria-label="Mover lección abajo"
        >
          <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
      </div>

      <!-- Lesson title (toggle expand) -->
      <button
        @click="expanded = !expanded"
        class="flex-1 text-left text-sm text-gray-700 hover:text-brand-primary truncate"
      >
        {{ lesson.title }}
      </button>

      <!-- Free badge -->
      <span
        v-if="lesson.is_free"
        class="inline-flex items-center gap-1 text-xs text-green-700 bg-green-50 border border-green-200 px-1.5 py-0.5 rounded-full font-medium shrink-0"
      >
        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
          <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
        </svg>
        Gratis
      </span>

      <!-- Expand/collapse icon -->
      <button
        @click="expanded = !expanded"
        class="shrink-0 text-gray-400 hover:text-brand-primary transition-colors"
        :aria-label="expanded ? 'Contraer lección' : 'Expandir lección'"
      >
        <svg class="w-4 h-4 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <!-- Delete -->
      <button
        @click="handleDelete"
        class="shrink-0 text-red-400 hover:text-red-600 transition-colors p-1"
        aria-label="Eliminar lección"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Expanded form -->
    <div v-if="expanded" class="px-4 pb-4 space-y-3 bg-gray-50 border-t border-gray-100">
      <!-- Validation errors from store -->
      <div
        v-if="Object.keys(lessonValidationErrors).length"
        class="bg-red-50 border border-red-200 rounded-lg p-3 mt-3"
      >
        <p class="text-xs text-red-700 font-medium mb-1 flex items-center gap-1">
          <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
          Errores de validación
        </p>
        <ul class="text-xs text-red-600 list-disc list-inside space-y-0.5">
          <li v-for="(msgs, field) in lessonValidationErrors" :key="field">
            <span class="font-medium">{{ field }}:</span>
            {{ Array.isArray(msgs) ? msgs[0] : msgs }}
          </li>
        </ul>
      </div>

      <!-- Title -->
      <div class="pt-3">
        <label :for="`lesson-title-${lesson.id}`" class="block text-xs font-medium text-gray-600 mb-1">Título</label>
        <input
          :id="`lesson-title-${lesson.id}`"
          v-model="formTitle"
          type="text"
          class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent"
        />
      </div>

      <!-- Description -->
      <div>
        <label :for="`lesson-desc-${lesson.id}`" class="block text-xs font-medium text-gray-600 mb-1">Descripción</label>
        <textarea
          :id="`lesson-desc-${lesson.id}`"
          v-model="formDescription"
          rows="3"
          class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent resize-none"
        />
      </div>

      <!-- Video URL -->
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">URL del video</label>
        <VideoUrlInput v-model="formVideoUrl" />
      </div>

      <!-- Duration -->
      <div>
        <label :for="`lesson-duration-${lesson.id}`" class="block text-xs font-medium text-gray-600 mb-1">Duración (segundos)</label>
        <input
          :id="`lesson-duration-${lesson.id}`"
          v-model="formDuration"
          type="number"
          min="0"
          class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent"
        />
      </div>

      <!-- is_free -->
      <div class="flex items-center gap-2">
        <input
          :id="`lesson-free-${lesson.id}`"
          v-model="formIsFree"
          type="checkbox"
          class="w-4 h-4 accent-brand-accent"
        />
        <label :for="`lesson-free-${lesson.id}`" class="text-xs font-medium text-gray-600">Lección gratuita (visible sin inscripción)</label>
      </div>

      <!-- is_practice -->
      <div class="flex items-center gap-2">
        <input
          :id="`lesson-practice-${lesson.id}`"
          v-model="formIsPractice"
          type="checkbox"
          class="w-4 h-4 accent-brand-accent"
        />
        <label :for="`lesson-practice-${lesson.id}`" class="text-xs font-medium text-gray-600">Lección de práctica (los alumnos suben fotos antes/después)</label>
      </div>

      <!-- Save -->
      <div class="flex justify-end pt-1">
        <button
          @click="handleSave"
          :disabled="saving"
          class="bg-brand-accent text-brand-primary px-4 py-1.5 rounded-lg text-xs font-semibold hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center gap-2"
        >
          <svg v-if="saving" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          {{ saving ? 'Guardando...' : 'Guardar' }}
        </button>
      </div>
    </div>
  </div>
</template>
