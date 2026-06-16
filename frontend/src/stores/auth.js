import { defineStore } from 'pinia'
import api from '../services/api.js'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('auth_user') || 'null'),
    token: localStorage.getItem('auth_token') || null,
    orders: [],
    ordersMeta: null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
  },

  actions: {
    _persist(user, token) {
      this.user = user
      this.token = token
      localStorage.setItem('auth_token', token)
      localStorage.setItem('auth_user', JSON.stringify(user))
    },

    _setUser(user) {
      this.user = user
      localStorage.setItem('auth_user', JSON.stringify(user))
    },

    async register(payload) {
      const response = await api.post('/register', payload)
      const { user, token } = response.data
      this._persist(user, token)
      return response.data
    },

    async login(payload) {
      const response = await api.post('/login', payload)
      const { user, token } = response.data
      this._persist(user, token)
      return response.data
    },

    async loginWithGoogle(idToken) {
      const response = await api.post('/auth/google', { id_token: idToken })
      const { user, token } = response.data
      this._persist(user, token)
      return response.data
    },

    async fetchMe() {
      const response = await api.get('/me')
      this.user = response.data.data
      localStorage.setItem('auth_user', JSON.stringify(this.user))
      return this.user
    },

    async logout() {
      try {
        await api.post('/logout')
      } catch {
        // ignore errors on logout
      } finally {
        this.user = null
        this.token = null
        localStorage.removeItem('auth_token')
        localStorage.removeItem('auth_user')
      }
    },

    async updateProfile(formData) {
      const { data } = await api.post('/profile', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      this._setUser(data.data ?? data)
      return this.user
    },

    async changePassword({ current_password, password, password_confirmation }) {
      const { data } = await api.put('/profile/password', { current_password, password, password_confirmation })
      return data
    },

    async fetchOrders() {
      const { data } = await api.get('/profile/orders')
      this.orders = data.data ?? data
      this.ordersMeta = data.meta ?? null
    },
  },
})
