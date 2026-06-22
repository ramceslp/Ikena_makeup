<script setup>
import { formatCertificateDate } from '../../../utils/formatCertificateDate.js'

defineProps({
  certificate: { type: Object, required: true },
  settings: { type: Object, required: true },
})
</script>

<template>
  <!-- Variant 4 — "Modern Band": asymmetric color-block. A solid deep-marsala
       side band (logo + business name, reversed) and a light content area.
       Contemporary, strong contrast. -->
  <div class="flex flex-col sm:flex-row rounded-2xl overflow-hidden border border-outline-variant min-h-[420px]">

    <!-- Left band -->
    <div class="bg-deep-marsala text-on-primary sm:w-1/4 p-6 flex flex-col items-center sm:items-start gap-6">
      <img
        v-if="settings.logo_url"
        data-cert-logo
        :src="settings.logo_url"
        :alt="settings.business_name"
        class="h-14 w-auto object-contain brightness-0 invert"
      />
      <span
        v-else
        data-cert-logo-fallback
        class="material-symbols-outlined"
        style="font-size: 40px;"
        aria-hidden="true"
      >workspace_premium</span>
      <p class="font-label-md text-label-md tracking-[0.25em] uppercase leading-relaxed">
        {{ settings.business_name }}
      </p>
      <p class="font-label-sm text-label-sm opacity-80 mt-auto">
        {{ certificate.code }}
      </p>
    </div>

    <!-- Right content -->
    <div class="sm:w-3/4 bg-background p-10 flex flex-col justify-center gap-4">
      <h1 class="font-display-lg text-display-lg text-deep-marsala leading-tight">
        {{ settings.title }}
      </h1>
      <p class="font-body-md text-body-md text-on-surface-variant">{{ settings.award_line }}</p>
      <p class="font-headline-lg text-headline-lg text-deep-marsala font-semibold">
        {{ certificate.student_name }}
      </p>
      <p class="font-body-md text-body-md text-on-surface-variant">
        {{ settings.achievement_line }}
        <span class="font-title-md text-title-md text-on-surface font-semibold italic">
          "{{ certificate.course_title }}"
        </span>
      </p>

      <div class="flex items-end justify-between gap-4 pt-6 mt-2 border-t border-outline-variant">
        <div>
          <p class="font-body-md text-body-md text-on-surface font-medium">{{ settings.signer_name }}</p>
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
    </div>

  </div>
</template>
