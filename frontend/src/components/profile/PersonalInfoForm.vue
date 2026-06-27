<script setup>
import { ref, watch } from 'vue'
import BaseInput from '../ui/BaseInput.vue'
import BaseButton from '../ui/BaseButton.vue'

const props = defineProps({
  user: { type: Object, required: true },
  saving: { type: Boolean, default: false },
  error: { type: String, default: '' },
  fieldErrors: { type: Object, default: () => ({}) },
  success: { type: Boolean, default: false },
})
const emit = defineEmits(['submit'])

const localName = ref('')
const localEmail = ref('')
const avatarFile = ref(null)
const avatarPreview = ref(null)

watch(() => props.user, (u) => {
  if (!u) return
  localName.value = u.name ?? ''
  localEmail.value = u.email ?? ''
}, { immediate: true })

function initials(name) {
  if (!name) return '?'
  return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase()
}

function onAvatarChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  avatarFile.value = file
  avatarPreview.value = URL.createObjectURL(file)
}

function handleSubmit() {
  emit('submit', {
    name: localName.value,
    email: localEmail.value,
    avatarFile: avatarFile.value,
  })
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <!-- Avatar -->
    <div class="flex items-center gap-4">
      <div class="relative w-20 h-20 flex-shrink-0">
        <img
          v-if="avatarPreview || user.avatar"
          :src="avatarPreview || user.avatar"
          :alt="user.name"
          class="w-20 h-20 rounded-full object-cover ring-2 ring-apricot-glow"
        />
        <div
          v-else
          class="w-20 h-20 rounded-full bg-primary text-on-primary flex items-center justify-center font-headline-sm text-headline-sm"
        >
          {{ initials(user.name) }}
        </div>
      </div>
      <div class="flex flex-col gap-1">
        <label
          for="avatar-input"
          class="cursor-pointer inline-flex items-center gap-1 font-label-md text-label-md text-primary hover:underline"
        >
          <span class="material-symbols-outlined text-[16px]" aria-hidden="true">upload</span>
          Cambiar foto
        </label>
        <input
          id="avatar-input"
          type="file"
          accept="image/*"
          class="sr-only"
          @change="onAvatarChange"
        />
        <p class="font-label-sm text-label-sm text-on-surface-variant">JPG, PNG o GIF. Máx. 2 MB</p>
      </div>
    </div>

    <!-- Name -->
    <BaseInput
      v-model="localName"
      id="profile-name"
      label="Nombre completo"
      type="text"
      placeholder="Tu nombre"
      autocomplete="name"
      :error="fieldErrors.name || ''"
    />

    <!-- Email -->
    <BaseInput
      v-model="localEmail"
      id="profile-email"
      label="Correo electrónico"
      type="email"
      placeholder="tu@correo.com"
      autocomplete="email"
      :error="fieldErrors.email || ''"
    />

    <!-- General error -->
    <p v-if="error" class="font-body-sm text-body-sm text-error" role="alert">
      {{ error }}
    </p>

    <!-- Success -->
    <p v-if="success" class="font-body-sm text-body-sm text-primary" role="status">
      Perfil actualizado correctamente.
    </p>

    <BaseButton type="submit" :loading="saving" size="sm">
      {{ saving ? 'Guardando…' : 'Guardar cambios' }}
    </BaseButton>
  </form>
</template>
