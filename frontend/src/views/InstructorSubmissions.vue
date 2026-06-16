<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useInstructorStore } from '../stores/instructor.js'
import ReviewTasksList from '../components/instructor/ReviewTasksList.vue'
import TaskReviewModal from '../components/instructor/TaskReviewModal.vue'

const instructorStore = useInstructorStore()

const modalOpen = ref(false)
const selected = ref(null)

const STATUS_FILTERS = [
  { key: null, label: 'Todas' },
  { key: 'pending', label: 'Pendientes' },
  { key: 'approved', label: 'Aprobadas' },
  { key: 'needs_work', label: 'Necesitan correcciones' },
]

const activeFilter = ref(null)

async function applyFilter(status) {
  activeFilter.value = status
  await instructorStore.fetchSubmissions(status)
}

function openModal(submission) {
  selected.value = submission
  modalOpen.value = true
}

async function handleGrade({ status, feedback }) {
  if (!selected.value) return
  await instructorStore.gradeSubmission(selected.value.id, { status, feedback })
  modalOpen.value = false
}

onMounted(() => {
  instructorStore.fetchSubmissions()
})
</script>

<template>
  <section class="py-16 bg-background min-h-screen">
    <div class="max-w-container-max mx-auto px-gutter">

      <!-- Page header -->
      <div class="mb-10 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
          <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Entregas por revisar</h1>
          <p class="font-body-md text-body-md text-on-surface-variant mt-1">
            Revisa y califica las prácticas de tus alumnos
          </p>
        </div>

        <RouterLink
          to="/instructor/dashboard"
          class="inline-flex items-center gap-2 font-label-md text-label-md text-primary border border-primary px-4 py-2 rounded-xl hover:bg-primary hover:text-on-primary transition-colors self-start sm:self-auto"
        >
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">dashboard</span>
          Panel
        </RouterLink>
      </div>

      <!-- Status filter buttons -->
      <div class="flex flex-wrap gap-2 mb-6">
        <button
          v-for="filter in STATUS_FILTERS"
          :key="String(filter.key)"
          @click="applyFilter(filter.key)"
          class="font-label-md text-label-md px-4 py-2 rounded-xl border transition-colors"
          :class="activeFilter === filter.key
            ? 'bg-primary text-on-primary border-primary'
            : 'border-outline-variant text-on-surface-variant hover:border-primary hover:text-primary'"
        >
          {{ filter.label }}
        </button>
      </div>

      <!-- List -->
      <ReviewTasksList
        :submissions="instructorStore.submissions"
        :loading="instructorStore.submissionsLoading"
        @open="openModal"
      />

      <!-- Grading modal -->
      <TaskReviewModal
        v-model="modalOpen"
        :submission="selected"
        :grading="instructorStore.grading"
        :error="instructorStore.error || ''"
        @grade="handleGrade"
      />

    </div>
  </section>
</template>
