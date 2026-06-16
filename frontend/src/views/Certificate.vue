<script setup>
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useCoursesStore } from '../stores/courses.js'
import CertificateCanvas from '../components/certificate/CertificateCanvas.vue'
import CertificateControls from '../components/certificate/CertificateControls.vue'

const route = useRoute()
const coursesStore = useCoursesStore()

function handlePrint() {
  window.print()
}

onMounted(async () => {
  try {
    await coursesStore.fetchCertificate(route.params.slug)
  } catch {
    // error state is handled via coursesStore.certificateError in the template
  }
})
</script>

<template>
  <section class="py-16 bg-background min-h-screen">
    <div class="max-w-container-max mx-auto px-gutter">

      <!-- Loading state -->
      <div
        v-if="coursesStore.certificateLoading"
        class="flex flex-col items-center justify-center py-24 gap-4"
      >
        <span
          class="material-symbols-outlined text-apricot-glow animate-spin"
          style="font-size: 48px;"
          aria-hidden="true"
        >progress_activity</span>
        <p class="font-body-lg text-body-lg text-on-surface-variant">
          Generando tu certificado...
        </p>
      </div>

      <!-- Error / not eligible state (e.g. 403) -->
      <div
        v-else-if="coursesStore.certificateError"
        class="flex flex-col items-center justify-center py-24 gap-6"
      >
        <div
          class="bg-surface-muted border border-blush-canvas/30 rounded-2xl p-10 text-center max-w-lg w-full"
        >
          <span
            class="material-symbols-outlined text-apricot-glow mb-4"
            style="font-size: 48px;"
            aria-hidden="true"
          >lock</span>
          <h2 class="font-title-md text-title-md text-deep-marsala font-semibold mb-3">
            Certificado no disponible
          </h2>
          <p class="font-body-md text-body-md text-on-surface-variant mb-6">
            {{ coursesStore.certificateError }}
          </p>
          <RouterLink
            to="/my-courses"
            class="inline-flex items-center gap-2 border-2 border-primary text-primary hover:bg-primary hover:text-on-primary px-6 py-3 rounded-xl font-title-md transition-all active:scale-95"
          >
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
            Volver a Mis Cursos
          </RouterLink>
        </div>
      </div>

      <!-- Success state -->
      <div v-else-if="coursesStore.certificate">
        <CertificateCanvas :certificate="coursesStore.certificate" />
        <CertificateControls @print="handlePrint" />
      </div>

    </div>
  </section>
</template>
