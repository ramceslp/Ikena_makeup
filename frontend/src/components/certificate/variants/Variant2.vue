<script setup>
import { formatCertificateDate } from '../../../utils/formatCertificateDate.js'

defineProps({
  certificate: { type: Object, required: true },
  settings: { type: Object, required: true },
})
</script>

<template>
  <!-- Variant 2 — "Minimal Line": borderless, left-aligned editorial layout,
       no seal, generous whitespace. Structurally distinct from the framed V1. -->
  <div class="px-10 py-12 space-y-10">

    <!-- Masthead: small left-aligned logo + business name over a thin rule -->
    <div class="space-y-4">
      <div class="flex items-center gap-3">
        <img
          v-if="settings.logo_url"
          data-cert-logo
          :src="settings.logo_url"
          :alt="settings.business_name"
          class="h-10 w-auto object-contain"
        />
        <span
          v-else
          data-cert-logo-fallback
          class="material-symbols-outlined text-apricot-glow"
          style="font-size: 32px;"
          aria-hidden="true"
        >workspace_premium</span>
        <p class="font-label-md text-label-md text-on-surface-variant tracking-[0.2em] uppercase">
          {{ settings.business_name }}
        </p>
      </div>
      <div class="h-px w-full bg-apricot-glow" />
    </div>

    <!-- Title + hero name + course -->
    <div class="space-y-4">
      <h1 class="font-display-lg text-display-lg text-deep-marsala leading-tight">
        {{ settings.title }}
      </h1>
      <p class="font-body-md text-body-md text-on-surface-variant">
        {{ settings.award_line }}
      </p>
      <p class="font-headline-lg text-headline-lg text-deep-marsala font-semibold">
        {{ certificate.student_name }}
      </p>
      <p class="font-body-md text-body-md text-on-surface-variant">
        {{ settings.achievement_line }}
        <span class="font-title-md text-title-md text-on-surface font-semibold italic">
          "{{ certificate.course_title }}"
        </span>
      </p>
    </div>

    <!-- Thin divider -->
    <div class="h-px w-full bg-outline-variant" />

    <!-- Footer: signer (left) + date (right) -->
    <div class="flex flex-col sm:flex-row items-start sm:items-end justify-between gap-4">
      <div>
        <p class="font-body-md text-body-md text-on-surface font-medium">
          {{ settings.signer_name }}
        </p>
        <p
          v-if="settings.signer_role"
          class="font-label-sm text-label-sm text-on-surface-variant mt-0.5"
        >
          {{ settings.signer_role }}
        </p>
      </div>
      <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest">
        {{ formatCertificateDate(certificate.issued_at) }}
      </p>
    </div>

    <!-- Faint verification code line -->
    <p class="font-label-sm text-label-sm text-on-surface-variant tracking-wider">
      Código de verificación: <span class="font-mono text-deep-marsala">{{ certificate.code }}</span>
    </p>
  </div>
</template>
