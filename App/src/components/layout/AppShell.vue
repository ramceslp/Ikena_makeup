<script setup>
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import TopAppBar from './TopAppBar.vue'
import BottomTabBar from './BottomTabBar.vue'

// The app's single layout. Owns three things no view should have to repeat:
//
//   1. The navigation chrome (slim sticky top bar + fixed bottom tab bar).
//   2. The content inset that keeps scrolling content clear of the fixed
//      bottom bar and the device safe areas — solved ONCE here, on the outlet
//      wrapper, never per view (Skill: fixed-element-offset,
//      scroll-and-fixed-coexistence).
//   3. Chrome visibility, driven by route meta.
//
// Chrome visibility contract: a route opts out with `meta: { hideChrome: true }`
// (see router/index.js — /login is the only route that does). The nav
// components themselves know nothing about route names, so adding another
// full-screen route later is a one-line meta change, not an edit here.
// Everything else keeps full chrome so navigation stays reachable from deep
// pages (Skill: persistent-nav).
const route = useRoute()

const showChrome = computed(() => route.meta?.hideChrome !== true)
</script>

<template>
  <!-- min-h-dvh, not min-h-screen: 100vh is wrong on mobile browsers/WebViews
       once the dynamic toolbars are accounted for (Skill: viewport-units). -->
  <div class="flex min-h-dvh flex-col bg-background">
    <TopAppBar v-if="showChrome" />

    <!-- A plain <div>, not <main>: views own their own <main> landmark
         (see views/Login.vue), and nesting two would break the landmark
         structure for screen readers. -->
    <div
      data-app-content
      class="flex-1"
      :class="showChrome ? 'app-content' : 'app-content-bare'"
    >
      <RouterView />
    </div>

    <BottomTabBar v-if="showChrome" />
  </div>
</template>
