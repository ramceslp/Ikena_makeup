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
 * we're already on /login (e.g. the failing request WAS the login attempt,
 * or a second 401 races in before navigation settles), do not push again.
 */
export async function handleResponseError(error) {
  if (error.response?.status === 401) {
    await remove(TOKEN_KEY)
    await remove(USER_KEY)

    if (router.currentRoute.value.path !== '/login') {
      router.push('/login')
    }
  }
  return Promise.reject(error)
}

api.interceptors.request.use(attachAuthToken)
api.interceptors.response.use((response) => response, handleResponseError)

export default api
