<script setup>
import { formatCertificateDate } from '../../../utils/formatCertificateDate.js'

defineProps({
  certificate: { type: Object, required: true },
  settings: { type: Object, required: true },
})
</script>

<template>
  <!-- Variant 5 — "Elegant Portrait": symmetric, top-heavy crest with a ringed
       medallion logo, serif-weighted title, name on a gradient rule, refined
       two-column footer split by a hairline, code in a centered pill. -->
  <div class="px-10 py-12 text-center space-y-8">

    <!-- Crest: medallion logo + business name -->
    <div class="flex flex-col items-center gap-3">
      <div class="w-20 h-20 rounded-full border border-apricot-glow flex items-center justify-center">
        <img
          v-if="settings.logo_url"
          data-cert-logo
          :src="settings.logo_url"
          :alt="settings.business_name"
          class="h-12 w-auto object-contain"
        />
        <span
          v-else
          data-cert-logo-fallback
          class="material-symbols-outlined text-apricot-glow"
          style="font-size: 36px;"
          aria-hidden="true"
        >workspace_premium</span>
      </div>
      <p class="font-label-md text-label-md text-on-surface-variant tracking-[0.35em] uppercase">
        {{ settings.business_name }}
      </p>
    </div>

    <!-- Serif-weighted title -->
    <h1 class="font-display-lg text-display-lg text-deep-marsala leading-tight" style="font-family: serif;">
      {{ settings.title }}
    </h1>

    <div class="space-y-3">
      <p class="font-body-md text-body-md text-on-surface-variant">{{ settings.award_line }}</p>

      <!-- Name on a subtle gradient rule -->
      <div class="inline-block">
        <p class="font-headline-lg text-headline-lg text-deep-marsala font-semibold">
          {{ certificate.student_name }}
        </p>
        <div class="h-0.5 w-full bg-gradient-to-r from-transparent via-apricot-glow to-transparent mt-1" />
      </div>

      <p class="font-body-md text-body-md text-on-surface-variant">{{ settings.achievement_line }}</p>
      <p class="font-title-md text-title-md text-on-surface font-semibold italic">
        "{{ certificate.course_title }}"
      </p>
    </div>

    <!-- Two-column footer split by a vertical hairline -->
    <div class="flex items-stretch justify-center gap-8 pt-4">
      <div class="text-right flex flex-col justify-center">
        <p class="font-body-md text-body-md text-on-surface font-medium">{{ settings.signer_name }}</p>
        <p
          v-if="settings.signer_role"
          class="font-label-sm text-label-sm text-on-surface-variant"
        >
          {{ settings.signer_role }}
        </p>
      </div>
      <div class="w-px bg-outline-variant" aria-hidden="true" />
      <div class="text-left flex flex-col justify-center">
        <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest mb-0.5">
          Fecha
        </p>
        <p class="font-body-md text-body-md text-on-surface font-medium">
          {{ formatCertificateDate(certificate.issued_at) }}
        </p>
      </div>
    </div>

    <!-- Code pill -->
    <div class="flex justify-center">
      <span class="inline-flex items-center gap-1 border border-outline-variant rounded-full px-4 py-1 font-label-sm text-label-sm text-on-surface-variant">
        Verificación:
        <span class="text-deep-marsala font-semibold tracking-wider">{{ certificate.code }}</span>
      </span>
    </div>

  </div>
</template>
