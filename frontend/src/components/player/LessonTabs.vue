<script setup>
// Presentational: tab area below the video stage.
// Uses TabGroup atom for accessible keyboard-navigable tabs.
// Dynamically adds a "Práctica" tab when lesson.is_practice is true.
import { ref, computed } from 'vue'
import TabGroup from '../ui/TabGroup.vue'
import PracticeSubmission from './PracticeSubmission.vue'

const props = defineProps({
  // Lesson object from LessonResource: { id, title, description, video_url, duration, is_free, is_practice, completed, my_submission }
  lesson: { type: Object, default: null },
  submission: { type: Object, default: null },
  submitting: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['submit-practice'])

const TABS = computed(() => {
  const tabs = [{ key: 'contenido', label: 'Contenido' }]
  if (props.lesson?.is_practice) {
    tabs.push({ key: 'practica', label: 'Práctica' })
  }
  return tabs
})

const activeTab = ref('contenido')
</script>

<template>
  <div
    v-if="lesson"
    class="bg-surface-muted rounded-xl border border-blush-canvas/20 shadow-sm px-4 py-2"
  >
    <TabGroup v-model="activeTab" :tabs="TABS">
      <!-- Contenido tab: lesson description -->
      <template #tab-contenido>
        <div class="px-2 pb-4">
          <p
            v-if="lesson.description"
            class="font-body-md text-body-md text-on-surface-variant leading-relaxed"
          >
            {{ lesson.description }}
          </p>
          <p
            v-else
            class="font-body-md text-body-md text-outline italic"
          >
            Este lección no tiene descripción adicional.
          </p>
        </div>
      </template>

      <!-- Práctica tab: only shown when lesson.is_practice is true -->
      <template v-if="lesson?.is_practice" #tab-practica>
        <div class="px-2 pb-4">
          <PracticeSubmission
            :submission="submission"
            :submitting="submitting"
            :error="error"
            @submit="emit('submit-practice', $event)"
          />
        </div>
      </template>
    </TabGroup>
  </div>
</template>
