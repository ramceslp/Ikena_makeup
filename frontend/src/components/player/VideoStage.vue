<script setup>
// Presentational: renders the video embed and lesson info below.
// Reuses resolveVideo from utils/video.js — receives the already-resolved descriptor
// to avoid calling it twice (container resolves it once).
import ProgressBar from '../ui/ProgressBar.vue'

defineProps({
  // Resolved descriptor from resolveVideo(lesson.video_url)
  // Shape: { type: 'youtube'|'vimeo'|'mp4'|'unknown', embedUrl?: string, src?: string }
  resolvedVideo: { type: Object, default: () => ({ type: 'unknown' }) },
  // Full lesson object: { id, title, description, video_url, duration, is_free, completed }
  lesson: { type: Object, default: null },
  // True while the lesson is being fetched
  loading: { type: Boolean, default: false },
  // Course completion percentage (0-100), shown in the header area
  progressValue: { type: Number, default: 0 },
})
</script>

<template>
  <div class="flex flex-col min-h-0">
    <!-- Progress bar strip at top — course completion -->
    <div v-if="progressValue > 0" class="px-4 pt-3">
      <ProgressBar :value="progressValue" :show-label="true" />
    </div>

    <!-- Video area — black background keeps focus on media -->
    <div class="bg-on-surface flex-1 flex items-center justify-center relative aspect-video">

      <!-- Lesson loading spinner -->
      <div
        v-if="loading"
        class="absolute inset-0 flex items-center justify-center bg-on-surface"
        aria-label="Cargando lección..."
        aria-busy="true"
      >
        <svg class="animate-spin w-10 h-10 text-apricot-glow" fill="none" viewBox="0 0 24 24" aria-hidden="true">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <template v-else-if="lesson">
        <!-- YouTube or Vimeo embed -->
        <iframe
          v-if="resolvedVideo.type === 'youtube' || resolvedVideo.type === 'vimeo'"
          :src="resolvedVideo.embedUrl"
          class="w-full h-full absolute inset-0"
          frameborder="0"
          allow="autoplay; fullscreen; picture-in-picture"
          allowfullscreen
          :title="lesson.title"
        />

        <!-- Direct MP4 -->
        <video
          v-else-if="resolvedVideo.type === 'mp4'"
          controls
          class="w-full h-full absolute inset-0 object-contain"
          :key="lesson.id"
        >
          <source :src="resolvedVideo.src" />
          Tu navegador no soporta la reproducción de video.
        </video>

        <!-- No video URL -->
        <div v-else class="text-center text-outline" role="status">
          <span
            class="material-symbols-outlined text-[48px] text-outline/50 block mb-3"
            aria-hidden="true"
          >
            videocam_off
          </span>
          <p class="font-body-md text-body-md text-on-surface-variant">Video no disponible</p>
        </div>
      </template>

      <!-- No lesson selected yet -->
      <div v-else class="text-center" role="status">
        <span
          class="material-symbols-outlined text-[48px] text-outline/40 block mb-3"
          aria-hidden="true"
        >
          play_circle
        </span>
        <p class="font-body-md text-body-md text-on-surface-variant">
          Selecciona una lección para comenzar
        </p>
      </div>
    </div>

    <!-- Lesson info panel — light surface below the video -->
    <div
      v-if="lesson && !loading"
      class="bg-surface-container-lowest px-6 py-5 border-t border-blush-canvas/20"
    >
      <h2 class="font-title-md text-title-md text-deep-marsala mb-1">
        {{ lesson.title }}
      </h2>
      <p
        v-if="lesson.description"
        class="font-body-md text-body-md text-on-surface-variant leading-relaxed"
      >
        {{ lesson.description }}
      </p>
    </div>
  </div>
</template>
