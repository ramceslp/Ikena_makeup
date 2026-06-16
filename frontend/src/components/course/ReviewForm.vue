<script setup>
// Presentational: review create/edit form.
// Container (CourseDetail.vue) owns store wiring and passes down props.
import { ref, watch, onMounted } from 'vue'
import StarRating from '../ui/StarRating.vue'
import BaseButton from '../ui/BaseButton.vue'

const props = defineProps({
  myReview: { type: Object, default: null },
  submitting: { type: Boolean, default: false },
  deleting: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['submit', 'delete'])

// ── Local state seeded from myReview ─────────────────────────────────────────
const localRating = ref(0)
const localBody = ref('')

function seedFromReview(review) {
  localRating.value = review?.rating ?? 0
  localBody.value = review?.body ?? ''
}

onMounted(() => seedFromReview(props.myReview))

watch(
  () => props.myReview,
  (review) => seedFromReview(review),
)

// ── Submit guard ──────────────────────────────────────────────────────────────
function handleSubmit() {
  if (localRating.value < 1) return
  emit('submit', {
    rating: localRating.value,
    body: localBody.value?.trim() || null,
  })
}
</script>

<template>
  <div class="bg-surface-container rounded-2xl border border-outline-variant p-6 space-y-4">
    <h3 class="font-title-md text-title-md text-deep-marsala">
      {{ myReview ? 'Tu valoración' : 'Deja tu valoración' }}
    </h3>

    <!-- Star selector -->
    <div class="flex items-center gap-2">
      <StarRating
        v-model="localRating"
        :editable="true"
        size="lg"
      />
      <span
        v-if="localRating > 0"
        class="font-label-md text-label-md text-on-surface-variant"
      >{{ localRating }} / 5</span>
    </div>

    <!-- Text area -->
    <textarea
      v-model="localBody"
      rows="3"
      maxlength="2000"
      placeholder="Comparte tu experiencia (opcional)"
      class="w-full rounded-xl border border-outline-variant bg-surface-container p-3 font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant resize-none focus:outline-none focus:border-primary transition-colors"
    />

    <!-- Error alert -->
    <div
      v-if="error"
      role="alert"
      class="flex items-center gap-2 bg-error-container text-on-error-container rounded-xl px-4 py-3 font-body-md text-body-md"
    >
      <span class="material-symbols-outlined text-[18px] shrink-0" aria-hidden="true">error</span>
      {{ error }}
    </div>

    <!-- Actions row -->
    <div class="flex flex-wrap items-center gap-3">
      <!-- Submit -->
      <BaseButton
        variant="primary"
        size="sm"
        :disabled="localRating < 1 || submitting"
        @click="handleSubmit"
      >
        <svg v-if="submitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        {{ submitting ? 'Enviando...' : myReview ? 'Actualizar valoración' : 'Publicar valoración' }}
      </BaseButton>

      <!-- Delete (only when editing an existing review) -->
      <BaseButton
        v-if="myReview"
        variant="outline"
        size="sm"
        :disabled="deleting"
        @click="emit('delete')"
      >
        {{ deleting ? 'Eliminando...' : 'Eliminar' }}
      </BaseButton>
    </div>
  </div>
</template>
