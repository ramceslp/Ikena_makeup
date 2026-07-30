<script setup>
import { RouterLink, useRoute } from 'vue-router'
import BaseButton from '../components/ui/BaseButton.vue'

// The router's catch-all landing (see router/index.js's last route).
//
// Added after a real incident: a custom push notification carrying a deep link
// the app has no route for. vue-router 4 does NOT reject a push() to an
// unmatched path — it resolves with an empty `matched` array — so AppShell
// rendered its top and bottom bars around an empty <RouterView> and the user
// got a blank screen. Nothing threw, nothing was logged, and the admin's send
// history still read "sent".
//
// The dead link itself is now blocked at its source (the destination picker in
// the admin panel, validated server-side against config/push_destinations.php).
// This view is the floor under that: any OTHER way of reaching a path that does
// not exist — an old notification sent before the fix, a link in a build newer
// than the installed app, a hand-typed URL — lands on something explicable with
// a way out, instead of on nothing.
const route = useRoute()
</script>

<template>
  <main class="max-w-container-max mx-auto px-gutter section-y-sm">
    <div class="state-y text-center" data-not-found>
      <span class="material-symbols-outlined text-5xl text-primary mb-4" aria-hidden="true">
        explore_off
      </span>

      <h1 class="font-title-lg text-title-lg text-on-surface mb-2">No encontramos esta pantalla</h1>

      <p class="font-body-md text-body-md text-on-surface-variant mb-1">
        Puede que el enlace sea viejo o que esta sección todavía no esté en tu versión de la app.
      </p>

      <!-- Shown so a user reporting the problem can say WHICH link failed —
           without it, "me abrió una pantalla rara" is unactionable. -->
      <p class="font-body-sm text-body-sm text-on-surface-variant/70 mb-6 break-all" data-attempted-path>
        {{ route.fullPath }}
      </p>

      <RouterLink to="/">
        <BaseButton variant="primary">Ir al inicio</BaseButton>
      </RouterLink>
    </div>
  </main>
</template>
