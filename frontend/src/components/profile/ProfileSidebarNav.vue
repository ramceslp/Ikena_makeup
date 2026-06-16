<script setup>
const model = defineModel({ type: String, default: 'perfil' })

const items = [
  { key: 'perfil', label: 'Perfil' },
  { key: 'seguridad', label: 'Seguridad' },
  { key: 'historial', label: 'Historial de compras' },
]
</script>

<template>
  <!-- Mobile: horizontal scroll tabs -->
  <div class="md:hidden flex overflow-x-auto border-b border-blush-canvas/30 gap-0">
    <button
      v-for="item in items"
      :key="item.key"
      @click="model = item.key"
      class="flex-shrink-0 px-4 py-3 font-label-md text-label-md transition-colors whitespace-nowrap"
      :class="model === item.key
        ? 'text-primary border-b-2 border-apricot-glow'
        : 'text-on-surface-variant hover:text-primary'"
    >
      {{ item.label }}
    </button>
  </div>

  <!-- Desktop: vertical sidebar -->
  <nav class="hidden md:flex flex-col gap-1">
    <button
      v-for="item in items"
      :key="item.key"
      @click="model = item.key"
      class="text-left px-4 py-3 rounded-xl font-body-md text-body-md transition-colors"
      :class="model === item.key
        ? 'text-primary bg-surface-container font-medium'
        : 'text-on-surface-variant hover:text-primary hover:bg-surface-container'"
    >
      {{ item.label }}
    </button>
  </nav>
</template>
