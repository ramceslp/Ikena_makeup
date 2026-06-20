import { defineStore } from 'pinia'
import api from '../services/api.js'

const STORAGE_KEY = 'ikena_cart'

function loadFromStorage() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return []
    return JSON.parse(raw)
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
      // Blocked when out of stock
      if (product.stock_state === 'Agotado') return

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
      const response = await api.post('/cart/checkout', payload)
      this.clear()
      return response.data
    },
  },
})
