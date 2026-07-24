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
      try {
        await router.push('/login')
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
