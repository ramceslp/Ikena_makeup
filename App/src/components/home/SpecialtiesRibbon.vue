<script setup>
/**
 * Ported from frontend/src/components/home/SpecialtiesRibbon.vue. Only the type
 * scale differs: the app has no desktop breakpoint, so the serif row stays at
 * the title tier instead of stepping up to the headline tier.
 *
 * "Cinta" (Phase 4): a marquee band of what the academy teaches, sitting
 * between the news grid and the courses section. It breaks the dashboard's
 * "block, block, block" rhythm and gives the scroll some brand temperature
 * without asking the reader for anything.
 *
 * The copy is static on purpose, and that matters more here than on the web:
 * Home.vue already gates six sections behind a connectivity probe, and a
 * decorative band must not add a seventh fetch that can leave a hole in the
 * layout. These are names of makeup disciplines and techniques, not claims
 * about the academy (no certifications, no availability, no promises).
 *
 * Each list is rendered twice: the marquee travels exactly one copy's width,
 * so the second one lands where the first started and the restart is
 * invisible. The duplicate is aria-hidden so a screen reader reads the terms
 * once. Motion and layout live in style.css under "Cinta".
 */

const specialties = [
  'Novias',
  'Editorial',
  'Efectos especiales',
  'Piel madura',
  'Cejas',
  'Automaquillaje',
]

const techniques = [
  'Contorno',
  'Delineado gráfico',
  'Piel luminosa',
  'Labial mate',
  'Iluminador',
  'Rubor difuminado',
]
</script>

<template>
  <div
    data-specialties-ribbon
    class="cinta overflow-clip select-none bg-primary border-y border-primary-container py-5"
  >
    <div data-ribbon-track class="cinta-track font-headline-lg text-title-md text-on-primary">
      <ul data-ribbon-group class="cinta-group">
        <li v-for="term in specialties" :key="term">{{ term }}</li>
      </ul>
      <ul data-ribbon-group class="cinta-group" aria-hidden="true">
        <li v-for="term in specialties" :key="term">{{ term }}</li>
      </ul>
    </div>

    <div
      data-ribbon-track
      class="cinta-track cinta-track--reverse font-label-sm text-label-sm uppercase text-blush-canvas mt-2"
    >
      <ul data-ribbon-group class="cinta-group">
        <li v-for="term in techniques" :key="term">{{ term }}</li>
      </ul>
      <ul data-ribbon-group class="cinta-group" aria-hidden="true">
        <li v-for="term in techniques" :key="term">{{ term }}</li>
      </ul>
    </div>
  </div>
</template>
