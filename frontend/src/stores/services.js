import { defineStore } from 'pinia'
import api from '../services/api.js'

export const useServicesStore = defineStore('services', {
  state: () => ({
    services: [],
    serviceMeta: null,
    categories: [],
    filters: {
      search: '',
      min_price: '',
      max_price: '',
      sort: 'newest',
      page: 1,
      category: '',
      availability_type: '',
    },
    currentService: null,
    loading: false,
    error: null,
  }),

  actions: {
    async fetchServices(filters = {}) {
      this.loading = true
      this.error = null
      try {
        // Build a local merged view only. Do NOT persist into this.filters:
        // mutating shared state contaminates other consumers (a featured
        // section's per_page would leak into the /services catalog). See posts.js.
        const merged = { ...this.filters, ...filters }
        // Strip empty/null/undefined values so they don't pollute query string
        const params = {}
        for (const [key, value] of Object.entries(merged)) {
          if (value !== '' && value !== null && value !== undefined) {
            params[key] = value
          }
        }
        const response = await api.get('/services', { params })
        this.services = response.data.data
        this.serviceMeta = response.data.meta
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar los servicios'
      } finally {
        this.loading = false
      }
    },

    async fetchService(slug) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get(`/services/${slug}`)
        this.currentService = response.data.data
        return this.currentService
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar el servicio'
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

    async createService(formData) {
      const response = await api.post('/admin/services', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return response.data.data
    },

    async updateService(id, formData) {
      const response = await api.post(`/admin/services/${id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return response.data.data
    },

    async deleteService(id) {
      await api.delete(`/admin/services/${id}`)
    },

    async uploadImages(id, files) {
      const fd = new FormData()
      for (const file of files) {
        fd.append('images[]', file)
      }
      const response = await api.post(`/admin/services/${id}/images`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return response.data.data
    },

    async deleteImage(id, imageId) {
      await api.delete(`/admin/services/${id}/images/${imageId}`)
    },

    async reorderImages(id, order) {
      const response = await api.patch(`/admin/services/${id}/images/reorder`, { order })
      return response.data.data
    },

    async fetchAdminServices() {
      this.loading = true
      this.error = null
      try {
        const response = await api.get('/admin/services')
        this.services = response.data.data
        this.serviceMeta = response.data.meta
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar los servicios'
      } finally {
        this.loading = false
      }
    },

    async fetchAdminService(id) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get(`/admin/services/${id}`)
        this.currentService = response.data.data
        return this.currentService
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar el servicio'
        throw err
      } finally {
        this.loading = false
      }
    },

    async createServiceWithImages(formData, files) {
      const created = await this.createService(formData)
      if (files && files.length > 0) {
        await this.uploadImages(created.id, files)
      }
      return created
    },
  },
})
