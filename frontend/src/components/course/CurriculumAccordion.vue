<script setup>
// Purely presentational: receives sections + openSections map.
// The container toggles section open state and passes it down.
import BaseBadge from '../ui/BaseBadge.vue'

defineProps({
  // Array of { id, title, position, lessons: [{ id, title, position, is_free, duration, completed? }] }
  sections: { type: Array, default: () => [] },
  // { [sectionId]: boolean } — which sections are expanded
  openSections: { type: Object, default: () => ({}) },
})

const emit = defineEmits([
  // Emitted when a section header is clicked; parent updates openSections
  'toggle-section',
])

function formatDuration(seconds) {
  if (!seconds) return '0:00'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}:${s.toString().padStart(2, '0')}`
}
</script>

<template>
  <section>
    <h2 class="font-headline-lg text-headline-lg text-deep-marsala mb-8">
      Temario del curso
    </h2>

    <div v-if="sections.length" class="space-y-4">
      <div
        v-for="(section, idx) in sections"
        :key="section.id"
        class="border border-blush-canvas/30 rounded-xl overflow-hidden bg-surface-muted"
      >
        <!-- Section header (button) -->
        <button
          class="w-full flex items-center justify-between p-6 text-left hover:bg-surface-container-low transition-colors"
          :aria-expanded="openSections[section.id] ? 'true' : 'false'"
          :aria-controls="`section-panel-${section.id}`"
          :id="`section-header-${section.id}`"
          @click="emit('toggle-section', section.id)"
        >
          <div class="flex items-center gap-4">
            <!-- Section number chip -->
            <span class="font-label-md text-label-md text-on-surface-variant bg-surface-container-high w-8 h-8 rounded-full flex items-center justify-center shrink-0">
              {{ String(idx + 1).padStart(2, '0') }}
            </span>
            <h3 class="font-title-md text-title-md text-deep-marsala text-left">
              {{ section.title }}
            </h3>
          </div>

          <div class="flex items-center gap-3 shrink-0">
            <span class="font-label-sm text-label-sm text-outline hidden sm:block">
              {{ section.lessons?.length || 0 }} lecciones
            </span>
            <!-- Chevron icon rotates when section is open -->
            <span
              class="material-symbols-outlined text-primary transition-transform duration-200"
              :class="openSections[section.id] ? 'rotate-180' : ''"
              aria-hidden="true"
            >
              expand_more
            </span>
          </div>
        </button>

        <!-- Lesson list (collapsible) -->
        <div
          v-show="openSections[section.id]"
          :id="`section-panel-${section.id}`"
          :aria-labelledby="`section-header-${section.id}`"
          role="region"
        >
          <div
            v-for="lesson in section.lessons"
            :key="lesson.id"
            class="flex items-center gap-3 px-6 py-3 border-t border-blush-canvas/10 hover:bg-white/50 transition-all"
          >
            <!-- Completion indicator (only present when lesson.completed is defined, i.e. user is enrolled) -->
            <div
              v-if="lesson.completed !== undefined"
              class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0"
              :class="lesson.completed
                ? 'bg-apricot-glow border-apricot-glow'
                : 'border-outline-variant'"
              :aria-label="lesson.completed ? 'Lección completada' : 'Lección pendiente'"
            >
              <span
                v-if="lesson.completed"
                class="material-symbols-outlined text-[12px] text-deep-marsala"
                aria-hidden="true"
                style="font-variation-settings: 'FILL' 1;"
              >
                check
              </span>
            </div>

            <!-- Play icon for non-enrolled or when completed state absent -->
            <span
              v-else
              class="material-symbols-outlined text-[20px] text-primary shrink-0"
              aria-hidden="true"
            >
              play_circle
            </span>

            <!-- Lesson title -->
            <span class="flex-1 font-body-md text-body-md text-on-surface">
              {{ lesson.title }}
            </span>

            <!-- Badges + duration -->
            <div class="flex items-center gap-2 shrink-0">
              <BaseBadge v-if="lesson.is_free" variant="blush" pill>
                Gratis
              </BaseBadge>
              <span class="font-label-sm text-label-sm text-outline">
                {{ formatDuration(lesson.duration) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <p v-else class="font-body-md text-body-md text-outline">
      Este curso aún no tiene secciones.
    </p>
  </section>
</template>
