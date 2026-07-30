import { defineStore } from 'pinia'
import api from '../services/api.js'
import { set, remove, getCached, TOKEN_KEY, USER_KEY } from '../services/storage.js'
import { usePushStore } from './push.js'

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

      // Push registration (tasks 8.6-8.8) requires an authenticated session
      // (POST /device-tokens is auth:sanctum), so a fresh login is one of
      // its two trigger points (the other is app boot for an already-
      // cached session — see main.js's bootstrap()). Deliberately NOT
      // awaited: push.js's init() never throws (every internal failure
      // path is caught and recorded on its own `error` state — see
      // stores/push.js), but a stray rejection is still guarded against
      // here defensively so a future change to that contract can never
      // turn a successful login into a rejected loginWithGoogle() call.
      usePushStore()
        .init()
        .catch((err) => {
          console.error('Push registration failed after login:', err)
        })

      return response.data
    },

    /**
     * Ends the session. Ported from frontend/src/stores/auth.js's logout(),
     * with the web's two localStorage.removeItem() calls replaced by
     * storage.js's remove() on the same Preferences-backed keys (which also
     * evicts them from the in-memory cache the Axios request interceptor
     * reads — see services/storage.js).
     *
     * Surfaced ONLY from the Profile screen, never from the bottom tab bar:
     * logout is destructive-adjacent and must stay spatially separated from
     * ordinary navigation items.
     *
     * Contract: this action NEVER rejects. Local state is cleared no matter
     * what, because:
     *   - POST /api/logout is best-effort. Offline, or with an already-expired
     *     token, it fails — but the user still asked to sign out, and leaving
     *     a dead token cached would just strand them on a broken session.
     *   - remove() can reject if the native Preferences bridge errors out.
     *     Reactive state is already cleared by then, so the user is visually
     *     signed out; a rethrow here would only hand the caller an unhandled
     *     rejection. Same "record, don't propagate" convention as
     *     stores/cart.js's _persist().
     *
     * Deliberately does NOT touch stores/push.js. Its init() crashes the
     * native process when Firebase is unconfigured, and there is no
     * device-token revocation step in the app's contract.
     */
    async logout() {
      try {
        // Revokes the Sanctum bearer token server-side. Awaited BEFORE the
        // token is dropped from state/storage, because the Axios request
        // interceptor reads that same cached token to authorize this call.
        await api.post('/logout')
      } catch (err) {
        console.error('Server-side logout failed; clearing local session anyway:', err)
      }

      this.user = null
      this.token = null

      try {
        await Promise.all([remove(TOKEN_KEY), remove(USER_KEY)])
      } catch (err) {
        console.error('Failed to clear the persisted session from storage:', err)
      }
    },
  },
})
