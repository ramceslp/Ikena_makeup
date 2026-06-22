import { defineStore } from 'pinia'
import api from '../services/api.js'

export const useCertificateSettingsStore = defineStore('certificateSettings', {
  state: () => ({
    settings: null,
    loading: false,
    error: null,
  }),

  actions: {
    /**
     * Public branding for the certificate canvas. Cached for the session —
     * a second call returns the cached value without another HTTP request.
     * Never throws to the canvas: on error leaves settings as-is and returns null.
     */
    async fetchSettings() {
      if (this.settings) return this.settings

      this.loading = true
      this.error = null
      try {
        const response = await api.get('/certificate-settings')
        this.settings = response.data.data
        return this.settings
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar la configuración del certificado'
        return null
      } finally {
        this.loading = false
      }
    },

    /** Admin form load — always hits the API (bypasses the cache). */
    async fetchAdminSettings() {
      this.loading = true
      this.error = null
      try {
        const response = await api.get('/admin/certificate-settings')
        this.settings = response.data.data
        return this.settings
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar la configuración del certificado'
        throw err
      } finally {
        this.loading = false
      }
    },

    /** Admin update (multipart for the logo). Refreshes the cached settings. */
    async updateSettings(formData) {
      const response = await api.post('/admin/certificate-settings', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      this.settings = response.data.data
      return this.settings
    },
  },
})
