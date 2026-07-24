import axios from 'axios'
import { getCached, remove, TOKEN_KEY, USER_KEY } from './storage.js'
import router from '../router/index.js'
import { API_BASE_URL } from '../config/env.js'

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// In-flight guard for the /login redirect (see handleResponseError below).
// Reset on the next outgoing request (attachAuthToken) rather than after
// router.push() resolves: it's synchronous, needs no promise bookkeeping,
// and by the time a new request goes out, the prior redirect burst is over.
let redirecting = false

/**
 * Request interceptor: injects the cached Bearer token (read synchronously
 * from storage.js's in-memory cache — see storage.js's hydrate()) into every
 * outgoing request.
 */
export function attachAuthToken(config) {
  redirecting = false
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
 * only the first 401 to arrive claims the redirect.
 */
export async function handleResponseError(error) {
  if (error.response?.status === 401) {
    const shouldRedirect = !redirecting && router.currentRoute.value.path !== '/login'
    if (shouldRedirect) {
      redirecting = true
    }

    await remove(TOKEN_KEY)
    await remove(USER_KEY)

    if (shouldRedirect) {
      router.push('/login')
    }
  }
  return Promise.reject(error)
}

api.interceptors.request.use(attachAuthToken)
api.interceptors.response.use((response) => response, handleResponseError)

export default api
