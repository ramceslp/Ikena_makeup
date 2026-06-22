<script setup>
import { computed } from 'vue'
import Variant1 from './variants/Variant1.vue'
import Variant2 from './variants/Variant2.vue'

const props = defineProps({
  certificate: {
    type: Object,
    required: true,
    // shape: { code, issued_at, student_name, course_title, instructor_name }
  },
  settings: {
    type: Object,
    required: true,
    // shape: { business_name, title, award_line, achievement_line,
    //          signer_name, signer_role, design_variant, logo_url }
  },
})

// Failsafe branding, mirroring the backend model defaults. The public endpoint
// always returns a defaults-filled payload, so this only kicks in on a transport
// failure — guaranteeing the certificate never renders blank (design §6).
const CLIENT_DEFAULTS = {
  business_name: 'Ikena Makeup Academy',
  title: 'Certificado de Profesionalización',
  award_line: 'Se otorga el presente certificado a',
  achievement_line: 'por completar satisfactoriamente el curso',
  signer_name: 'Ikena Makeup Academy',
  signer_role: null,
  design_variant: 1,
  logo_url: null,
}

const safeSettings = computed(() => props.settings ?? CLIENT_DEFAULTS)

// Only the variants shipped so far are registered; 3-5 arrive in PR4.
// Anything unknown / out-of-range / missing falls back to Variant1 so the
// certificate never fails to render.
const variantMap = {
  1: Variant1,
  2: Variant2,
}

const activeVariant = computed(() => {
  const idx = Number(safeSettings.value.design_variant)
  return variantMap[idx] ?? Variant1
})
</script>

<template>
  <!-- Printable certificate canvas — never marked no-print; must appear in print -->
  <div
    class="w-full max-w-3xl mx-auto bg-background"
    role="document"
    :aria-label="safeSettings.title"
  >
    <component :is="activeVariant" :certificate="certificate" :settings="safeSettings" />
  </div>
</template>
