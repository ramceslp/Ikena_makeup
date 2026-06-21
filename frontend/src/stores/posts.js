import { defineStore } from 'pinia'
import api from '../services/api.js'

export const usePostsStore = defineStore('posts', {
  state: () => ({
    posts: [],
    postMeta: null,
    currentPost: null,
    loading: false,
    error: null,
    filters: {
      search: '',
      type: '',
      page: 1,
    },
  }),

  actions: {
    async fetchPosts(filters = {}) {
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
        const response = await api.get('/posts', { params })
        this.posts = response.data.data
        this.postMeta = response.data.meta
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar las noticias'
      } finally {
        this.loading = false
      }
    },

    async fetchPost(slug) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get(`/posts/${slug}`)
        this.currentPost = response.data.data
        return this.currentPost
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar la noticia'
        throw err
      } finally {
        this.loading = false
      }
    },

    async fetchLatest() {
      try {
        const response = await api.get('/posts/latest')
        return response.data.data
      } catch {
        return []
      }
    },

    async fetchFeatured() {
      try {
        const response = await api.get('/posts/featured')
        return response.data.data
      } catch {
        return null
      }
    },

    async fetchAdminPosts(filters = {}) {
      this.loading = true
      this.error = null
      try {
        // Build local params object — do NOT mutate this.filters
        const params = {}
        for (const [key, value] of Object.entries(filters)) {
          if (value !== '' && value !== null && value !== undefined) {
            params[key] = value
          }
        }
        const response = await api.get('/admin/posts', { params })
        this.posts = response.data.data
        this.postMeta = response.data.meta
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar las noticias'
      } finally {
        this.loading = false
      }
    },

    // Admin: fetch single post by id (includes drafts)
    async fetchAdminPost(id) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get(`/admin/posts/${id}`)
        this.currentPost = response.data.data
        return this.currentPost
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar la noticia'
        throw err
      } finally {
        this.loading = false
      }
    },

    async createPost(formData) {
      const response = await api.post('/admin/posts', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return response.data.data
    },

    async updatePost(id, formData) {
      const response = await api.post(`/admin/posts/${id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return response.data.data
    },

    async deletePost(id) {
      await api.delete(`/admin/posts/${id}`)
    },

    async uploadCover(id, file) {
      const fd = new FormData()
      fd.append('cover_image', file)
      const response = await api.post(`/admin/posts/${id}/cover`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return response.data.data
    },

    async deleteCover(id) {
      await api.delete(`/admin/posts/${id}/cover`)
    },

    async uploadImages(id, files) {
      const fd = new FormData()
      for (const file of files) {
        fd.append('images[]', file)
      }
      const response = await api.post(`/admin/posts/${id}/images`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return response.data.data
    },

    async deleteImage(id, imageId) {
      await api.delete(`/admin/posts/${id}/images/${imageId}`)
    },

    async reorderImages(id, order) {
      const response = await api.post(`/admin/posts/${id}/images/reorder`, { order })
      return response.data.data
    },
  },
})
