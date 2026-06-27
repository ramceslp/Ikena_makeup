<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth.js'
import ProfileSidebarNav from '../components/profile/ProfileSidebarNav.vue'
import PersonalInfoForm from '../components/profile/PersonalInfoForm.vue'
import SecurityForm from '../components/profile/SecurityForm.vue'
import PurchaseHistory from '../components/profile/PurchaseHistory.vue'

const authStore = useAuthStore()
const user = computed(() => authStore.user)

const activeSection = ref('perfil')

// Profile section state
const savingProfile = ref(false)
const profileError = ref('')
const profileFieldErrors = ref({})
const profileSuccess = ref(false)

// Security section state
const savingPassword = ref(false)
const passwordError = ref('')
const passwordFieldErrors = ref({})
const passwordSuccess = ref(false)

// Orders loading state
const loadingOrders = ref(false)

async function handleProfileSave({ name, email, avatarFile }) {
  savingProfile.value = true
  profileError.value = ''
  profileFieldErrors.value = {}
  profileSuccess.value = false

  const fd = new FormData()
  fd.append('name', name)
  fd.append('email', email)
  if (avatarFile) {
    fd.append('avatar', avatarFile)
  }

  try {
    await authStore.updateProfile(fd)
    profileSuccess.value = true
  } catch (err) {
    const data = err.response?.data
    profileError.value = data?.message || 'Error al actualizar el perfil.'
    profileFieldErrors.value = data?.errors || {}
  } finally {
    savingProfile.value = false
  }
}

async function handlePasswordChange(payload) {
  savingPassword.value = true
  passwordError.value = ''
  passwordFieldErrors.value = {}
  passwordSuccess.value = false

  try {
    await authStore.changePassword(payload)
    passwordSuccess.value = true
  } catch (err) {
    const data = err.response?.data
    passwordError.value = data?.message || 'Error al cambiar la contraseña.'
    passwordFieldErrors.value = data?.errors || {}
  } finally {
    savingPassword.value = false
  }
}

onMounted(async () => {
  loadingOrders.value = true
  try {
    await authStore.fetchOrders()
  } finally {
    loadingOrders.value = false
  }
})
</script>

<template>
  <section class="py-12 bg-background min-h-screen">
    <div class="max-w-container-max mx-auto px-gutter">
      <!-- Page header -->
      <div v-reveal class="mb-8">
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Mi perfil</h1>
      </div>

      <div class="flex flex-col md:flex-row gap-8">
        <!-- Sidebar nav -->
        <div class="md:w-60 flex-shrink-0">
          <ProfileSidebarNav v-model="activeSection" />
        </div>

        <!-- Content area -->
        <div class="flex-1 min-w-0">
          <!-- Personal info section -->
          <div v-if="activeSection === 'perfil'" v-reveal class="bg-surface rounded-2xl border border-blush-canvas/30 shadow-md shadow-primary/5 p-6">
            <h2 class="font-title-lg text-title-lg text-on-surface mb-6">Información personal</h2>
            <PersonalInfoForm
              v-if="user"
              :user="user"
              :saving="savingProfile"
              :error="profileError"
              :field-errors="profileFieldErrors"
              :success="profileSuccess"
              @submit="handleProfileSave"
            />
          </div>

          <!-- Security section -->
          <div v-else-if="activeSection === 'seguridad'" v-reveal class="bg-surface rounded-2xl border border-blush-canvas/30 shadow-md shadow-primary/5 p-6">
            <h2 class="font-title-lg text-title-lg text-on-surface mb-6">Seguridad</h2>
            <SecurityForm
              v-if="user"
              :user="user"
              :saving="savingPassword"
              :error="passwordError"
              :field-errors="passwordFieldErrors"
              :success="passwordSuccess"
              @submit="handlePasswordChange"
            />
          </div>

          <!-- Purchase history section -->
          <div v-else-if="activeSection === 'historial'" v-reveal class="bg-surface rounded-2xl border border-blush-canvas/30 shadow-md shadow-primary/5 p-6">
            <h2 class="font-title-lg text-title-lg text-on-surface mb-6">Historial de compras</h2>
            <PurchaseHistory
              :orders="authStore.orders"
              :loading="loadingOrders"
            />
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
