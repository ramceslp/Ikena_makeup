import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// ---------------------------------------------------------------------------
// Mock api.js and storage.js BEFORE importing the store, so the store's
// actions drive controllable fakes instead of real HTTP/Preferences.
// ---------------------------------------------------------------------------
vi.mock('../services/api.js', () => ({
  default: {
    post: vi.fn(),
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
}))

vi.mock('../services/storage.js', () => ({
  set: vi.fn().mockResolvedValue(undefined),
  getCached: vi.fn(),
  TOKEN_KEY: 'ikena_auth_token',
  USER_KEY: 'ikena_user',
}))

import api from '../services/api.js'
import { set, getCached } from '../services/storage.js'
import { useAuthStore } from '../stores/auth.js'

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    getCached.mockReturnValue(null)
  })

  it('initializes token/user from the already-hydrated storage cache (see main.js bootstrap)', () => {
    getCached.mockImplementation((key) => (key === 'ikena_auth_token' ? 'cached-tok' : { id: 9, name: 'Cached User' }))

    const store = useAuthStore()

    expect(store.token).toBe('cached-tok')
    expect(store.user).toEqual({ id: 9, name: 'Cached User' })
  })

  it('isAuthenticated is false when there is no cached token', () => {
    const store = useAuthStore()
    expect(store.isAuthenticated).toBe(false)
  })

  it('isAuthenticated is true once a token is present', async () => {
    api.post.mockResolvedValueOnce({ data: { user: { id: 1 }, token: 'tok' } })
    const store = useAuthStore()

    await store.loginWithGoogle('plugin-id-token')

    expect(store.isAuthenticated).toBe(true)
  })

  it('loginWithGoogle posts the idToken to POST /api/auth/google', async () => {
    api.post.mockResolvedValueOnce({ data: { user: { id: 1 }, token: 'tok' } })
    const store = useAuthStore()

    await store.loginWithGoogle('plugin-id-token')

    expect(api.post).toHaveBeenCalledWith('/auth/google', { id_token: 'plugin-id-token' })
  })

  it('loginWithGoogle persists the returned session via storage.js and updates reactive state', async () => {
    const fakeUser = { id: 42, name: 'Ada Lovelace' }
    api.post.mockResolvedValueOnce({ data: { user: fakeUser, token: 'session-tok' } })
    const store = useAuthStore()

    await store.loginWithGoogle('plugin-id-token')

    expect(store.token).toBe('session-tok')
    expect(store.user).toEqual(fakeUser)
    expect(set).toHaveBeenCalledWith('ikena_auth_token', 'session-tok')
    expect(set).toHaveBeenCalledWith('ikena_user', fakeUser)
  })

  it('loginWithGoogle rejects and leaves the store unauthenticated when the backend call fails', async () => {
    api.post.mockRejectedValueOnce(new Error('network down'))
    const store = useAuthStore()

    await expect(store.loginWithGoogle('plugin-id-token')).rejects.toThrow('network down')

    expect(store.isAuthenticated).toBe(false)
    expect(set).not.toHaveBeenCalled()
  })
})
