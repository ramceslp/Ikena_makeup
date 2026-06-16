<script setup>
import { ref, computed, useSlots } from 'vue'

// Two-way bound value via defineModel (Vue 3.4+)
const model = defineModel({ type: String, default: '' })

const props = defineProps({
  label: { type: String, default: '' },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  error: { type: String, default: '' },
  id: { type: String, default: '' },
  autocomplete: { type: String, default: '' },
  // When true and type === 'password', shows a visibility toggle button
  revealable: { type: Boolean, default: false },
})

// Matches the MD3 input style established in CourseFilters.vue
const inputClass = computed(() => [
  'w-full px-4 py-2 bg-surface-container-low border rounded-xl',
  'font-body-md text-body-md text-on-surface placeholder:text-outline',
  'outline-none transition-all',
  props.error
    ? 'border-error focus:ring-1 focus:ring-error focus:border-error'
    : 'border-blush-canvas/30 focus:ring-1 focus:ring-primary focus:border-primary',
])

// If a leading-icon slot is used, add left padding so the text doesn't overlap
const hasIcon = !!useSlots()['leading-icon']

// Password reveal toggle
const revealed = ref(false)
const effectiveType = computed(() => {
  if (props.type === 'password' && props.revealable && revealed.value) return 'text'
  return props.type
})

function toggleReveal() {
  revealed.value = !revealed.value
}
</script>

<template>
  <div class="flex flex-col gap-1">
    <!-- Label -->
    <label
      v-if="label"
      :for="id || undefined"
      class="font-label-md text-label-md text-on-surface-variant"
    >
      {{ label }}
    </label>

    <!-- Input wrapper (relative for icon / reveal slots) -->
    <div class="relative">
      <!-- Leading icon slot -->
      <span
        v-if="$slots['leading-icon']"
        class="absolute left-3 top-1/2 -translate-y-1/2 text-outline pointer-events-none"
        aria-hidden="true"
      >
        <slot name="leading-icon" />
      </span>

      <input
        v-model="model"
        :type="effectiveType"
        :id="id || undefined"
        :placeholder="placeholder"
        :autocomplete="autocomplete || undefined"
        :class="[
          $slots['leading-icon'] ? 'pl-10' : '',
          type === 'password' && revealable ? 'pr-10' : '',
          inputClass,
        ]"
        :aria-describedby="error ? `${id}-error` : undefined"
        :aria-invalid="error ? 'true' : undefined"
      />

      <!-- Password reveal toggle (only when revealable + type is password) -->
      <button
        v-if="type === 'password' && revealable"
        type="button"
        class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors"
        :aria-label="revealed ? 'Ocultar contraseña' : 'Mostrar contraseña'"
        @click="toggleReveal"
      >
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">
          {{ revealed ? 'visibility_off' : 'visibility' }}
        </span>
      </button>
    </div>

    <!-- Error message -->
    <p
      v-if="error"
      :id="id ? `${id}-error` : undefined"
      class="font-label-sm text-label-sm text-error flex items-center gap-1"
      role="alert"
    >
      <span class="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
      {{ error }}
    </p>
  </div>
</template>
