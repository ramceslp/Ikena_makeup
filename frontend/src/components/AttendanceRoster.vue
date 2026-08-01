<script setup>
import { ref, computed, watch } from 'vue'
import { useInstructorStore } from '../stores/instructor.js'

/**
 * Attendance for one live session.
 *
 * These checkboxes are what a certificate is made of: the API writes the same
 * lesson_progress rows the on-demand player writes, and the certificate gate
 * counts them. So the roster is explicit about being a record, not a
 * formality — and unchecking really does take the credit back.
 */
const props = defineProps({
  lessonId: { type: Number, required: true },
})

const instructorStore = useInstructorStore()

const open = ref(false)
const saving = ref(false)
const loaded = ref(false)
const selected = ref(new Set())

const roster = computed(() => instructorStore.attendance[props.lessonId] ?? [])
const presentCount = computed(() => selected.value.size)

// Fetch on first open only: a roster the instructor never looks at is a
// request per lesson on a course that may have twenty of them.
watch(open, async (isOpen) => {
  if (!isOpen || loaded.value) return
  await instructorStore.fetchAttendance(props.lessonId)
  syncFromRoster()
  loaded.value = true
})

function syncFromRoster() {
  selected.value = new Set(roster.value.filter((s) => s.attended).map((s) => s.id))
}

function toggle(studentId) {
  const next = new Set(selected.value)
  next.has(studentId) ? next.delete(studentId) : next.add(studentId)
  selected.value = next
}

async function handleSave() {
  saving.value = true
  try {
    await instructorStore.saveAttendance(props.lessonId, [...selected.value])
    syncFromRoster()
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="border-t border-gray-100 bg-white">
    <button
      type="button"
      class="w-full flex items-center justify-between px-4 py-2 text-xs font-medium text-gray-600 hover:text-brand-primary transition-colors"
      :aria-expanded="open"
      @click="open = !open"
    >
      <span class="flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Asistencia de la sesión
      </span>
      <svg
        class="w-3.5 h-3.5 transition-transform"
        :class="open ? 'rotate-180' : ''"
        fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <div v-if="open" class="px-4 pb-4" data-roster>
      <p v-if="loaded && roster.length === 0" class="text-xs text-gray-500 py-2">
        Todavía no hay alumnos inscritos en este curso.
      </p>

      <template v-else-if="loaded">
        <p class="text-xs text-gray-500 mb-2">
          Marcar la asistencia habilita el certificado. Desmarcar a un alumno se lo retira.
        </p>

        <ul class="divide-y divide-gray-100 border border-gray-100 rounded-lg overflow-hidden">
          <li
            v-for="student in roster"
            :key="student.id"
            class="flex items-center gap-3 px-3 py-2"
          >
            <input
              :id="`attendance-${lessonId}-${student.id}`"
              type="checkbox"
              class="w-4 h-4 accent-brand-accent shrink-0"
              :checked="selected.has(student.id)"
              @change="toggle(student.id)"
            />
            <label
              :for="`attendance-${lessonId}-${student.id}`"
              class="flex-1 min-w-0 cursor-pointer"
            >
              <span class="block text-sm text-gray-800 truncate">{{ student.name }}</span>
              <span class="block text-xs text-gray-500 truncate">{{ student.email }}</span>
            </label>
          </li>
        </ul>

        <div class="flex items-center justify-between pt-3">
          <span class="text-xs text-gray-500" data-present-count>
            {{ presentCount }} de {{ roster.length }} presentes
          </span>
          <button
            type="button"
            :disabled="saving"
            class="bg-brand-accent text-brand-primary px-4 py-1.5 rounded-lg font-semibold text-xs hover:opacity-90 transition-opacity disabled:opacity-50"
            @click="handleSave"
          >
            {{ saving ? 'Guardando...' : 'Guardar asistencia' }}
          </button>
        </div>
      </template>

      <p v-else class="text-xs text-gray-500 py-2" role="status">Cargando alumnos...</p>
    </div>
  </div>
</template>
