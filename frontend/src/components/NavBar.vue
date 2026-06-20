<script setup>
import { ref, computed } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { useCartStore } from '../stores/cart.js'

const router = useRouter()
const authStore = useAuthStore()
const cartStore = useCartStore()

const isAuthenticated = computed(() => authStore.isAuthenticated)
const user = computed(() => authStore.user)
const cartCount = computed(() => cartStore.count)
const mobileOpen = ref(false)

function toggleMobile() {
  mobileOpen.value = !mobileOpen.value
}

async function handleLogout() {
  await authStore.logout()
  mobileOpen.value = false
  router.push('/login')
}

function initials(name) {
  if (!name) return '?'
  return name
    .split(' ')
    .map((n) => n[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
}

// Shared classes for top-level desktop links
const linkClass =
  'font-title-md text-title-md text-on-surface-variant hover:text-primary transition-colors pb-1'
const activeClass = 'text-primary border-b-2 border-apricot-glow'
</script>

<template>
  <nav class="bg-surface/80 backdrop-blur-xl sticky top-0 z-50 shadow-sm border-b border-blush-canvas/20">
    <div class="max-w-container-max mx-auto px-gutter">
      <div class="flex justify-between items-center h-16">
        <!-- Brand -->
        <RouterLink to="/" class="font-headline-lg text-headline-lg font-bold text-primary tracking-tight">
          Ikena
        </RouterLink>

        <!-- Desktop nav -->
        <div class="hidden md:flex items-center gap-8">
          <RouterLink to="/" :class="linkClass" :exact-active-class="activeClass">
            Explorar
          </RouterLink>
          <RouterLink to="/services" :class="linkClass" :active-class="activeClass">
            Servicios
          </RouterLink>
          <RouterLink to="/products" :class="linkClass" :active-class="activeClass">
            Productos
          </RouterLink>

          <template v-if="isAuthenticated">
            <RouterLink to="/my-courses" :class="linkClass" :active-class="activeClass">
              Mis Cursos
            </RouterLink>
            <RouterLink
              v-if="user?.role === 'instructor'"
              to="/instructor"
              :class="linkClass"
              :active-class="activeClass"
            >
              Panel instructor
            </RouterLink>
            <RouterLink
              v-if="user?.role === 'admin'"
              to="/admin/services"
              :class="linkClass"
              :active-class="activeClass"
            >
              Servicios admin
            </RouterLink>
            <RouterLink
              v-if="user?.role === 'admin'"
              to="/admin/appointments"
              :class="linkClass"
              :active-class="activeClass"
            >
              Citas
            </RouterLink>

            <!-- User avatar + logout -->
            <div class="flex items-center gap-3">
              <RouterLink to="/profile" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                <img
                  v-if="user?.avatar"
                  :src="user.avatar"
                  :alt="user.name"
                  class="w-9 h-9 rounded-full object-cover ring-2 ring-apricot-glow"
                />
                <div
                  v-else
                  class="w-9 h-9 rounded-full bg-primary text-on-primary flex items-center justify-center font-label-md text-label-md"
                >
                  {{ initials(user?.name) }}
                </div>
                <span class="font-body-md text-body-md text-on-surface font-medium">{{ user?.name }}</span>
              </RouterLink>
              <button
                @click="handleLogout"
                class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors"
              >
                Salir
              </button>
            </div>
          </template>

          <template v-else>
            <RouterLink to="/login" :class="linkClass" :active-class="activeClass">
              Iniciar sesión
            </RouterLink>
            <RouterLink
              to="/register"
              class="bg-apricot-glow text-deep-marsala px-5 py-2 rounded-lg font-label-md text-label-md hover:-translate-y-0.5 transition-all active:scale-95"
            >
              Registrarse
            </RouterLink>
          </template>
        </div>

        <!-- Cart icon + badge (always visible) -->
        <RouterLink
          to="/cart"
          data-cart-link
          class="relative p-2 rounded-lg text-on-surface-variant hover:text-primary hover:bg-surface-container-low transition-colors"
          aria-label="Ver carrito"
        >
          <span class="material-symbols-outlined text-[22px]" aria-hidden="true">shopping_bag</span>
          <span
            v-if="cartCount > 0"
            data-cart-badge
            class="absolute -top-1 -right-1 min-w-[18px] h-[18px] rounded-full bg-apricot-glow text-deep-marsala text-[10px] font-bold flex items-center justify-center px-1 leading-none"
          >
            {{ cartCount }}
          </span>
        </RouterLink>

        <!-- Mobile hamburger -->
        <button
          @click="toggleMobile"
          class="md:hidden p-2 rounded-lg text-primary hover:bg-surface-container-low transition-colors"
          aria-label="Abrir menú"
        >
          <span class="material-symbols-outlined" aria-hidden="true">
            {{ mobileOpen ? 'close' : 'menu' }}
          </span>
        </button>
      </div>
    </div>

    <!-- Mobile menu -->
    <div v-if="mobileOpen" class="md:hidden border-t border-blush-canvas/20 bg-surface">
      <div class="px-gutter py-3 space-y-1">
        <RouterLink
          to="/"
          @click="mobileOpen = false"
          class="block py-2 font-body-md text-body-md text-on-surface-variant hover:text-primary transition-colors"
        >
          Explorar
        </RouterLink>
        <RouterLink
          to="/services"
          @click="mobileOpen = false"
          class="block py-2 font-body-md text-body-md text-on-surface-variant hover:text-primary transition-colors"
        >
          Servicios
        </RouterLink>
        <RouterLink
          to="/products"
          @click="mobileOpen = false"
          class="block py-2 font-body-md text-body-md text-on-surface-variant hover:text-primary transition-colors"
        >
          Productos
        </RouterLink>
        <RouterLink
          to="/cart"
          @click="mobileOpen = false"
          class="block py-2 font-body-md text-body-md text-on-surface-variant hover:text-primary transition-colors"
        >
          Carrito
          <span
            v-if="cartCount > 0"
            class="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] rounded-full bg-apricot-glow text-deep-marsala text-[10px] font-bold px-1 leading-none"
          >{{ cartCount }}</span>
        </RouterLink>

        <template v-if="isAuthenticated">
          <RouterLink
            to="/my-courses"
            @click="mobileOpen = false"
            class="block py-2 font-body-md text-body-md text-on-surface-variant hover:text-primary transition-colors"
          >
            Mis Cursos
          </RouterLink>
          <RouterLink
            v-if="user?.role === 'instructor'"
            to="/instructor"
            @click="mobileOpen = false"
            class="block py-2 font-body-md text-body-md text-on-surface-variant hover:text-primary transition-colors"
          >
            Panel instructor
          </RouterLink>
          <RouterLink
            v-if="user?.role === 'admin'"
            to="/admin/services"
            @click="mobileOpen = false"
            class="block py-2 font-body-md text-body-md text-on-surface-variant hover:text-primary transition-colors"
          >
            Servicios admin
          </RouterLink>
          <RouterLink
            v-if="user?.role === 'admin'"
            to="/admin/appointments"
            @click="mobileOpen = false"
            class="block py-2 font-body-md text-body-md text-on-surface-variant hover:text-primary transition-colors"
          >
            Citas
          </RouterLink>
          <RouterLink to="/profile" @click="mobileOpen = false" class="flex items-center gap-2 py-2 hover:opacity-80 transition-opacity">
            <img v-if="user?.avatar" :src="user.avatar" :alt="user.name" class="w-9 h-9 rounded-full object-cover ring-2 ring-apricot-glow" />
            <div
              v-else
              class="w-9 h-9 rounded-full bg-primary text-on-primary flex items-center justify-center font-label-md text-label-md shrink-0"
            >
              {{ initials(user?.name) }}
            </div>
            <span class="font-body-md text-body-md text-on-surface font-medium">{{ user?.name }}</span>
          </RouterLink>
          <button
            @click="handleLogout"
            class="block w-full text-left py-2 font-body-md text-body-md text-on-surface-variant hover:text-primary transition-colors"
          >
            Cerrar sesión
          </button>
        </template>

        <template v-else>
          <RouterLink
            to="/login"
            @click="mobileOpen = false"
            class="block py-2 font-body-md text-body-md text-on-surface-variant hover:text-primary transition-colors"
          >
            Iniciar sesión
          </RouterLink>
          <RouterLink
            to="/register"
            @click="mobileOpen = false"
            class="block py-2 font-label-md text-label-md text-deep-marsala font-semibold"
          >
            Registrarse
          </RouterLink>
        </template>
      </div>
    </div>
  </nav>
</template>
