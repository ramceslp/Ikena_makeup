<script setup>
import { computed } from 'vue'
import { resolveVideo } from '../utils/video.js'

const props = defineProps({
  modelValue: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const resolved = computed(() => resolveVideo(props.modelValue))
const hasValue = computed(() => (props.modelValue ?? '').trim().length > 0)
</script>

<template>
  <div>
    <input
      :value="modelValue"
      @input="emit('update:modelValue', $event.target.value)"
      type="text"
      placeholder="https://www.youtube.com/watch?v=... o enlace .mp4"
      class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent"
    />

    <!-- Live preview -->
    <div v-if="hasValue" class="mt-2">
      <!-- YouTube / Vimeo embed -->
      <template v-if="resolved.type === 'youtube' || resolved.type === 'vimeo'">
        <div class="aspect-video rounded-lg overflow-hidden bg-black">
          <iframe
            :src="resolved.embedUrl"
            class="w-full h-full"
            frameborder="0"
            allow="autoplay; fullscreen; picture-in-picture"
            allowfullscreen
            title="Vista previa del video"
          />
        </div>
      </template>

      <!-- Direct MP4 -->
      <template v-else-if="resolved.type === 'mp4'">
        <div class="rounded-lg overflow-hidden bg-black">
          <video controls class="w-full">
            <source :src="resolved.src" />
            Tu navegador no soporta reproducción de video.
          </video>
        </div>
      </template>

      <!-- Unknown / invalid (non-empty) — icon + text, never color-only -->
      <template v-else>
        <div class="flex items-start gap-2 text-sm text-gray-500 mt-1">
          <svg class="w-4 h-4 shrink-0 mt-0.5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>URL de video no válida. Use YouTube, Vimeo o un enlace .mp4 directo.</span>
        </div>
      </template>
    </div>
    <!-- Empty: no preview -->
  </div>
</template>
