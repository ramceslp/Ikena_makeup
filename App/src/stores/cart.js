import { defineStore } from 'pinia'
import { startCheckoutHandoff } from '../services/checkoutHandoff.js'
import { set, remove, getCached, CART_KEY } from '../services/storage.js'

// Ported from frontend/src/stores/cart.js (mobile-capacitor-setup Phase 8,
// tasks 8.1/8.2). Business logic (add/remove/update-quantity/subtotal/clear)
// is a direct port; persistence is adapted from synchronous localStorage to
// storage.js's async Preferences-backed API, following the same pattern
// already established by stores/auth.js: initial state is read synchronously
// from storage.js's in-memory cache, which main.js's bootstrap() warms via
// hydrate() BEFORE the app is mounted (see services/storage.js hydrate()).
//
// checkout() (the web's direct /cart/checkout + inline PayPhone widget
// action) is intentionally NOT ported here -- that pattern would render
// payment UI inside this app's own WebView, which the spec's Mobile App
// Boundaries explicitly forbid ("MUST NOT render any payment UI inside the
// app's own WebView"). pay() below is the app's real equivalent: it calls
// the checkout-handoff endpoint (design.md Decision 1) and hands the
// returned URL to @capacitor/browser's Browser.open(), which renders the
// checkout page in the system/in-app Browser container -- never the app's
// own router/WebView (tasks 8.3-8.5).
function isValidItem(item) {
  if (!item || typeof item !== 'object') return false
  const price = parseFloat(item.price)
  const qty = Number(item.quantity)
  const pid = item.product_id
  return pid != null && Number.isFinite(price) && Number.isFinite(qty) && qty > 0
}

function sanitizeItems(cached) {
  if (!Array.isArray(cached)) return []
  return cached.filter(isValidItem)
}

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: sanitizeItems(getCached(CART_KEY)), // [{ product_id, slug, title, price, thumbnail, stock_qty, quantity }]
    // Set when the underlying storage.js set()/remove() call fails (e.g. the
    // native Preferences bridge errors out). Mirrors the error-field
    // convention already established by stores/booking.js (daysError,
    // bookingError) and stores/products.js (error) -- single field here
    // because cart.js has exactly one async concern (persistence), shared by
    // every action below.
    persistError: null,
    // pay() state (tasks 8.3-8.5): isPaying drives the CTA's loading state in
    // CartSummary.vue; payError surfaces either the client-side "cart is
    // empty" guard or a failed POST /checkout/handoff call. Same single-field
    // convention as persistError/bookingError -- pay() has exactly one async
    // concern.
    isPaying: false,
    payError: null,
  }),

  getters: {
    count: (state) => state.items.reduce((sum, item) => sum + item.quantity, 0),
    isEmpty: (state) => state.items.length === 0,
    subtotal: (state) =>
      state.items.reduce((sum, item) => sum + parseFloat(item.price) * item.quantity, 0),
  },

  actions: {
    async _persist() {
      this.persistError = null
      try {
        if (this.items.length === 0) {
          await remove(CART_KEY)
        } else {
          await set(CART_KEY, this.items)
        }
      } catch (err) {
        // Caught here (not re-thrown) so every caller below -- and, in turn,
        // CartItemRow.vue's fire-and-forget increment/decrement/remove
        // handlers, which do not await/.catch these actions -- never see an
        // unhandled promise rejection. The failure is recorded on
        // persistError instead, same shape as booking.js/products.js.
        console.error('Failed to persist cart to storage:', err)
        this.persistError = 'No se pudo guardar el carrito. Tus cambios podrían perderse.'
      }
    },

    async addItem(product) {
      // Blocked when out of stock — use numeric stock_qty so the guard works
      // even on hydrated items (stock_state is not persisted, same as web).
      const qty = Number(product.stock_qty)
      if (!Number.isFinite(qty) || qty <= 0) return

      const existing = this.items.find((i) => i.product_id === product.id)
      if (existing) {
        // Clamp to available stock
        existing.quantity = Math.min(existing.quantity + 1, product.stock_qty)
      } else {
        this.items.push({
          product_id: product.id,
          slug: product.slug,
          title: product.title,
          price: product.price,
          thumbnail: product.thumbnail ?? null,
          stock_qty: product.stock_qty,
          quantity: 1,
        })
      }
      await this._persist()
    },

    async removeItem(productId) {
      this.items = this.items.filter((i) => i.product_id !== productId)
      await this._persist()
    },

    async updateQuantity(productId, qty) {
      const item = this.items.find((i) => i.product_id === productId)
      if (!item) return
      item.quantity = Math.max(1, Math.min(qty, item.stock_qty))
      await this._persist()
    },

    async clear() {
      this.items = []
      await this._persist()
    },

    /**
     * "Pay" action (tasks 8.3-8.5). Snapshots the current cart into
     * POST /api/checkout/handoff and opens the returned resume URL via
     * @capacitor/browser's Browser.open() -- the system/in-app Browser
     * container, never the app's own router/WebView (spec's "Payment never
     * renders in the app's WebView" / "Handoff opens system/in-app browser
     * pre-loaded" scenarios).
     *
     * Guards against an empty cart client-side BEFORE contacting the
     * backend at all [Spec: cart empty at checkout tap] -- the backend's
     * own StoreCartCheckoutRequest also rejects an empty `items` array
     * (min:1), but that would mean a wasted round-trip and a generic 422
     * instead of the specific "cart is empty" message the spec calls for.
     */
    async pay() {
      this.payError = null

      if (this.isEmpty) {
        this.payError = 'El carrito está vacío.'
        return
      }

      this.isPaying = true
      try {
        // URL validation + Browser.open() live in services/checkoutHandoff.js,
        // shared with the booking-deposit and course-enrollment payers so the
        // security-sensitive guard has exactly one implementation.
        await startCheckoutHandoff({
          type: 'product_cart',
          items: this.items.map((item) => ({
            product_id: item.product_id,
            quantity: item.quantity,
          })),
        })
      } catch (err) {
        console.error('Failed to start checkout handoff:', err)
        this.payError =
          err.response?.data?.message || 'No se pudo iniciar el pago. Inténtalo de nuevo.'
      } finally {
        this.isPaying = false
      }
    },
  },
})
