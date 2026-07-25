import { defineStore } from 'pinia'
import api from '../services/api.js'
import { buildParams } from './shared/buildParams.js'

// Trimmed port of frontend/src/stores/products.js: fetchProducts() (Home's
// "3 most-recent products" section) plus fetchProduct()/fetchCategories()
// added in Phase 7 for ProductDetail.vue/Products.vue. Admin CRUD/upload
// actions are NOT ported -- no admin surface in this app (see spec's Mobile
// App Boundaries).
export const useProductsStore = defineStore('products', {
  state: () => ({
    products: [],
    productMeta: null,
    categories: [],
    currentProduct: null,
    loading: false,
    error: null,
  }),

  actions: {
    async fetchProducts(filters = {}) {
      this.loading = true
      this.error = null
      try {
        const params = buildParams(filters)
        const response = await api.get('/products', { params })
        this.products = response.data.data
        this.productMeta = response.data.meta
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar los productos'
      } finally {
        this.loading = false
      }
    },

    async fetchProduct(slug) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get(`/products/${slug}`)
        this.currentProduct = response.data.data
        return this.currentProduct
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar el producto'
        throw err
      } finally {
        this.loading = false
      }
    },

    async fetchCategories() {
      if (this.categories.length > 0) return
      try {
        const { data } = await api.get('/categories')
        this.categories = data.data ?? data
      } catch {
        // Leave categories empty — non-critical
      }
    },
  },
})
