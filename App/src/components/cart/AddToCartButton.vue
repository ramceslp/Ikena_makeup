<script setup>
import { computed, ref, onBeforeUnmount } from 'vue'
import { useCartStore } from '../../stores/cart.js'
import BaseButton from '../ui/BaseButton.vue'

// The "add to cart" CTA, shared by ProductCard.vue (catalog grid) and
// ProductDetail.vue (detail screen).
//
// Both of those views shipped WITHOUT a cart CTA on purpose -- see their
// header comments: the cart store did not exist yet on the branch that
// introduced them, so they offered only "Ver Detalles". stores/cart.js,
// views/Cart.vue and the TopAppBar badge all landed later, but nobody went
// back to add the button, which left the app with a fully working cart that
// no screen could put anything into. This component closes that loop in one
// place instead of duplicating the same stock/quantity reasoning twice.
//
// It owns three states the raw store call cannot express on its own:
//   - out of stock      -> disabled, "Agotado" (cart.addItem() already
//                          refuses these silently; a silent no-op button
//                          reads as a broken button).
//   - already at max    -> disabled, "Máximo disponible" (addItem() clamps to
//                          stock_qty, so further taps would also be silent).
//   - just added        -> transient confirmation, because the only other
//                          feedback is the TopAppBar badge, which sits off
//                          the thumb's path at the top of the screen.
const props = defineProps({
  product: { type: Object, required: true },
  size: { type: String, default: 'sm' },
  variant: { type: String, default: 'primary' },
  fullWidth: { type: Boolean, default: false },
})

const cart = useCartStore()

// Numeric coercion mirrors cart.addItem()'s own guard: stock_qty may arrive
// as a string from the API, and hydrated cart rows carry no stock_state.
const stockQty = computed(() => {
  const qty = Number(props.product?.stock_qty)
  return Number.isFinite(qty) ? qty : 0
})

const isOutOfStock = computed(() => stockQty.value <= 0)

const quantityInCart = computed(
  () => cart.items.find((item) => item.product_id === props.product?.id)?.quantity ?? 0
)

const isMaxed = computed(() => !isOutOfStock.value && quantityInCart.value >= stockQty.value)

const isAdding = ref(false)
const justAdded = ref(false)
let confirmationTimer = null

async function add() {
  if (isOutOfStock.value || isMaxed.value || isAdding.value) return

  isAdding.value = true
  try {
    // addItem() never rejects — a storage failure is recorded on
    // cart.persistError and surfaced by Cart.vue's banner, so there is no
    // error branch to render here.
    await cart.addItem(props.product)
    justAdded.value = true
    clearTimeout(confirmationTimer)
    confirmationTimer = setTimeout(() => {
      justAdded.value = false
    }, 2000)
  } finally {
    isAdding.value = false
  }
}

// Without this, the pending timeout fires against an unmounted component when
// the user taps a card and navigates away inside the 2s window.
onBeforeUnmount(() => clearTimeout(confirmationTimer))

const label = computed(() => {
  if (isOutOfStock.value) return 'Agotado'
  if (justAdded.value) return 'Añadido al carrito'
  if (isMaxed.value) return 'Máximo disponible'
  return 'Añadir al carrito'
})

const icon = computed(() => {
  if (isOutOfStock.value) return 'remove_shopping_cart'
  if (justAdded.value) return 'check_circle'
  return 'add_shopping_cart'
})
</script>

<template>
  <div :class="fullWidth ? 'w-full' : ''">
    <BaseButton
      data-add-to-cart
      :variant="variant"
      :size="size"
      :disabled="isOutOfStock || isMaxed"
      :loading="isAdding"
      :class="fullWidth ? 'w-full' : ''"
      @click="add"
    >
      <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ icon }}</span>
      {{ label }}
    </BaseButton>

    <!-- The button's own label change is not reliably announced (the button is
         not necessarily focused when tapped on a touch screen), so the
         confirmation gets its own polite live region. -->
    <p data-add-to-cart-status class="sr-only" role="status" aria-live="polite">
      {{ justAdded ? `${product.title} añadido al carrito` : '' }}
    </p>
  </div>
</template>
