import axios from 'axios'
import { getCached, remove, TOKEN_KEY, USER_KEY } from './storage.js'
import router from '../router/index.js'
import { API_BASE_URL } from '../config/env.js'

const api = axios.create({
  baseURL: API_BASE_URL,
  // Without a timeout, a hung/half-open connection (e.g. a mobile network
  // that drops mid-request without an explicit reset) never rejects, which
  // Home.vue's connectivity probe and every other caller assume it will.
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// In-flight guard for the /login redirect (see handleResponseError below).
// Reset only after the specific router.push('/login') navigation it guards
// has settled (see the finally block below) — NOT on unrelated outgoing
// requests, which can intervene while the redirect is still in flight and
// would otherwise reopen the window for a second, duplicate redirect.
let redirecting = false

/**
 * Request interceptor: injects the cached Bearer token (read synchronously
 * from storage.js's in-memory cache — see storage.js's hydrate()) into every
 * outgoing request.
 */
export function attachAuthToken(config) {
  const token = getCached(TOKEN_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
}

/**
 * Response interceptor: on 401, clears the cached auth session and routes to
 * /login via the app's own Vue Router (never window.location.href, which
 * would force a full WebView reload). Includes a redirect-loop guard: if
 * we're already on /login (e.g. the failing request WAS the login attempt),
 * do not push again. The `redirecting` flag is decided and locked
 * synchronously, before the async remove() calls start, so that several
 * parallel requests failing around the same time on one expired token can't
 * each independently race the async router-navigation check and all push —
 * only the first 401 to arrive claims the redirect. The flag stays locked
 * until that same call's router.push('/login') navigation settles (see the
 * finally block), so unrelated requests firing while the redirect is still
 * in flight cannot reopen the window and trigger a duplicate redirect.
 *
 * Per-request opt-out: a request config with `skipAuthRedirect: true` (see
 * stores/push.js's opportunistic POST /device-tokens call) bypasses this
 * whole 401 handling block entirely -- no session clear, no redirect -- and
 * falls straight through to a plain rejection instead. That background call
 * runs unprompted at app boot/login and must never force-navigate the user
 * away from whatever screen they're actually on or clear a session that may
 * still be perfectly valid for every other in-flight/future request; the
 * caller's own catch block (push.js's _onToken) records the failure on its
 * own `error` state instead. Scoped narrowly to opt-in call sites only --
 * every other call site keeps the full logout+redirect behavior.
 */
export async function handleResponseError(error) {
  if (error.response?.status === 401 && !error.config?.skipAuthRedirect) {
    const shouldRedirect = !redirecting && router.currentRoute.value.path !== '/login'
    if (shouldRedirect) {
      redirecting = true
    }

    await remove(TOKEN_KEY)
    await remove(USER_KEY)

    if (shouldRedirect) {
      try {
        await router.push('/login')
      } catch (navigationError) {
        // Swallow navigation failures (e.g. a lazy-loaded /login route
        // component failing to dynamically import over a flaky mobile
        // connection). The original 401 below must still be the error
        // surfaced to the caller, not this unrelated navigation failure.
        console.error('Failed to redirect to /login after 401:', navigationError)
      } finally {
        redirecting = false
      }
    }
  }
  return Promise.reject(error)
}

api.interceptors.request.use(attachAuthToken)
api.interceptors.response.use((response) => response, handleResponseError)

export default api
