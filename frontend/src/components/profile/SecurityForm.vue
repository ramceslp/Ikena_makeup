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

const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')

watch(() => props.success, (val) => {
  if (val) {
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
  }
})

function handleSubmit() {
  emit('submit', {
    current_password: currentPassword.value,
    password: newPassword.value,
    password_confirmation: confirmPassword.value,
  })
}
</script>

<template>
  <!-- Google-only account: no password available -->
  <div
    v-if="!user.has_password"
    class="bg-surface-container rounded-2xl p-6 border border-blush-canvas/30"
  >
    <div class="flex items-start gap-3">
      <span class="material-symbols-outlined text-primary text-2xl mt-0.5" aria-hidden="true">
        info
      </span>
      <div>
        <p class="font-title-sm text-title-sm text-on-surface mb-1">Sin contraseña</p>
        <p class="font-body-md text-body-md text-on-surface-variant">
          Tu cuenta inicia sesión con Google, por lo que no tiene contraseña que cambiar.
        </p>
      </div>
    </div>
  </div>

  <!-- Regular account: password change form -->
  <form v-else @submit.prevent="handleSubmit" class="space-y-6">
    <BaseInput
      v-model="currentPassword"
      id="current-password"
      label="Contraseña actual"
      type="password"
      :revealable="true"
      placeholder="••••••••"
      autocomplete="current-password"
      :error="fieldErrors.current_password || ''"
    />
    <BaseInput
      v-model="newPassword"
      id="new-password"
      label="Nueva contraseña"
      type="password"
      :revealable="true"
      placeholder="••••••••"
      autocomplete="new-password"
      :error="fieldErrors.password || ''"
    />
    <BaseInput
      v-model="confirmPassword"
      id="confirm-password"
      label="Confirmar nueva contraseña"
      type="password"
      :revealable="true"
      placeholder="••••••••"
      autocomplete="new-password"
      :error="fieldErrors.password_confirmation || ''"
    />

    <!-- General error -->
    <p v-if="error" class="font-body-sm text-body-sm text-error" role="alert">{{ error }}</p>

    <!-- Success -->
    <p v-if="success" class="font-body-sm text-body-sm text-primary" role="status">
      Contraseña actualizada correctamente.
    </p>

    <BaseButton type="submit" :disabled="saving" size="sm">
      {{ saving ? 'Guardando…' : 'Cambiar contraseña' }}
    </BaseButton>
  </form>
</template>
