<script setup>
import { ref, computed, watch } from 'vue'
import BaseButton from '../ui/BaseButton.vue'
import CertificateCanvas from '../certificate/CertificateCanvas.vue'

const props = defineProps({
  settings: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['submit'])

const business_name = ref('')
const title = ref('')
const award_line = ref('')
const achievement_line = ref('')
const signer_name = ref('')
const signer_role = ref('')
const design_variant = ref(1)
const selectedLogo = ref(null)

const variants = [
  { value: 1, label: '1 · Classic Frame' },
  { value: 2, label: '2 · Minimal Line' },
  { value: 3, label: '3 · Ornate Seal' },
  { value: 4, label: '4 · Modern Band' },
  { value: 5, label: '5 · Elegant Portrait' },
]

function hydrate(s) {
  if (!s) return
  business_name.value = s.business_name ?? ''
  title.value = s.title ?? ''
  award_line.value = s.award_line ?? ''
  achievement_line.value = s.achievement_line ?? ''
  signer_name.value = s.signer_name ?? ''
  signer_role.value = s.signer_role ?? ''
  design_variant.value = s.design_variant ?? 1
}

hydrate(props.settings)
watch(() => props.settings, hydrate)

function handleLogoChange(event) {
  selectedLogo.value = event.target.files?.[0] ?? null
}

// Live preview — mirrors the current form values through the real canvas so the
// admin sees the chosen variant + copy before saving. Logo uses the stored URL
// (the new file only appears in the preview after a successful save).
const sampleCertificate = {
  code: 'IKENA-0000-DEMO',
  issued_at: new Date().toISOString(),
  student_name: 'Nombre del Alumno',
  course_title: 'Nombre del Curso',
  instructor_name: '',
}

const previewSettings = computed(() => ({
  business_name: business_name.value,
  title: title.value,
  award_line: award_line.value,
  achievement_line: achievement_line.value,
  signer_name: signer_name.value,
  signer_role: signer_role.value,
  design_variant: design_variant.value,
  logo_url: props.settings?.logo_url ?? null,
}))

function handleSubmit() {
  const fd = new FormData()
  fd.append('business_name', business_name.value)
  fd.append('title', title.value)
  fd.append('award_line', award_line.value)
  fd.append('achievement_line', achievement_line.value)
  fd.append('signer_name', signer_name.value)
  fd.append('signer_role', signer_role.value ?? '')
  fd.append('design_variant', design_variant.value)
  if (selectedLogo.value) fd.append('logo', selectedLogo.value)
  emit('submit', fd)
}

const inputClass =
  'px-4 py-2 bg-surface-container-low border border-blush-canvas/30 rounded-xl font-body-md text-body-md focus:ring-1 focus:ring-primary outline-none'
</script>

<template>
  <div class="flex flex-col gap-8">
    <form @submit.prevent="handleSubmit" class="flex flex-col gap-6">
    <!-- Business name -->
    <div class="flex flex-col gap-1">
      <label for="business_name" class="font-label-md text-label-md text-on-surface-variant">Nombre del negocio *</label>
      <input id="business_name" v-model="business_name" type="text" :class="inputClass" />
    </div>

    <!-- Copy fields -->
    <div class="flex flex-col gap-1">
      <label for="title" class="font-label-md text-label-md text-on-surface-variant">Título del certificado *</label>
      <input id="title" v-model="title" type="text" :class="inputClass" />
    </div>
    <div class="flex flex-col gap-1">
      <label for="award_line" class="font-label-md text-label-md text-on-surface-variant">Línea de otorgamiento *</label>
      <input id="award_line" v-model="award_line" type="text" :class="inputClass" />
    </div>
    <div class="flex flex-col gap-1">
      <label for="achievement_line" class="font-label-md text-label-md text-on-surface-variant">Línea de logro *</label>
      <input id="achievement_line" v-model="achievement_line" type="text" :class="inputClass" />
    </div>

    <!-- Signer -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="flex flex-col gap-1">
        <label for="signer_name" class="font-label-md text-label-md text-on-surface-variant">Firmante *</label>
        <input id="signer_name" v-model="signer_name" type="text" :class="inputClass" />
      </div>
      <div class="flex flex-col gap-1">
        <label for="signer_role" class="font-label-md text-label-md text-on-surface-variant">Cargo del firmante</label>
        <input id="signer_role" v-model="signer_role" type="text" placeholder="Opcional" :class="inputClass" />
      </div>
    </div>

    <!-- Design variant -->
    <div class="flex flex-col gap-1">
      <label for="design_variant" class="font-label-md text-label-md text-on-surface-variant">Diseño</label>
      <select id="design_variant" v-model.number="design_variant" :class="inputClass">
        <option v-for="v in variants" :key="v.value" :value="v.value">{{ v.label }}</option>
      </select>
    </div>

    <!-- Logo -->
    <div class="flex flex-col gap-2">
      <span class="font-label-md text-label-md text-on-surface-variant">Logo</span>
      <img
        v-if="settings?.logo_url"
        data-logo-preview
        :src="settings.logo_url"
        alt="Logo actual"
        class="h-16 w-auto object-contain rounded-lg border border-blush-canvas/30 bg-surface-container p-2"
      />
      <input
        id="logo"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        @change="handleLogoChange"
        class="font-body-md text-body-md text-on-surface-variant"
      />
    </div>

    <!-- Submit -->
    <div class="flex justify-end">
      <BaseButton type="submit" variant="primary" :disabled="loading">
        {{ loading ? 'Guardando…' : 'Guardar configuración' }}
      </BaseButton>
    </div>
    </form>

    <!-- Live preview -->
    <section class="border-t border-blush-canvas/20 pt-6">
      <h2 class="font-title-md text-title-md text-deep-marsala mb-1">Vista previa</h2>
      <p class="font-body-sm text-body-sm text-on-surface-variant mb-4">
        Refleja los valores actuales del formulario. El logo nuevo se verá tras guardar.
      </p>
      <div class="bg-surface-container-low rounded-2xl p-4 overflow-x-auto">
        <CertificateCanvas :certificate="sampleCertificate" :settings="previewSettings" />
      </div>
    </section>
  </div>
</template>
