<script setup>
import { computed } from 'vue'

/**
 * The player stage for a scheduled live session.
 *
 * Replaces the video frame when there is nothing to play: either the room is
 * open and the student should join it, or the session has not started and the
 * only useful thing to show is when to come back.
 *
 * The API decides which of those it is. meeting_url arrives filled only
 * inside the session window and null outside it, so this component never has
 * to reason about whether the link may be shown — it only has to reflect
 * whether it was given one.
 */
const props = defineProps({
  // { title, meeting_url, starts_at, meeting_available_at }
  lesson: { type: Object, required: true },
})

const isOpen = computed(() => Boolean(props.lesson.meeting_url))

function formatMoment(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('es-EC', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const scheduledFor = computed(() => formatMoment(props.lesson.starts_at))
const opensAt = computed(() => formatMoment(props.lesson.meeting_available_at))

/**
 * A session whose window has closed reads differently from one that has not
 * opened: "come back later" would be wrong advice once it is over.
 */
const hasEnded = computed(() => {
  if (isOpen.value || !props.lesson.starts_at) return false
  return new Date(props.lesson.starts_at) < new Date()
})
</script>

<template>
  <div class="absolute inset-0 flex items-center justify-center px-6 text-center">
    <!-- Room is open -->
    <div v-if="isOpen" class="space-y-4" data-live-open>
      <span
        class="material-symbols-outlined text-[48px] text-apricot-glow block"
        aria-hidden="true"
        style="font-variation-settings: 'FILL' 1;"
      >
        sensors
      </span>
      <p class="font-title-md text-title-md text-surface">La sesión está disponible</p>
      <a
        :href="lesson.meeting_url"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-2 bg-apricot-glow text-deep-marsala px-6 py-3 rounded-full font-label-lg text-label-lg hover:opacity-90 transition-opacity"
      >
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">videocam</span>
        Entrar a la sesión
      </a>
    </div>

    <!-- Session is over and no recording was published -->
    <div v-else-if="hasEnded" class="space-y-3" role="status" data-live-ended>
      <span
        class="material-symbols-outlined text-[48px] text-outline/60 block"
        aria-hidden="true"
      >
        event_busy
      </span>
      <p class="font-title-md text-title-md text-surface">Esta sesión ya finalizó</p>
      <p class="font-body-md text-body-md text-outline">
        Si el instructor publica la grabación, aparecerá aquí.
      </p>
    </div>

    <!-- Scheduled, not yet open -->
    <div v-else class="space-y-3" role="status" data-live-scheduled>
      <span
        class="material-symbols-outlined text-[48px] text-outline/60 block"
        aria-hidden="true"
      >
        event_upcoming
      </span>
      <p class="font-title-md text-title-md text-surface">Sesión programada</p>
      <p v-if="scheduledFor" class="font-body-md text-body-md text-surface/80">
        {{ scheduledFor }}
      </p>
      <p v-if="opensAt" class="font-body-sm text-body-sm text-outline">
        El enlace se habilita el {{ opensAt }}.
      </p>
    </div>
  </div>
</template>
