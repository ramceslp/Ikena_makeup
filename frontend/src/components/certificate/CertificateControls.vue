<script setup>
import { RouterLink } from 'vue-router'
import BaseButton from '../ui/BaseButton.vue'

defineEmits(['print'])
</script>

<template>
  <!-- no-print: this control bar is hidden when the user prints/saves as PDF -->
  <div class="no-print flex flex-col sm:flex-row items-center justify-center gap-4 mt-8">
    <BaseButton variant="primary" size="md" @click="$emit('print')">
      <span class="material-symbols-outlined text-[20px]" aria-hidden="true">print</span>
      Imprimir / Guardar PDF
    </BaseButton>

    <RouterLink
      to="/my-courses"
      class="inline-flex items-center gap-2 border-2 border-primary text-primary hover:bg-primary hover:text-on-primary px-8 py-4 rounded-xl font-title-md transition-all active:scale-95"
    >
      <span class="material-symbols-outlined text-[20px]" aria-hidden="true">arrow_back</span>
      Volver a Mis Cursos
    </RouterLink>
  </div>
</template>
