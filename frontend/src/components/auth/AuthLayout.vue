<script setup>
import { useRoute } from 'vue-router'
import { RouterLink } from 'vue-router'
import authLogo from '../../assets/auth-logo.jpg'

const route = useRoute()
</script>

<template>
  <!-- Full-viewport tonal background.
       Top-anchored (items-start) so switching between the shorter Login and the
       taller Register grows the card DOWNWARD from a fixed top edge, instead of
       expanding from a vertical center (which made the card jump up and down). -->
  <div
    class="min-h-screen bg-background font-body-md text-on-background relative flex items-start justify-center px-4 py-8 md:py-16"
  >
    <!-- Background: hero image at low opacity + gradient overlay -->
    <div class="absolute inset-0 z-0 overflow-hidden" aria-hidden="true">
      <img
        :src="authLogo"
        alt=""
        class="w-full h-full object-contain object-center opacity-15"
        aria-hidden="true"
      />
      <div
        class="absolute inset-0 bg-gradient-to-tr from-surface-muted via-transparent to-surface-container-low"
      />
    </div>

    <!-- Main container -->
    <main class="relative z-10 w-full max-w-[480px]">
      <!-- Header / Brand -->
      <div class="text-center mb-8">
        <h1
          class="font-headline-lg text-headline-lg font-bold text-deep-marsala tracking-tight mb-2"
        >
          Ikena
        </h1>
        <p class="font-title-md text-title-md text-on-surface-variant opacity-80">
          <slot name="tagline">Domina el arte del makeup profesional</slot>
        </p>
      </div>

      <!-- Glass card -->
      <div
        class="rounded-[2rem] border border-blush-canvas/30 shadow-2xl shadow-deep-marsala/5 bg-surface-container-lowest/85 backdrop-blur-xl p-8 md:p-10"
      >
        <!-- Toggle tabs -->
        <div class="flex p-1 bg-surface-container rounded-full mb-8 relative">
          <RouterLink
            to="/login"
            class="flex-1 py-2 rounded-full font-label-md text-label-md transition-all duration-300 text-center"
            :class="
              route.path === '/login'
                ? 'bg-surface-container-lowest text-primary font-bold shadow-sm'
                : 'text-on-surface-variant'
            "
          >
            Iniciar Sesión
          </RouterLink>
          <RouterLink
            to="/register"
            class="flex-1 py-2 rounded-full font-label-md text-label-md transition-all duration-300 text-center"
            :class="
              route.path === '/register'
                ? 'bg-surface-container-lowest text-primary font-bold shadow-sm'
                : 'text-on-surface-variant'
            "
          >
            Crear Cuenta
          </RouterLink>
        </div>

        <!-- Card body: re-animates on each mount (i.e. each tab switch) so the
             swap between Login/Register reads as a soft transition while the
             logo and tabs above stay perfectly still. -->
        <div class="auth-body">
          <!-- Form slot -->
          <slot />

          <!-- Divider -->
          <div class="relative my-8">
          <div class="absolute inset-0 flex items-center" aria-hidden="true">
            <div class="w-full border-t border-blush-canvas/20" />
          </div>
          <div class="relative flex justify-center">
            <span
              class="bg-surface-container-lowest px-4 font-label-sm text-label-sm uppercase text-on-surface-variant"
            >
              O continúa con
            </span>
          </div>
        </div>

          <!-- OAuth slot -->
          <slot name="oauth" />

          <!-- Terms / Privacy footer — static copy, no routes wired -->
          <p class="mt-8 text-center font-label-sm text-label-sm text-on-surface-variant leading-relaxed">
            Al continuar, aceptas nuestros
            <span class="text-secondary font-bold">Términos de Servicio</span>
            y nuestra
            <span class="text-secondary font-bold">Política de Privacidad</span>.
          </p>
        </div>
      </div>
    </main>
  </div>
</template>

<style scoped>
/* Soft entrance for the card body on each mount (= each Login/Register switch).
   Disabled under reduced-motion so the swap is instant for those users. */
@media (prefers-reduced-motion: no-preference) {
  .auth-body {
    animation: auth-body-enter 350ms cubic-bezier(0.16, 1, 0.3, 1);
  }
}

@keyframes auth-body-enter {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}
</style>
