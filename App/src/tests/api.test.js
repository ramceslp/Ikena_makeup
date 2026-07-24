import { describe, it, expect, vi, beforeEach } from 'vitest'

// ---------------------------------------------------------------------------
// Mock storage.js and the router BEFORE importing api.js, so the interceptor
// logic under test receives controllable fakes instead of the real Preferences
// bridge / a mounted Vue Router instance.
// ---------------------------------------------------------------------------
vi.mock('../services/storage.js', () => ({
  getCached: vi.fn(),
  remove: vi.fn(),
  TOKEN_KEY: 'ikena_auth_token',
  USER_KEY: 'ikena_user',
}))

vi.mock('../router/index.js', () => ({
  default: {
    push: vi.fn(),
    currentRoute: { value: { path: '/' } },
  },
}))

import { getCached, remove } from '../services/storage.js'
import router from '../router/index.js'
import { attachAuthToken, handleResponseError } from '../services/api.js'

describe('api.js interceptors', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    router.currentRoute.value.path = '/'
  })

  describe('attachAuthToken (request interceptor)', () => {
    it('adds an Authorization Bearer header when a cached token exists', () => {
      getCached.mockReturnValue('cached-token-123')

      const config = attachAuthToken({ headers: {} })

      expect(config.headers.Authorization).toBe('Bearer cached-token-123')
    })

    it('does not add an Authorization header when there is no cached token', () => {
      getCached.mockReturnValue(null)

      const config = attachAuthToken({ headers: {} })

      expect(config.headers.Authorization).toBeUndefined()
    })
  })

  describe('handleResponseError (response interceptor)', () => {
    it('on 401, clears the cached token and user from storage', async () => {
      const error = { response: { status: 401 } }

      await expect(handleResponseError(error)).rejects.toBe(error)

      expect(remove).toHaveBeenCalledWith('ikena_auth_token')
      expect(remove).toHaveBeenCalledWith('ikena_user')
    })

    it('on 401, redirects to /login when not already there', async () => {
      router.currentRoute.value.path = '/cart'
      const error = { response: { status: 401 } }

      await expect(handleResponseError(error)).rejects.toBe(error)

      expect(router.push).toHaveBeenCalledWith('/login')
    })

    it('on 401, does NOT push again when already on /login (redirect-loop guard)', async () => {
      router.currentRoute.value.path = '/login'
      const error = { response: { status: 401 } }

      await expect(handleResponseError(error)).rejects.toBe(error)

      expect(router.push).not.toHaveBeenCalled()
    })

    it('on a non-401 error, does not clear storage or navigate, and still rejects', async () => {
      const error = { response: { status: 500 } }

      await expect(handleResponseError(error)).rejects.toBe(error)

      expect(remove).not.toHaveBeenCalled()
      expect(router.push).not.toHaveBeenCalled()
    })
  })
})
