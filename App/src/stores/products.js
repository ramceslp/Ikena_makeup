import { defineStore } from 'pinia'
import api from '../services/api.js'

// Trimmed port of frontend/src/stores/products.js: only fetchProducts(),
// needed by FeaturedProducts.vue for Home's "3 most-recent products"
// section. fetchProduct (detail) is added in Phase 7 alongside
// ProductDetail.vue; admin CRUD/upload actions are not ported (no admin
// surface in this app -- see spec's Mobile App Boundaries).
export const useProductsStore = defineStore('products', {
  state: () => ({
    products: [],
    productMeta: null,
    loading: false,
    error: null,
  }),

  actions: {
    async fetchProducts(filters = {}) {
      this.loading = true
      this.error = null
      try {
        const params = {}
        for (const [key, value] of Object.entries(filters)) {
          if (value !== '' && value !== null && value !== undefined) {
            params[key] = value
          }
        }
        const response = await api.get('/products', { params })
        this.products = response.data.data
        this.productMeta = response.data.meta
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar los productos'
      } finally {
        this.loading = false
      }
    },
  },
})
