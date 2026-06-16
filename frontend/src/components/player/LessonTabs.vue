<script setup>
// Presentational: tab area below the video stage.
// Only the "Contenido" tab is included — no materials/submissions because
// the backend LessonResource does not return downloadable resources or
// submission endpoints. Those tabs are intentionally omitted (MIGRATION_BACKLOG #3).
// Uses TabGroup atom for accessible keyboard-navigable tabs.
import { ref } from 'vue'
import TabGroup from '../ui/TabGroup.vue'

defineProps({
  // Lesson object from LessonResource: { id, title, description, video_url, duration, is_free, completed }
  lesson: { type: Object, default: null },
})

const TABS = [{ key: 'contenido', label: 'Contenido' }]
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
    </TabGroup>
  </div>
</template>
