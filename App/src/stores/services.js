import { defineStore } from 'pinia'
import api from '../services/api.js'
import { buildParams } from './shared/buildParams.js'

// Trimmed port of frontend/src/stores/services.js: fetchServices() (Home's
// "3 most-recent services" section) plus fetchService()/fetchCategories()
// added in Phase 7 for ServiceDetail.vue/Services.vue. Admin CRUD/upload
// actions are NOT ported -- no admin surface in this app (see spec's Mobile
// App Boundaries).
export const useServicesStore = defineStore('services', {
  state: () => ({
    services: [],
    serviceMeta: null,
    categories: [],
    currentService: null,
    loading: false,
    error: null,
  }),

  actions: {
    async fetchServices(filters = {}) {
      this.loading = true
      this.error = null
      try {
        const params = buildParams(filters)
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
  },
})
