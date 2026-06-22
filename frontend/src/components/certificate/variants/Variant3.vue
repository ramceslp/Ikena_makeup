<script setup>
import { formatCertificateDate } from '../../../utils/formatCertificateDate.js'

defineProps({
  certificate: { type: Object, required: true },
  settings: { type: Object, required: true },
})
</script>

<template>
  <!-- Variant 3 — "Ornate Seal": tinted panel in a deep-marsala frame, corner
       flourishes, a prominent circular seal overlapping the body, signer drawn
       on a signature rule. Ceremonial / formal feel. -->
  <div class="border-2 border-deep-marsala rounded-2xl p-2">
    <div class="relative bg-blush-canvas/30 rounded-xl px-10 py-12 text-center overflow-hidden">

      <!-- Corner flourishes -->
      <span class="absolute top-3 left-4 text-apricot-glow text-xl" aria-hidden="true">✦</span>
      <span class="absolute top-3 right-4 text-apricot-glow text-xl" aria-hidden="true">✦</span>
      <span class="absolute bottom-3 left-4 text-apricot-glow text-xl" aria-hidden="true">✦</span>
      <span class="absolute bottom-3 right-4 text-apricot-glow text-xl" aria-hidden="true">✦</span>

      <!-- Logo centered top -->
      <div class="flex justify-center mb-3">
        <img
          v-if="settings.logo_url"
          data-cert-logo
          :src="settings.logo_url"
          :alt="settings.business_name"
          class="h-16 w-auto object-contain"
        />
        <span
          v-else
          data-cert-logo-fallback
          class="material-symbols-outlined text-apricot-glow"
          style="font-size: 44px;"
          aria-hidden="true"
        >workspace_premium</span>
      </div>

      <p class="font-label-md text-label-md text-deep-marsala tracking-[0.25em] uppercase">
        {{ settings.business_name }}
      </p>

      <h1 class="font-display-lg text-display-lg text-deep-marsala leading-tight mt-3">
        {{ settings.title }}
      </h1>

      <div class="mt-6 space-y-2">
        <p class="font-body-md text-body-md text-on-surface-variant">{{ settings.award_line }}</p>
        <p class="font-headline-lg text-headline-lg text-deep-marsala font-semibold">
          {{ certificate.student_name }}
        </p>
        <p class="font-body-md text-body-md text-on-surface-variant">{{ settings.achievement_line }}</p>
        <p class="font-title-md text-title-md text-on-surface font-semibold italic">
          "{{ certificate.course_title }}"
        </p>
      </div>

      <!-- Prominent wax-style seal, overlapping the lower-right body -->
      <div
        class="w-24 h-24 rounded-full border-4 border-apricot-glow bg-surface-container flex flex-col items-center justify-center mx-auto mt-8 shadow-sm"
        aria-hidden="true"
      >
        <span class="material-symbols-outlined text-apricot-glow text-3xl">verified</span>
      </div>

      <!-- Signer on a signature rule -->
      <div class="mt-8 inline-flex flex-col items-center">
        <div class="h-px w-56 bg-deep-marsala/60" />
        <p class="font-body-md text-body-md text-on-surface font-medium mt-1">
          {{ settings.signer_name }}
        </p>
        <p
          v-if="settings.signer_role"
          class="font-label-sm text-label-sm text-on-surface-variant"
        >
          {{ settings.signer_role }}
        </p>
      </div>

      <!-- Date + code stacked bottom-center -->
      <div class="mt-6 space-y-1">
        <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest">
          {{ formatCertificateDate(certificate.issued_at) }}
        </p>
        <p class="font-label-sm text-label-sm text-on-surface-variant">
          Código de verificación:
          <span class="text-deep-marsala font-semibold tracking-wider">{{ certificate.code }}</span>
        </p>
      </div>

    </div>
  </div>
</template>
