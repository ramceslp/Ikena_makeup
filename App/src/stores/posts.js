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
  actions: {
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
  },
})
