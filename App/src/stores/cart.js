import { defineStore } from 'pinia'
import { set, remove, getCached, CART_KEY } from '../services/storage.js'

// Ported from frontend/src/stores/cart.js (mobile-capacitor-setup Phase 8,
// tasks 8.1/8.2). Business logic (add/remove/update-quantity/subtotal/clear)
// is a direct port; persistence is adapted from synchronous localStorage to
// storage.js's async Preferences-backed API, following the same pattern
// already established by stores/auth.js: initial state is read synchronously
// from storage.js's in-memory cache, which main.js's bootstrap() warms via
// hydrate() BEFORE the app is mounted (see services/storage.js hydrate()).
//
// checkout() is intentionally NOT ported here. The web version posts
// directly to /cart/checkout and renders the PayPhone widget inline
// (Cart.vue) -- that pattern would render payment UI inside this app's own
// WebView, which the spec's Mobile App Boundaries explicitly forbid ("MUST
// NOT render any payment UI inside the app's own WebView"). The app's
// equivalent "pay" action instead calls the checkout-handoff endpoint and
// opens the result via @capacitor/browser (design.md Decision 1) -- that
// action, plus the empty-cart guard and browser-isolation tests, are tasks
// 8.3-8.5, a separate PR (8b), not part of this port.
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
  }),

  getters: {
    count: (state) => state.items.reduce((sum, item) => sum + item.quantity, 0),
    isEmpty: (state) => state.items.length === 0,
    subtotal: (state) =>
      state.items.reduce((sum, item) => sum + parseFloat(item.price) * item.quantity, 0),
  },

  actions: {
    async _persist() {
      if (this.items.length === 0) {
        await remove(CART_KEY)
      } else {
        await set(CART_KEY, this.items)
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
  },
})
