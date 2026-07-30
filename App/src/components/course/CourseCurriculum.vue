<script setup>
import { ref } from 'vue'
import BaseBadge from '../ui/BaseBadge.vue'

// Read-only curriculum outline for views/CourseDetail.vue. Mirrors
// frontend/src/components/course/CurriculumAccordion.vue's accordion
// interaction and mm:ss duration format (duration is stored in seconds --
// see backend/database/migrations/2026_06_15_100002_create_lessons_table.php
// + Lesson::casts()), but manages its own open-section state internally
// instead of receiving it from a parent -- this app has no lesson player, so
// there is no "completed" state or toggle-section event to lift up, unlike
// the frontend's version.
defineProps({
  // Array of { id, title, position, lessons: [{ id, title, position, is_free, duration }] }
  sections: { type: Array, default: () => [] },
})

// { [sectionId]: boolean } -- explicit overrides only. When a section has no
// entry here, isOpen() falls back to "first section starts open" so the
// outline isn't fully collapsed on first render.
const openSections = ref({})

// Takes the CURRENT effective open state (as computed by isOpen() below,
// which accounts for the "first section defaults open" fallback) rather than
// re-deriving it from sectionId alone -- otherwise toggling the first
// section closed would read "no explicit entry" and flip false -> true,
// re-opening it instead of closing it.
function toggleSection(sectionId, currentlyOpen) {
  openSections.value[sectionId] = !currentlyOpen
}

function isOpen(section, idx) {
  const explicit = openSections.value[section.id]
  if (explicit !== undefined) return explicit
  return idx === 0
}

function formatDuration(seconds) {
  if (!seconds) return '0:00'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}:${s.toString().padStart(2, '0')}`
}
</script>

<template>
  <div v-if="sections.length" class="space-y-4">
    <div
      v-for="(section, idx) in sections"
      :key="section.id"
      data-curriculum-section
      class="border border-blush-canvas/30 rounded-xl overflow-hidden bg-surface-muted"
    >
      <!-- Section header (button) -->
      <button
        type="button"
        data-section-toggle
        class="w-full flex items-center justify-between p-6 text-left hover:bg-surface-container-low transition-colors min-h-[48px]"
        :aria-expanded="isOpen(section, idx) ? 'true' : 'false'"
        :aria-controls="`section-panel-${section.id}`"
        :id="`section-header-${section.id}`"
        @click="toggleSection(section.id, isOpen(section, idx))"
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
            :class="isOpen(section, idx) ? 'rotate-180' : ''"
            aria-hidden="true"
          >
            expand_more
          </span>
        </div>
      </button>

      <!-- Lesson list (collapsible) -->
      <div
        v-show="isOpen(section, idx)"
        :id="`section-panel-${section.id}`"
        :aria-labelledby="`section-header-${section.id}`"
        role="region"
      >
        <div
          v-for="lesson in section.lessons"
          :key="lesson.id"
          data-curriculum-lesson
          class="flex items-center gap-3 px-6 py-3 border-t border-blush-canvas/10"
        >
          <span class="material-symbols-outlined text-[20px] text-primary shrink-0" aria-hidden="true">
            play_circle
          </span>

          <!-- Lesson title -->
          <span class="flex-1 font-body-md text-body-md text-on-surface">
            {{ lesson.title }}
          </span>

          <!-- Badges + duration -->
          <div class="flex items-center gap-2 shrink-0">
            <BaseBadge v-if="lesson.is_free" variant="blush" pill>
              Vista previa
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
</template>
