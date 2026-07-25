import { defineStore } from 'pinia'
import api from '../services/api.js'

// Trimmed port of frontend/src/stores/posts.js: only the two read-only
// fetchers Home.vue's ported sections actually call (FeaturedNewsHero uses
// fetchFeatured, LatestNewsGrid uses fetchLatest). The paginated
// fetchPosts()/fetchPost() and every admin CRUD/upload action are NOT
// ported -- the app exposes no news list/detail route yet and has no admin
// surface at all (see spec's "Admin/instructor views excluded from app
// shell" non-goal).
export const usePostsStore = defineStore('posts', {
  // Brought in line with courses.js/services.js/products.js's loading/error
  // tracking convention. This is purely additive state -- fetchLatest() and
  // fetchFeatured() keep returning `[]`/`null` on failure exactly as before,
  // so existing callers (FeaturedNewsHero.vue, LatestNewsGrid.vue) are
  // unaffected; only `loading`/`error` become observable for anyone that
  // wants to react to them, same as the sibling stores.
  state: () => ({
    loading: false,
    error: null,
  }),

  actions: {
    async fetchLatest() {
      this.loading = true
      this.error = null
      try {
        const response = await api.get('/posts/latest')
        return response.data.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar las noticias'
        return []
      } finally {
        this.loading = false
      }
    },

    async fetchFeatured() {
      this.loading = true
      this.error = null
      try {
        const response = await api.get('/posts/featured')
        return response.data.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar la noticia destacada'
        return null
      } finally {
        this.loading = false
      }
    },
  },
})
