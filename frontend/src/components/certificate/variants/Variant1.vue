<script setup>
import { formatCertificateDate } from '../../../utils/formatCertificateDate.js'

defineProps({
  certificate: { type: Object, required: true },
  settings: { type: Object, required: true },
})
</script>

<template>
  <!-- Variant 1 — "Classic Frame": centered, double border, footer seal row.
       Faithful port of the original hardcoded canvas, now data-driven. -->
  <div class="border border-deep-marsala p-3 rounded-2xl">
    <div class="border-[3px] border-apricot-glow rounded-xl p-10 text-center space-y-6">

      <!-- Logo / header mark -->
      <div class="flex justify-center mb-2">
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
          style="font-size: 48px;"
          aria-hidden="true"
        >workspace_premium</span>
      </div>

      <!-- Business name -->
      <p class="font-label-md text-label-md text-on-surface-variant tracking-widest uppercase">
        {{ settings.business_name }}
      </p>

      <!-- Title -->
      <h1 class="font-display-lg text-display-lg text-deep-marsala leading-tight">
        {{ settings.title }}
      </h1>

      <!-- Decorative divider -->
      <div class="flex items-center gap-4 justify-center">
        <div class="h-px flex-1 bg-outline-variant" />
        <span class="text-apricot-glow text-lg">✦</span>
        <div class="h-px flex-1 bg-outline-variant" />
      </div>

      <!-- Award body -->
      <div class="space-y-2">
        <p class="font-body-md text-body-md text-on-surface-variant">
          {{ settings.award_line }}
        </p>

        <p class="font-headline-lg text-headline-lg text-deep-marsala font-semibold mt-1">
          {{ certificate.student_name }}
        </p>

        <p class="font-body-md text-body-md text-on-surface-variant mt-3">
          {{ settings.achievement_line }}
        </p>

        <p class="font-title-md text-title-md text-on-surface font-semibold mt-1 italic">
          "{{ certificate.course_title }}"
        </p>
      </div>

      <!-- Decorative divider -->
      <div class="flex items-center gap-4 justify-center">
        <div class="h-px flex-1 bg-outline-variant" />
        <span class="text-apricot-glow text-lg">✦</span>
        <div class="h-px flex-1 bg-outline-variant" />
      </div>

      <!-- Footer info row: signer | seal | date -->
      <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
        <div class="text-center sm:text-left">
          <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest mb-1">
            Firma
          </p>
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

        <div
          class="w-16 h-16 rounded-full border-2 border-apricot-glow bg-surface-container flex items-center justify-center flex-shrink-0"
          aria-hidden="true"
        >
          <span class="material-symbols-outlined text-apricot-glow text-2xl">verified</span>
        </div>

        <div class="text-center sm:text-right">
          <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest mb-1">
            Fecha de emisión
          </p>
          <p class="font-body-md text-body-md text-on-surface font-medium">
            {{ formatCertificateDate(certificate.issued_at) }}
          </p>
        </div>
      </div>

      <!-- Verification code footer -->
      <div class="mt-4 pt-4 border-t border-outline-variant">
        <p class="font-label-sm text-label-sm text-on-surface-variant">
          Código de verificación:
          <span class="text-deep-marsala font-semibold tracking-wider ml-1">{{ certificate.code }}</span>
        </p>
      </div>

    </div>
  </div>
</template>
