<script setup>
import { computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth.js'
import { initials } from './initials.js'

// Primary navigation for the app (Skill: tab-bar-ios / bottom-nav-top-level).
// Exactly 5 top-level destinations — the Material hard cap (Skill:
// bottom-nav-limit). Do NOT add a sixth: use a Profile sub-screen instead.
//
// Every item carries BOTH an icon and a text label (Skill: nav-label-icon);
// icon-only tabs were the discoverability problem this shell exists to fix
// ("no veo las opciones para iniciar sesión"). Logout deliberately does NOT
// live here — it is destructive-adjacent and belongs on the Profile screen
// (Skill: destructive-nav-separation).
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const isAuthenticated = computed(() => authStore.isAuthenticated)
const user = computed(() => authStore.user)

// The Cursos destination is owned by the courses feature, not by the shell,
// so it is targeted BY NAME — a path change there needs no edit here.
//
// The hasRoute() guard is a blast-radius limiter, not indirection for its own
// sake: router.resolve({ name }) THROWS for an unregistered name, and because
// this bar renders on every screen, that throw would blank the entire app —
// not just this one tab. Falling back to the literal path degrades to "tab
// present, destination not wired yet" instead. Once the courses routes are
// registered (the normal state) this resolves to { name: 'courses' } exactly
// as if the guard were not here.
const coursesTo = computed(() => (router.hasRoute('courses') ? { name: 'courses' } : '/cursos'))

// `basePath` — not the route name — drives the active state, so a nested
// detail route (/products/:slug, /services/:slug, /cursos/:slug) keeps its
// parent tab highlighted without this component having to enumerate every
// child route name owned by another feature.
const tabs = computed(() => [
  { key: 'home', label: 'Inicio', icon: 'home', to: { name: 'home' }, basePath: '/' },
  { key: 'courses', label: 'Cursos', icon: 'school', to: coursesTo.value, basePath: '/cursos' },
  { key: 'services', label: 'Servicios', icon: 'spa', to: { name: 'services' }, basePath: '/services' },
  {
    key: 'products',
    label: 'Productos',
    icon: 'shopping_bag',
    to: { name: 'products' },
    basePath: '/products',
  },
  // Auth-aware 5th tab. Signed in -> the user's own avatar/initials routing to
  // Perfil; signed out -> an explicit, always-visible way into Entrar.
  isAuthenticated.value
    ? {
        key: 'account',
        label: 'Perfil',
        icon: 'person',
        to: { name: 'profile' },
        basePath: '/profile',
        isAccount: true,
      }
    : {
        key: 'account',
        label: 'Entrar',
        icon: 'login',
        to: { name: 'login' },
        basePath: '/login',
        isAccount: true,
      },
])

function isActive(tab) {
  // '/' would prefix-match every route, so home is exact-match only.
  if (tab.basePath === '/') return route.path === '/'
  return route.path === tab.basePath || route.path.startsWith(`${tab.basePath}/`)
}

// The active tab is signalled four ways, never colour alone (Skill:
// nav-state-active, color-not-only): the tonal indicator pill behind the icon,
// a FILLED icon (Material Symbols' FILL variation axis), a heavier label
// weight, and the marsala text colour.
const ACTIVE_ICON_STYLE = { fontVariationSettings: "'FILL' 1" }
</script>

<template>
  <!-- Fixed so it stays reachable from every screen, including deep detail
       pages (Skill: persistent-nav). .app-bottomnav adds the
       env(safe-area-inset-bottom) padding that lifts the tap targets clear of
       the Android gesture bar — see the app-shell block in src/style.css. -->
  <nav
    data-app-bottomnav
    aria-label="Navegación principal"
    class="app-bottomnav fixed inset-x-0 bottom-0 z-40 border-t border-blush-canvas/25 bg-surface/95 backdrop-blur-xl"
  >
    <!-- gap-2 keeps >=8px between adjacent tap targets (Skill: touch-spacing).
         On a 360dp phone each tab is still ~62dp wide, comfortably over the
         48dp minimum (Skill: touch-target-size). -->
    <ul class="mx-auto flex max-w-container-max items-stretch gap-2 px-2">
      <li v-for="tab in tabs" :key="tab.key" class="min-w-0 flex-1">
        <RouterLink
          :to="tab.to"
          :data-tab="tab.key"
          :aria-current="isActive(tab) ? 'page' : undefined"
          class="relative flex min-h-14 w-full touch-manipulation flex-col items-center justify-center gap-0.5 rounded-xl transition-colors duration-100 ease-out active:bg-surface-container-high"
          :class="isActive(tab) ? 'text-primary' : 'text-on-surface-variant'"
        >
          <!-- Material 3 "active indicator": a tonal pill behind the icon,
               which is the Android-idiomatic signal for the selected tab
               (Skill: platform-adaptive). Rendered as an out-of-flow sibling
               so selecting a tab changes paint only — it never resizes or
               reflows the row (Skill: "Stable Interaction States",
               layout-shift-avoid). Painted before the icon in DOM order, so
               the icon sits on top without needing a z-index. -->
          <span class="relative grid h-8 w-14 max-w-full place-items-center">
            <span
              v-if="isActive(tab)"
              data-tab-indicator
              aria-hidden="true"
              class="absolute inset-0 rounded-full bg-blush-canvas"
            ></span>

            <img
              v-if="tab.isAccount && isAuthenticated && user?.avatar"
              data-tab-avatar
              :src="user.avatar"
              alt=""
              referrerpolicy="no-referrer"
              class="relative h-6 w-6 rounded-full object-cover ring-2"
              :class="isActive(tab) ? 'ring-deep-marsala/30' : 'ring-transparent'"
            />
            <span
              v-else-if="tab.isAccount && isAuthenticated"
              data-tab-initials
              aria-hidden="true"
              class="relative grid h-6 w-6 place-items-center rounded-full bg-primary text-[10px] leading-none font-bold text-on-primary"
            >
              {{ initials(user?.name) }}
            </span>
            <span
              v-else
              class="material-symbols-outlined relative text-[24px] leading-none"
              :style="isActive(tab) ? ACTIVE_ICON_STYLE : undefined"
              aria-hidden="true"
            >
              {{ tab.icon }}
            </span>
          </span>

          <span
            class="font-body-md text-[11px] leading-4 whitespace-nowrap"
            :class="isActive(tab) ? 'font-bold' : 'font-medium'"
          >
            {{ tab.label }}
          </span>
        </RouterLink>
      </li>
    </ul>
  </nav>
</template>
