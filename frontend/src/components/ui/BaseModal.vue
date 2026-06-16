<script setup>
import { watch, onUnmounted } from 'vue'

// open state is two-way bound — parent uses v-model or :modelValue + @update:modelValue
const open = defineModel({ type: Boolean, default: false })

defineProps({
  title: { type: String, default: '' },
})

// Keyboard: close on Escape
function onKeydown(e) {
  if (e.key === 'Escape') open.value = false
}

// Add/remove the Escape listener based on open state
watch(open, (val) => {
  if (val) {
    document.addEventListener('keydown', onKeydown)
  } else {
    document.removeEventListener('keydown', onKeydown)
  }
})

// Safety cleanup if the component is unmounted while open
onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})

function closeOnBackdrop(e) {
  // Only close when clicking the backdrop itself, not children
  if (e.target === e.currentTarget) open.value = false
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      leave-active-class="transition-opacity duration-150"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        :aria-label="title || 'Diálogo'"
        @click="closeOnBackdrop"
      >
        <!-- Dim backdrop -->
        <div class="absolute inset-0 bg-on-surface/40 backdrop-blur-sm" aria-hidden="true" />

        <!-- Panel -->
        <div
          class="relative z-10 w-full max-w-lg bg-surface-container-lowest rounded-xl shadow-2xl shadow-primary/10 flex flex-col max-h-[90vh]"
          @click.stop
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-blush-canvas/20 shrink-0">
            <h2 class="font-title-md text-title-md text-deep-marsala">{{ title }}</h2>
            <button
              class="p-1 rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
              aria-label="Cerrar"
              @click="open = false"
            >
              <span class="material-symbols-outlined text-[22px]">close</span>
            </button>
          </div>

          <!-- Body (scrollable) -->
          <div class="overflow-y-auto px-6 py-5 flex-1 custom-scrollbar">
            <slot />
          </div>

          <!-- Footer slot (optional) -->
          <div
            v-if="$slots.footer"
            class="px-6 py-4 border-t border-blush-canvas/20 shrink-0"
          >
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
