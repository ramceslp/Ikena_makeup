import { defineStore } from 'pinia'
import api from '../services/api.js'
import { set, getCached, TOKEN_KEY, USER_KEY } from '../services/storage.js'

// Trimmed port of frontend/src/stores/auth.js: the app only supports native
// Google Sign-In (no email/password login/register -- see spec's "Native
// Google Sign-In and bearer session" requirement, which is the sole
// authentication method described for the app), so only loginWithGoogle()
// is ported. Profile/orders/password actions belong to a Profile surface
// that lands in a later phase (see tasks.md Phase 8).
export const useAuthStore = defineStore('auth', {
  state: () => ({
    // Read from storage.js's in-memory cache, which main.js's bootstrap()
    // already warmed via hydrate() BEFORE the app (and therefore Pinia) is
    // mounted -- see services/storage.js. This mirrors the frontend store's
    // synchronous localStorage read, adapted for Preferences' async API.
    user: getCached(USER_KEY),
    token: getCached(TOKEN_KEY),
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
  },

  actions: {
    async _persist(user, token) {
      this.user = user
      this.token = token
      await set(TOKEN_KEY, token)
      await set(USER_KEY, user)
    },

    /**
     * Posts the native Google Sign-In plugin's idToken to the existing
     * POST /api/auth/google endpoint and persists the returned Sanctum
     * bearer token + user via storage.js. See services/googleAuth.js for
     * how idToken is obtained (and how a user cancellation is kept separate
     * from this action entirely -- Login.vue never calls this on cancel).
     */
    async loginWithGoogle(idToken) {
      const response = await api.post('/auth/google', { id_token: idToken })
      const { user, token } = response.data
      await this._persist(user, token)
      return response.data
    },
  },
})
