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
    // The module-level `redirecting` guard (see api.js) is only reset once
    // its own router.push('/login') navigation settles, which each 401 test
    // below already awaits to completion — so no explicit reset is needed
    // here between tests.
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

    it('on concurrent 401s (parallel requests racing on one expired token), only pushes to /login once', async () => {
      router.currentRoute.value.path = '/cart'
      const errorA = { response: { status: 401 } }
      const errorB = { response: { status: 401 } }

      const [resultA, resultB] = await Promise.allSettled([
        handleResponseError(errorA),
        handleResponseError(errorB),
      ])

      expect(resultA.status).toBe('rejected')
      expect(resultB.status).toBe('rejected')
      expect(router.push).toHaveBeenCalledTimes(1)
      expect(router.push).toHaveBeenCalledWith('/login')
    })

    it('on a non-401 error, does not clear storage or navigate, and still rejects', async () => {
      const error = { response: { status: 500 } }

      await expect(handleResponseError(error)).rejects.toBe(error)

      expect(remove).not.toHaveBeenCalled()
      expect(router.push).not.toHaveBeenCalled()
    })

    it('does not re-trigger the redirect when an unrelated outgoing request intervenes before the in-flight redirect navigation settles (staggered 401 race)', async () => {
      router.currentRoute.value.path = '/cart'
      let resolvePush
      router.push.mockImplementation(
        () =>
          new Promise((resolve) => {
            resolvePush = resolve
          })
      )

      const errorA = { response: { status: 401 } }
      const errorB = { response: { status: 401 } }

      // First 401 arrives: arms the guard and kicks off the /login
      // navigation, but that navigation's promise is not yet resolved.
      const handledA = handleResponseError(errorA).catch(() => {})

      // Flush microtasks so the arm + the two awaited remove() calls run,
      // reaching the point where router.push('/login') has been invoked
      // but has not settled yet.
      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()
      expect(router.push).toHaveBeenCalledTimes(1)

      // An unrelated outgoing request fires while errorA's redirect
      // navigation is still in flight — this must NOT reset the guard.
      attachAuthToken({ headers: {} })

      // A second, staggered 401 lands before the first redirect settles.
      const handledB = handleResponseError(errorB).catch(() => {})
      await Promise.resolve()

      // Now let the first navigation settle.
      resolvePush()
      await handledA
      await handledB

      expect(router.push).toHaveBeenCalledTimes(1)
    })
  })
})
