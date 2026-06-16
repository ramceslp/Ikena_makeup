<script setup>
const props = defineProps({
  certificate: {
    type: Object,
    required: true,
    // expected shape: { code, issued_at, student_name, course_title, instructor_name }
  },
})

function formatDate(iso) {
  return new Intl.DateTimeFormat('es', { dateStyle: 'long' }).format(new Date(iso))
}
</script>

<template>
  <!-- Printable certificate canvas — never marked no-print; must appear in print -->
  <div
    class="w-full max-w-3xl mx-auto bg-background"
    role="document"
    aria-label="Certificado de Profesionalización"
  >
    <!-- Outer decorative frame -->
    <div class="border border-deep-marsala p-3 rounded-2xl">
      <!-- Inner accent border -->
      <div class="border-[3px] border-apricot-glow rounded-xl p-10 text-center space-y-6">

        <!-- Header mark -->
        <div class="flex justify-center mb-2">
          <span
            class="material-symbols-outlined text-apricot-glow"
            style="font-size: 48px;"
            aria-hidden="true"
          >workspace_premium</span>
        </div>

        <!-- Academy name -->
        <p class="font-label-md text-label-md text-on-surface-variant tracking-widest uppercase">
          Ikena Makeup Academy
        </p>

        <!-- Main heading -->
        <h1 class="font-display-lg text-display-lg text-deep-marsala leading-tight">
          Certificado de Profesionalización
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
            Se otorga el presente certificado a
          </p>

          <!-- Student name — largest, most prominent element -->
          <p class="font-headline-lg text-headline-lg text-deep-marsala font-semibold mt-1">
            {{ certificate.student_name }}
          </p>

          <p class="font-body-md text-body-md text-on-surface-variant mt-3">
            por completar satisfactoriamente el curso
          </p>

          <!-- Course title -->
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

        <!-- Footer info row -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
          <!-- Instructor -->
          <div class="text-center sm:text-left">
            <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest mb-1">
              Instructor
            </p>
            <p class="font-body-md text-body-md text-on-surface font-medium">
              {{ certificate.instructor_name }}
            </p>
          </div>

          <!-- Decorative seal placeholder -->
          <div
            class="w-16 h-16 rounded-full border-2 border-apricot-glow bg-surface-container flex items-center justify-center flex-shrink-0"
            aria-hidden="true"
          >
            <span class="material-symbols-outlined text-apricot-glow text-2xl">verified</span>
          </div>

          <!-- Issue date -->
          <div class="text-center sm:text-right">
            <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest mb-1">
              Fecha de emisión
            </p>
            <p class="font-body-md text-body-md text-on-surface font-medium">
              {{ formatDate(certificate.issued_at) }}
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
  </div>
</template>
