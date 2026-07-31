<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useCartStore } from '../../stores/cart.js'

// Slim sticky top bar: brand wordmark + cart. Deliberately NOT a second
// navigation surface — primary navigation is the bottom tab bar, and mixing
// two nav patterns at the same hierarchy level is an anti-pattern (Skill:
// avoid-mixed-patterns, nav-hierarchy). The cart lives here rather than in the
// tab bar because it is an action on the current session, not a top-level
// destination, and the tab bar is already at its 5-item cap.
//
// Cart-count logic ported from frontend/src/components/NavBar.vue.
const cartStore = useCartStore()

const cartCount = computed(() => cartStore.count)

// Cap the rendered badge so a runaway quantity cannot widen the badge past
// the icon and break the bar's layout (Skill: layout-shift-avoid).
const cartBadge = computed(() => (cartCount.value > 99 ? '99+' : String(cartCount.value)))

// The count must also reach screen readers, not only the badge's colour and
// position (Skill: color-not-only, aria-labels).
const cartLabel = computed(() => {
  if (cartCount.value === 0) return 'Ver carrito'
  const noun = cartCount.value === 1 ? 'artículo' : 'artículos'
  return `Ver carrito, ${cartCount.value} ${noun}`
})
</script>

<template>
  <!-- sticky (not fixed) so the bar stays in normal flow and pushes the
       content down by itself — that is why only the bottom nav needs a
       content inset. .app-topbar supplies the env(safe-area-inset-top)
       padding that clears the status bar / notch; see src/style.css. -->
  <header
    data-app-topbar
    class="app-topbar sticky top-0 z-40 border-b border-blush-canvas/20 bg-surface/95 backdrop-blur-xl"
  >
    <!-- Reading progress. Absolutely positioned against this sticky bar rather
         than fixed at the viewport top: with viewport-fit=cover, a bar at top 0
         would sit under the status bar / notch. Riding the bottom border puts
         it below the safe-area padding without any inset maths. Decorative --
         it repeats what the scroll position already says. -->
    <div data-scroll-progress class="barra" aria-hidden="true"></div>

    <div class="mx-auto flex h-14 max-w-container-max items-center justify-between px-gutter">
      <RouterLink
        :to="{ name: 'home' }"
        data-brand
        class="font-headline-lg text-title-md font-bold tracking-tight text-primary"
      >
        Ikena
      </RouterLink>

      <!-- h-12 w-12 = 48dp tap target even though the glyph is 24dp
           (Skill: touch-target-size). -mr-2 pulls the visual edge back into
           line with px-gutter without shrinking the hit area. -->
      <RouterLink
        :to="{ name: 'cart' }"
        data-cart-link
        :aria-label="cartLabel"
        class="relative -mr-2 grid h-12 w-12 touch-manipulation place-items-center rounded-full text-on-surface-variant transition-colors duration-100 ease-out active:bg-surface-container-high"
      >
        <span class="material-symbols-outlined text-[24px]" aria-hidden="true">shopping_cart</span>
        <span
          v-if="cartCount > 0"
          data-cart-badge
          aria-hidden="true"
          class="absolute top-1.5 right-1.5 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-apricot-glow px-1 font-body-md text-[10px] leading-none font-bold text-deep-marsala"
        >
          {{ cartBadge }}
        </span>
      </RouterLink>
    </div>
  </header>
</template>
