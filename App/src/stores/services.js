import { defineStore } from 'pinia'
import api from '../services/api.js'
import { buildParams } from './shared/buildParams.js'

// Trimmed port of frontend/src/stores/services.js: only fetchServices(),
// needed by FeaturedServices.vue for Home's "3 most-recent services"
// section. fetchService (detail) is added in Phase 7 alongside
// ServiceDetail.vue/booking; admin CRUD/upload actions are not ported (no
// admin surface in this app -- see spec's Mobile App Boundaries).
export const useServicesStore = defineStore('services', {
  state: () => ({
    services: [],
    serviceMeta: null,
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
  },
})
