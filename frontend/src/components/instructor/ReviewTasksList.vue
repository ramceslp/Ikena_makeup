<script setup>
import BaseBadge from '../ui/BaseBadge.vue'
import BaseButton from '../ui/BaseButton.vue'

const props = defineProps({
  submissions: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['open'])

const STATUS_LABELS = {
  pending: 'En revisión',
  approved: 'Aprobada',
  needs_work: 'Necesita correcciones',
}

const STATUS_VARIANTS = {
  pending: 'secondary',
  approved: 'accent',
  needs_work: 'blush',
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Intl.DateTimeFormat('es', { dateStyle: 'medium' }).format(new Date(dateStr))
}
</script>

<template>
  <div>
    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 3" :key="i" class="bg-surface-muted rounded-xl border border-outline-variant p-4 animate-pulse space-y-2">
        <div class="h-3 bg-surface-container rounded w-1/3" />
        <div class="h-3 bg-surface-container rounded w-1/2" />
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!submissions.length" class="text-center py-12">
      <span class="material-symbols-outlined text-[48px] text-on-surface-variant mb-3 block" aria-hidden="true">assignment</span>
      <p class="font-body-md text-body-md text-on-surface-variant">No hay entregas para revisar.</p>
    </div>

    <!-- Submission cards -->
    <div v-else class="space-y-3">
      <div
        v-for="submission in submissions"
        :key="submission.id"
        class="bg-surface-muted rounded-xl border border-outline-variant p-4 flex flex-col sm:flex-row sm:items-center gap-4"
      >
        <!-- Thumbnails -->
        <div class="flex gap-2 shrink-0">
          <img :src="submission.before_url" alt="Foto antes" class="w-16 h-16 rounded-lg object-cover" />
          <img :src="submission.after_url" alt="Foto después" class="w-16 h-16 rounded-lg object-cover" />
        </div>

        <!-- Info -->
        <div class="flex-1 min-w-0">
          <p class="font-label-md text-label-md text-deep-marsala truncate">{{ submission.user?.name }}</p>
          <p class="font-body-md text-body-md text-on-surface-variant truncate">{{ submission.lesson?.title }}</p>
          <p class="font-body-sm text-body-sm text-outline mt-0.5">{{ formatDate(submission.created_at) }}</p>
        </div>

        <!-- Status + action -->
        <div class="flex items-center gap-3 shrink-0">
          <BaseBadge :variant="STATUS_VARIANTS[submission.status]" pill>
            {{ STATUS_LABELS[submission.status] }}
          </BaseBadge>
          <BaseButton variant="outline" size="sm" @click="emit('open', submission)">
            Revisar
          </BaseButton>
        </div>
      </div>
    </div>
  </div>
</template>
