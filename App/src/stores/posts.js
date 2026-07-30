import { defineStore } from 'pinia'
import api from '../services/api.js'

// Read-only port of frontend/src/stores/posts.js. Admin CRUD/upload actions
// are deliberately NOT ported -- the app has no admin surface at all (see
// spec's "Admin/instructor views excluded from app shell" non-goal).
//
// fetchPosts()/fetchPost() were added in push-notifications Slice 5b, which
// introduced the /noticias and /noticias/:slug routes. Before that the app
// only had Home.vue's two sections (FeaturedNewsHero -> fetchFeatured,
// LatestNewsGrid -> fetchLatest), yet both of those already linked to
// /noticias/{slug} -- a route that did not exist, so "Leer más" was a dead
// tap. The news push notification's deep link targets the same path.
export const usePostsStore = defineStore('posts', {
  // Brought in line with courses.js/services.js/products.js's loading/error
  // tracking convention. This is purely additive state -- fetchLatest() and
  // fetchFeatured() keep returning `[]`/`null` on failure exactly as before,
  // so existing callers (FeaturedNewsHero.vue, LatestNewsGrid.vue) are
  // unaffected; only `loading`/`error` become observable for anyone that
  // wants to react to them, same as the sibling stores.
  state: () => ({
    posts: [],
    meta: null,
    currentPost: null,
    loading: false,
    error: null,
  }),

  actions: {
    /**
     * Paginated public news list. The API accepts only `search` and `page`
     * (see backend Api\PostController::index) — there is no type filter, so
     * the view does not offer one.
     */
    async fetchPosts({ search = '', page = 1 } = {}) {
      this.loading = true
      this.error = null
      try {
        // Params built locally rather than from shared state, so a later
        // unfiltered call cannot silently inherit an earlier search term.
        const params = { page }
        if (search) params.search = search

        const response = await api.get('/posts', { params })
        this.posts = response.data.data
        this.meta = response.data.meta
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar las noticias'
        this.posts = []
        this.meta = null
      } finally {
        this.loading = false
      }
    },

    /**
     * Single published post by slug. Rethrows so the detail view can tell a
     * genuine 404 apart from an empty result — unlike the list fetchers
     * above, whose callers only render a state.
     */
    async fetchPost(slug) {
      this.loading = true
      this.error = null
      this.currentPost = null
      try {
        const response = await api.get(`/posts/${slug}`)
        this.currentPost = response.data.data
        return this.currentPost
      } catch (err) {
        this.error =
          err.response?.status === 404
            ? 'No encontramos esta noticia.'
            : err.response?.data?.message || 'Error al cargar la noticia'
        throw err
      } finally {
        this.loading = false
      }
    },

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
