<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  images: {
    type: Array,
    default: () => [],
  },
})

const activeIndex = ref(0)

const activeImage = computed(() => props.images[activeIndex.value] ?? null)

function selectImage(index) {
  activeIndex.value = index
}

function next() {
  if (!props.images.length) return
  activeIndex.value = (activeIndex.value + 1) % props.images.length
}

function prev() {
  if (!props.images.length) return
  activeIndex.value = (activeIndex.value - 1 + props.images.length) % props.images.length
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <!-- Main image -->
    <div class="relative aspect-[4/3] rounded-2xl overflow-hidden bg-surface-container">
      <img
        v-if="activeImage"
        data-main-image
        :src="activeImage.url"
        :alt="`Imagen ${activeIndex + 1}`"
        class="w-full h-full object-cover"
      />
      <div
        v-else
        class="w-full h-full flex items-center justify-center"
      >
        <span class="material-symbols-outlined text-5xl text-blush-canvas" aria-hidden="true">image</span>
      </div>

      <!-- Navigation chevrons (only when >1 image) -->
      <template v-if="images.length > 1">
        <button
          data-gallery-prev
          type="button"
          @click="prev"
          class="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-surface/80 backdrop-blur flex items-center justify-center hover:bg-surface transition-colors shadow"
          aria-label="Imagen anterior"
        >
          <span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>
        </button>
        <button
          data-gallery-next
          type="button"
          @click="next"
          class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-surface/80 backdrop-blur flex items-center justify-center hover:bg-surface transition-colors shadow"
          aria-label="Imagen siguiente"
        >
          <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
        </button>
      </template>
    </div>

    <!-- Thumbnails -->
    <div v-if="images.length > 1" class="flex gap-2 overflow-x-auto pb-1">
      <button
        v-for="(img, index) in images"
        :key="img.id"
        type="button"
        @click="selectImage(index)"
        class="shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 transition-colors"
        :class="activeIndex === index
          ? 'border-primary'
          : 'border-transparent hover:border-blush-canvas'"
        :aria-pressed="activeIndex === index"
        :aria-label="`Ver imagen ${index + 1}`"
      >
        <img
          data-thumbnail
          :src="img.url"
          :alt="`Miniatura ${index + 1}`"
          class="w-full h-full object-cover"
          loading="lazy"
        />
      </button>
    </div>
  </div>
</template>
