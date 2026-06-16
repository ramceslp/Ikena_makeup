<script setup>
import { ref, computed } from 'vue'
import { useInstructorStore } from '../stores/instructor.js'
import LessonEditor from './LessonEditor.vue'

const props = defineProps({
  section: { type: Object, required: true },
  sectionIndex: { type: Number, required: true },
  isFirst: { type: Boolean, default: false },
  isLast: { type: Boolean, default: false },
  courseSlug: { type: String, required: true },
})

const instructorStore = useInstructorStore()

// Inline title editing
const isEditingTitle = ref(false)
const editTitle = ref(props.section.title)
const savingTitle = ref(false)

// New lesson form
const newLessonTitle = ref('')
const addingLesson = ref(false)

const lessons = computed(() => props.section.lessons ?? [])

function startEditTitle() {
  editTitle.value = props.section.title
  isEditingTitle.value = true
}

function cancelEditTitle() {
  isEditingTitle.value = false
  editTitle.value = props.section.title
}

async function saveTitle() {
  const title = editTitle.value.trim()
  if (!title || title === props.section.title) {
    isEditingTitle.value = false
    return
  }
  savingTitle.value = true
  try {
    await instructorStore.updateSection(props.section.id, { title })
    isEditingTitle.value = false
  } finally {
    savingTitle.value = false
  }
}

async function handleDelete() {
  if (!window.confirm(`¿Eliminar la sección "${props.section.title}"? Se eliminarán también todas sus lecciones.`)) return
  await instructorStore.deleteSection(props.section.id)
}

async function moveUp() {
  if (props.isFirst || !instructorStore.currentCourse) return
  const sections = instructorStore.currentCourse.sections
  const newOrder = sections.map((s) => s.id)
  // Swap with previous
  const tmp = newOrder[props.sectionIndex - 1]
  newOrder[props.sectionIndex - 1] = newOrder[props.sectionIndex]
  newOrder[props.sectionIndex] = tmp
  await instructorStore.reorderSections(props.courseSlug, newOrder)
}

async function moveDown() {
  if (props.isLast || !instructorStore.currentCourse) return
  const sections = instructorStore.currentCourse.sections
  const newOrder = sections.map((s) => s.id)
  // Swap with next
  const tmp = newOrder[props.sectionIndex + 1]
  newOrder[props.sectionIndex + 1] = newOrder[props.sectionIndex]
  newOrder[props.sectionIndex] = tmp
  await instructorStore.reorderSections(props.courseSlug, newOrder)
}

async function handleAddLesson() {
  const title = newLessonTitle.value.trim()
  if (!title) return
  addingLesson.value = true
  try {
    await instructorStore.createLesson(props.section.id, { title })
    newLessonTitle.value = ''
  } finally {
    addingLesson.value = false
  }
}
</script>

<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <!-- Section header -->
    <div class="flex items-center gap-2 px-4 py-3 bg-gray-50 border-b border-gray-100">
      <!-- Reorder buttons -->
      <div class="flex flex-col gap-0.5 shrink-0">
        <button
          @click="moveUp"
          :disabled="isFirst"
          class="w-6 h-5 flex items-center justify-center rounded text-gray-400 hover:text-brand-primary hover:bg-gray-200 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
          aria-label="Mover sección arriba"
        >
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
          </svg>
        </button>
        <button
          @click="moveDown"
          :disabled="isLast"
          class="w-6 h-5 flex items-center justify-center rounded text-gray-400 hover:text-brand-primary hover:bg-gray-200 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
          aria-label="Mover sección abajo"
        >
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
      </div>

      <!-- Title inline edit -->
      <div class="flex-1 min-w-0">
        <template v-if="isEditingTitle">
          <div class="flex items-center gap-2">
            <input
              v-model="editTitle"
              type="text"
              class="flex-1 border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent"
              @keyup.enter="saveTitle"
              @keyup.escape="cancelEditTitle"
              autofocus
            />
            <button
              @click="saveTitle"
              :disabled="savingTitle"
              class="text-xs bg-brand-accent text-brand-primary px-2 py-1 rounded font-medium hover:opacity-90 disabled:opacity-50"
            >
              {{ savingTitle ? '...' : 'Guardar' }}
            </button>
            <button
              @click="cancelEditTitle"
              class="text-xs text-gray-500 hover:text-gray-700 px-2 py-1"
            >
              Cancelar
            </button>
          </div>
        </template>
        <template v-else>
          <button
            @click="startEditTitle"
            class="text-sm font-semibold text-brand-primary hover:underline text-left truncate w-full"
          >
            {{ section.title }}
          </button>
        </template>
      </div>

      <!-- Delete section -->
      <button
        @click="handleDelete"
        class="shrink-0 text-red-400 hover:text-red-600 transition-colors p-1 rounded"
        aria-label="Eliminar sección"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
      </button>
    </div>

    <!-- Lesson list -->
    <div class="divide-y divide-gray-50">
      <LessonEditor
        v-for="(lesson, idx) in lessons"
        :key="lesson.id"
        :lesson="lesson"
        :section-id="section.id"
        :lesson-index="idx"
        :is-first="idx === 0"
        :is-last="idx === lessons.length - 1"
      />

      <div v-if="!lessons.length" class="px-4 py-3 text-xs text-gray-400">
        Esta sección no tiene lecciones aún.
      </div>
    </div>

    <!-- Add lesson -->
    <div class="px-4 py-3 border-t border-gray-100 bg-gray-50">
      <div class="flex gap-2">
        <input
          v-model="newLessonTitle"
          type="text"
          placeholder="Título de la lección"
          class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent"
          @keyup.enter="handleAddLesson"
        />
        <button
          @click="handleAddLesson"
          :disabled="addingLesson || !newLessonTitle.trim()"
          class="bg-brand-accent text-brand-primary px-3 py-1.5 rounded-lg text-xs font-medium hover:opacity-90 disabled:opacity-50 transition-opacity flex items-center gap-1"
        >
          <svg v-if="addingLesson" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          Añadir lección
        </button>
      </div>
    </div>
  </div>
</template>
