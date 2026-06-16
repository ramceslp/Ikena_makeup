<script setup>
/**
 * TabGroup — accessible tab bar with slot-per-key content panels.
 *
 * Usage:
 *   <TabGroup v-model="activeTab" :tabs="[{ key: 'a', label: 'Tab A' }, { key: 'b', label: 'Tab B' }]">
 *     <template #tab-a>Content for A</template>
 *     <template #tab-b>Content for B</template>
 *   </TabGroup>
 *
 * Slot naming: each tab key is prefixed with "tab-" to avoid conflicts with
 * reserved Vue slot names. The parent provides named slots e.g. #tab-overview.
 */

// Active tab key is two-way bound
const active = defineModel({ type: String, default: '' })

defineProps({
  // Array of { key: string, label: string }
  tabs: { type: Array, default: () => [] },
})
</script>

<template>
  <div>
    <!-- Tab bar -->
    <div
      class="flex border-b border-blush-canvas/30 gap-1"
      role="tablist"
    >
      <button
        v-for="tab in tabs"
        :key="tab.key"
        role="tab"
        :aria-selected="active === tab.key"
        :tabindex="active === tab.key ? 0 : -1"
        class="relative px-5 py-3 font-title-md text-title-md transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-t-lg"
        :class="
          active === tab.key
            ? 'text-primary'
            : 'text-on-surface-variant hover:text-primary hover:bg-surface-container-low'
        "
        @click="active = tab.key"
        @keydown.right.prevent="
          active = tabs[(tabs.findIndex(t => t.key === active) + 1) % tabs.length]?.key ?? active
        "
        @keydown.left.prevent="
          active = tabs[(tabs.findIndex(t => t.key === active) - 1 + tabs.length) % tabs.length]?.key ?? active
        "
      >
        {{ tab.label }}

        <!-- Active underline accent (apricot-glow, matches NavBar style) -->
        <span
          v-if="active === tab.key"
          class="absolute bottom-0 left-0 right-0 h-0.5 bg-apricot-glow rounded-full"
          aria-hidden="true"
        />
      </button>
    </div>

    <!-- Tab panels — one slot per key, prefixed with "tab-" -->
    <div class="pt-4">
      <template v-for="tab in tabs" :key="tab.key">
        <div
          v-show="active === tab.key"
          role="tabpanel"
          :aria-labelledby="`tab-${tab.key}`"
        >
          <slot :name="`tab-${tab.key}`" />
        </div>
      </template>
    </div>
  </div>
</template>
