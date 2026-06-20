import { defineStore } from 'pinia'
import api from '../services/api.js'

const STORAGE_KEY = 'ikena_cart'

function isValidItem(item) {
  if (!item || typeof item !== 'object') return false
  const price = parseFloat(item.price)
  const qty = Number(item.quantity)
  const pid = item.product_id
  return (
    pid != null &&
    Number.isFinite(price) &&
    Number.isFinite(qty) &&
    qty > 0
  )
}

function loadFromStorage() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    if (!Array.isArray(parsed)) return []
    // Drop malformed items so the subtotal getter can never produce NaN.
    return parsed.filter(isValidItem)
  } catch {
    return []
  }
}

function saveToStorage(items) {
  if (items.length === 0) {
    localStorage.removeItem(STORAGE_KEY)
  } else {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items))
  }
}

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: loadFromStorage(), // [{ product_id, slug, title, price, thumbnail, stock_qty, quantity }]
  }),

  getters: {
    count: (state) => state.items.reduce((sum, item) => sum + item.quantity, 0),
    isEmpty: (state) => state.items.length === 0,
    subtotal: (state) =>
      state.items.reduce((sum, item) => sum + parseFloat(item.price) * item.quantity, 0),
  },

  actions: {
    _persist() {
      saveToStorage(this.items)
    },

    addItem(product) {
      // Blocked when out of stock — use numeric stock_qty so the guard works even
      // on hydrated items (stock_state is not persisted to localStorage).
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
      this._persist()
    },

    removeItem(productId) {
      this.items = this.items.filter((i) => i.product_id !== productId)
      this._persist()
    },

    updateQuantity(productId, qty) {
      const item = this.items.find((i) => i.product_id === productId)
      if (!item) return
      item.quantity = Math.max(1, Math.min(qty, item.stock_qty))
      this._persist()
    },

    clear() {
      this.items = []
      this._persist()
    },

    async checkout() {
      const payload = {
        items: this.items.map((i) => ({ product_id: i.product_id, quantity: i.quantity })),
      }
      // NOTE: cart.clear() is intentionally NOT called here.
      // The view (Cart.vue) calls clear() only AFTER the PayPhone widget renders
      // successfully. This preserves the cart if asset injection or rendering fails.
      const response = await api.post('/cart/checkout', payload)
      return response.data
    },
  },
})
