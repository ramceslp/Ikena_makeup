<script setup>
import NavBar from './components/NavBar.vue'
import SiteFooter from './components/layout/SiteFooter.vue'
</script>

<template>
  <div class="min-h-screen flex flex-col bg-background text-on-surface font-body-md">
    <NavBar class="no-print" />
    <main class="flex-grow">
      <router-view />
    </main>
    <SiteFooter class="no-print" />
  </div>
</template>
