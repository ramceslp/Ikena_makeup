<script setup>
import { ref, computed } from 'vue'
import BaseBadge from '../ui/BaseBadge.vue'
import BaseButton from '../ui/BaseButton.vue'

const props = defineProps({
  submission: { type: Object, default: null },
  submitting: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['submit'])

const beforeFile = ref(null)
const afterFile = ref(null)
const showForm = ref(false)

const beforePreview = computed(() =>
  beforeFile.value ? URL.createObjectURL(beforeFile.value) : (props.submission?.before_url ?? null)
)

const afterPreview = computed(() =>
  afterFile.value ? URL.createObjectURL(afterFile.value) : (props.submission?.after_url ?? null)
)

const statusLabel = computed(() => {
  const map = {
    pending: 'En revisión',
    approved: 'Aprobada',
    needs_work: 'Necesita correcciones',
  }
  return map[props.submission?.status] ?? ''
})

const badgeVariant = computed(() => {
  const map = {
    pending: 'secondary',
    approved: 'accent',
    needs_work: 'blush',
  }
  return map[props.submission?.status] ?? 'secondary'
})

function handleBeforeChange(e) {
  beforeFile.value = e.target.files[0] ?? null
}

function handleAfterChange(e) {
  afterFile.value = e.target.files[0] ?? null
}

function handleSubmit() {
  if (beforeFile.value && afterFile.value) {
    emit('submit', { before: beforeFile.value, after: afterFile.value })
  }
}

defineExpose({ handleSubmit })
</script>

<template>
  <div class="space-y-4">
    <!-- Existing submission status -->
    <div v-if="submission">
      <!-- Status badge -->
      <div class="flex items-center gap-2 mb-3">
        <BaseBadge :variant="badgeVariant" pill>{{ statusLabel }}</BaseBadge>
      </div>

      <!-- needs_work: error-container feedback block -->
      <div
        v-if="submission.status === 'needs_work' && submission.feedback"
        class="bg-error-container text-on-error-container rounded-lg p-3 mb-3 font-body-md text-body-md"
      >
        {{ submission.feedback }}
      </div>

      <!-- approved/pending feedback -->
      <div
        v-else-if="submission.feedback"
        class="bg-surface-container text-on-surface-variant rounded-lg p-3 mb-3 font-body-md text-body-md"
      >
        {{ submission.feedback }}
      </div>

      <!-- Thumbnails -->
      <div class="flex gap-3 mb-3">
        <div class="flex-1">
          <p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Foto antes</p>
          <img :src="submission.before_url" alt="Foto antes" class="w-full rounded-lg object-cover aspect-video" />
        </div>
        <div class="flex-1">
          <p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Foto después</p>
          <img :src="submission.after_url" alt="Foto después" class="w-full rounded-lg object-cover aspect-video" />
        </div>
      </div>

      <!-- Toggle to show resubmit form (for non-needs_work statuses) -->
      <button
        v-if="submission.status !== 'needs_work' && !showForm"
        @click="showForm = true"
        class="font-label-md text-label-md text-primary border border-primary px-4 py-2 rounded-xl hover:bg-primary hover:text-on-primary transition-colors"
      >
        Actualizar entrega
      </button>
    </div>

    <!-- Upload form: shown when no submission OR needs_work OR showForm toggled -->
    <div v-if="!submission || submission.status === 'needs_work' || showForm">
      <!-- error alert -->
      <div
        v-if="error"
        role="alert"
        class="bg-error-container text-on-error-container rounded-lg p-3 mb-3 font-body-md text-body-md"
      >
        {{ error }}
      </div>

      <!-- Before file drop zone -->
      <div class="mb-4">
        <p class="font-label-md text-label-md text-on-surface-variant mb-2">Foto antes</p>
        <label class="block border-2 border-dashed border-outline-variant rounded-xl p-4 text-center cursor-pointer hover:border-primary transition-colors">
          <span class="material-symbols-outlined text-[32px] text-on-surface-variant mb-1 block" aria-hidden="true">add_photo_alternate</span>
          <span class="font-body-md text-body-md text-on-surface-variant">Arrastra o haz clic para subir</span>
          <input type="file" accept="image/*" class="sr-only" @change="handleBeforeChange" />
        </label>
        <img v-if="beforePreview" :src="beforePreview" alt="Vista previa - antes" class="mt-2 w-full rounded-lg object-cover max-h-40" />
      </div>

      <!-- After file drop zone -->
      <div class="mb-4">
        <p class="font-label-md text-label-md text-on-surface-variant mb-2">Foto después</p>
        <label class="block border-2 border-dashed border-outline-variant rounded-xl p-4 text-center cursor-pointer hover:border-primary transition-colors">
          <span class="material-symbols-outlined text-[32px] text-on-surface-variant mb-1 block" aria-hidden="true">add_photo_alternate</span>
          <span class="font-body-md text-body-md text-on-surface-variant">Arrastra o haz clic para subir</span>
          <input type="file" accept="image/*" class="sr-only" @change="handleAfterChange" />
        </label>
        <img v-if="afterPreview" :src="afterPreview" alt="Vista previa - después" class="mt-2 w-full rounded-lg object-cover max-h-40" />
      </div>

      <!-- Submit button -->
      <BaseButton
        data-submit
        :disabled="!beforeFile || !afterFile || submitting"
        @click="handleSubmit"
        variant="primary"
        size="sm"
      >
        <span v-if="submitting">Enviando...</span>
        <span v-else>{{ submission ? 'Actualizar entrega' : 'Enviar entrega' }}</span>
      </BaseButton>
    </div>
  </div>
</template>
