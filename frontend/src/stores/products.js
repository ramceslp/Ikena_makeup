import { defineStore } from 'pinia'
import api from '../services/api.js'

export const useProductsStore = defineStore('products', {
  state: () => ({
    products: [],
    productMeta: null,
    categories: [],
    filters: {
      search: '',
      min_price: '',
      max_price: '',
      sort: 'newest',
      page: 1,
      category: '',
      stock_state: '',
    },
    currentProduct: null,
    loading: false,
    error: null,
  }),

  actions: {
    async fetchProducts(filters = {}) {
      this.loading = true
      this.error = null
      try {
        const merged = { ...this.filters, ...filters }
        this.filters = merged
        // Strip empty/null/undefined values so they don't pollute the query string
        const params = {}
        for (const [key, value] of Object.entries(merged)) {
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

    // Admin actions

    async createProduct(formData) {
      const response = await api.post('/admin/products', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return response.data.data
    },

    async updateProduct(id, formData) {
      const response = await api.post(`/admin/products/${id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return response.data.data
    },

    async deleteProduct(id) {
      await api.delete(`/admin/products/${id}`)
    },

    async uploadImages(id, files) {
      const fd = new FormData()
      for (const file of files) {
        fd.append('images[]', file)
      }
      const response = await api.post(`/admin/products/${id}/images`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return response.data.data
    },

    async deleteImage(id, imageId) {
      await api.delete(`/admin/products/${id}/images/${imageId}`)
    },

    async reorderImages(id, order) {
      const response = await api.post(`/admin/products/${id}/images/reorder`, { order })
      return response.data.data
    },

    async createProductWithImages(formData, files) {
      const created = await this.createProduct(formData)
      if (files && files.length > 0) {
        await this.uploadImages(created.id, files)
      }
      return created
    },
  },
})
