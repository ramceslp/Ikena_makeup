<script setup>
import BaseButton from '../ui/BaseButton.vue'
import heroImage from '../../assets/hero.png'

defineEmits(['explore'])
</script>

<template>
  <section class="relative flex items-center overflow-hidden bg-surface-muted min-h-[600px]">
    <!-- Background image + gradient veil -->
    <div class="absolute inset-0 z-0">
      <div class="absolute inset-0 bg-gradient-to-r from-background via-background/40 to-transparent z-10" />
      <img
        :src="heroImage"
        alt=""
        aria-hidden="true"
        class="w-full h-full object-cover object-center scale-105"
      />
    </div>

    <div class="relative z-20 w-full max-w-container-max mx-auto px-gutter py-24">
      <div class="max-w-2xl space-y-8">
        <div class="inline-flex items-center gap-2 px-3 py-1 bg-blush-canvas/20 rounded-full border border-blush-canvas/30">
          <span class="font-label-sm text-label-sm text-primary uppercase tracking-widest">
            Inscripciones Abiertas
          </span>
        </div>

        <h1 class="font-display-lg text-display-lg text-primary leading-tight">
          Transforma tu Pasión en una
          <span class="text-deep-marsala italic">Carrera de Arte</span>
        </h1>

        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-lg">
          Aprende las técnicas más vanguardistas de la industria con instructores de élite.
          Desde el maquillaje editorial hasta la elegancia nupcial.
        </p>

        <div class="flex flex-wrap gap-4 pt-4">
          <BaseButton variant="primary" @click="$emit('explore')">
            Explorar Catálogo
          </BaseButton>
        </div>
      </div>
    </div>
  </section>
</template>
