<script setup>
import { ref, watch } from 'vue'
import BaseModal from '../ui/BaseModal.vue'
import BaseButton from '../ui/BaseButton.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  submission: { type: Object, default: null },
  grading: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'grade'])

const feedback = ref(props.submission?.feedback ?? '')

watch(() => props.submission, (val) => {
  feedback.value = val?.feedback ?? ''
})

function approve() {
  emit('grade', { status: 'approved', feedback: feedback.value })
}

function requestCorrections() {
  emit('grade', { status: 'needs_work', feedback: feedback.value })
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    title="Revisar entrega"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div v-if="submission" class="space-y-4">
      <!-- Student + lesson -->
      <div>
        <p class="font-title-md text-title-md text-deep-marsala">{{ submission.user?.name }}</p>
        <p class="font-body-md text-body-md text-on-surface-variant">{{ submission.lesson?.title }}</p>
      </div>

      <!-- Before / After images -->
      <div class="flex gap-3">
        <div class="flex-1">
          <p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Foto antes</p>
          <img :src="submission.before_url" alt="Foto antes" class="w-full rounded-lg object-cover aspect-video" />
        </div>
        <div class="flex-1">
          <p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Foto después</p>
          <img :src="submission.after_url" alt="Foto después" class="w-full rounded-lg object-cover aspect-video" />
        </div>
      </div>

      <!-- Feedback textarea -->
      <div>
        <label class="block font-label-md text-label-md text-on-surface-variant mb-1">
          Retroalimentación
        </label>
        <textarea
          v-model="feedback"
          rows="4"
          maxlength="2000"
          class="w-full border border-outline-variant rounded-xl px-3 py-2 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary resize-none bg-surface-container"
          placeholder="Escribe tu retroalimentación para el alumno..."
        />
      </div>

      <!-- Error alert -->
      <div v-if="error" role="alert" class="bg-error-container text-on-error-container rounded-lg p-3 font-body-md text-body-md">
        {{ error }}
      </div>
    </div>

    <template #footer>
      <div class="flex gap-3 justify-end">
        <BaseButton
          variant="outline"
          size="sm"
          :disabled="grading"
          @click="requestCorrections"
        >
          Necesita correcciones
        </BaseButton>
        <BaseButton
          variant="primary"
          size="sm"
          :disabled="grading"
          @click="approve"
        >
          Aprobar
        </BaseButton>
      </div>
    </template>
  </BaseModal>
</template>
