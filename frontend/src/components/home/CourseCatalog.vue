<script setup>
import CourseCard from '../CourseCard.vue'
import BaseButton from '../ui/BaseButton.vue'

defineProps({
  courses: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  error: { type: [String, null], default: null },
  meta: { type: [Object, null], default: null },
})

defineEmits(['retry', 'page-change'])
</script>

<template>
  <section class="py-20 bg-background">
    <div class="max-w-container-max mx-auto px-gutter">
      <!-- Loading skeleton -->
      <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12">
        <div
          v-for="i in 6"
          :key="i"
          class="bg-surface-muted rounded-2xl border border-blush-canvas/30 overflow-hidden animate-pulse"
        >
          <div class="aspect-[16/9] bg-surface-container" />
          <div class="p-6 space-y-3">
            <div class="h-4 bg-surface-container rounded w-3/4" />
            <div class="h-3 bg-surface-container rounded w-1/2" />
            <div class="h-3 bg-surface-container rounded w-full" />
          </div>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="text-center py-16">
        <span class="material-symbols-outlined text-error text-5xl mb-4" aria-hidden="true">error</span>
        <p class="font-body-lg text-body-lg text-on-surface">{{ error }}</p>
        <button
          @click="$emit('retry')"
          class="mt-4 text-primary hover:underline font-label-md text-label-md"
        >
          Intentar de nuevo
        </button>
      </div>

      <!-- Empty -->
      <div v-else-if="!courses.length" class="text-center py-16">
        <span class="material-symbols-outlined text-blush-canvas text-5xl mb-4" aria-hidden="true">search_off</span>
        <p class="font-body-lg text-body-lg text-on-surface-variant">No se encontraron cursos</p>
        <p class="font-body-md text-body-md text-outline mt-1">Prueba con otros filtros de búsqueda</p>
      </div>

      <!-- Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12">
        <CourseCard v-for="(course, i) in courses" :key="course.id" v-reveal="i % 3" :course="course" />
      </div>

      <!-- Pagination -->
      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-center gap-4 mt-16"
      >
        <BaseButton
          variant="outline"
          size="sm"
          :disabled="meta.current_page <= 1"
          @click="$emit('page-change', meta.current_page - 1)"
        >
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_left</span>
          Anterior
        </BaseButton>

        <span class="font-body-md text-body-md text-on-surface-variant">
          Página {{ meta.current_page }} de {{ meta.last_page }}
        </span>

        <BaseButton
          variant="outline"
          size="sm"
          :disabled="meta.current_page >= meta.last_page"
          @click="$emit('page-change', meta.current_page + 1)"
        >
          Siguiente
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_right</span>
        </BaseButton>
      </div>
    </div>
  </section>
</template>
