import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// ---------------------------------------------------------------------------
// Mock the axios api module BEFORE importing the store.
// The store imports '../services/api.js' which is resolved relative to the
// store file at src/stores/auth.js, so the mock path must match what the
// module resolver sees from the test file's perspective.
// ---------------------------------------------------------------------------
vi.mock('../services/api.js', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
}))

// Import AFTER mocking so the store receives the mock instance.
import api from '../services/api.js'
import { useAuthStore } from '../stores/auth.js'

// ---------------------------------------------------------------------------
// localStorage stub
// ---------------------------------------------------------------------------
const localStorageMock = (() => {
  let store = {}
  return {
    getItem: (key) => store[key] ?? null,
    setItem: (key, value) => { store[key] = String(value) },
    removeItem: (key) => { delete store[key] },
    clear: () => { store = {} },
  }
})()

Object.defineProperty(globalThis, 'localStorage', {
  value: localStorageMock,
  writable: true,
})

// ---------------------------------------------------------------------------
// Test suite
// ---------------------------------------------------------------------------

describe('auth store', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  // -------------------------------------------------------------------------
  // login
  // -------------------------------------------------------------------------

  it('login stores token and user in state and localStorage', async () => {
    const fakeUser  = { id: 1, name: 'Test User', email: 'test@example.com', role: 'student' }
    const fakeToken = 'abc123token'

    api.post.mockResolvedValueOnce({ data: { user: fakeUser, token: fakeToken } })

    const store = useAuthStore()
    await store.login({ email: 'test@example.com', password: 'secret' })

    expect(store.user).toEqual(fakeUser)
    expect(store.token).toBe(fakeToken)
    expect(localStorage.getItem('auth_token')).toBe(fakeToken)
    expect(JSON.parse(localStorage.getItem('auth_user'))).toEqual(fakeUser)
  })

  it('login calls POST /login with the correct payload', async () => {
    api.post.mockResolvedValueOnce({
      data: { user: { id: 1 }, token: 'token' },
    })

    const store = useAuthStore()
    await store.login({ email: 'a@b.com', password: 'pass' })

    expect(api.post).toHaveBeenCalledWith('/login', { email: 'a@b.com', password: 'pass' })
  })

  // -------------------------------------------------------------------------
  // logout
  // -------------------------------------------------------------------------

  it('logout clears token and user from state and localStorage', async () => {
    // Pre-load state as if logged in
    localStorage.setItem('auth_token', 'existing-token')
    localStorage.setItem('auth_user', JSON.stringify({ id: 1 }))

    api.post.mockResolvedValueOnce({}) // POST /logout succeeds

    const store = useAuthStore()
    // Force state to match localStorage values
    store.token = 'existing-token'
    store.user  = { id: 1 }

    await store.logout()

    expect(store.user).toBeNull()
    expect(store.token).toBeNull()
    expect(localStorage.getItem('auth_token')).toBeNull()
    expect(localStorage.getItem('auth_user')).toBeNull()
  })

  it('logout clears state even when API call fails', async () => {
    api.post.mockRejectedValueOnce(new Error('Network error'))

    const store = useAuthStore()
    store.token = 'some-token'
    store.user  = { id: 2 }

    await store.logout()

    expect(store.user).toBeNull()
    expect(store.token).toBeNull()
  })

  // -------------------------------------------------------------------------
  // isAuthenticated getter
  // -------------------------------------------------------------------------

  it('isAuthenticated is false when token is null', () => {
    const store = useAuthStore()
    store.token = null
    expect(store.isAuthenticated).toBe(false)
  })

  it('isAuthenticated is true when token is set', () => {
    const store = useAuthStore()
    store.token = 'some-token'
    expect(store.isAuthenticated).toBe(true)
  })

  // -------------------------------------------------------------------------
  // loginWithGoogle
  // -------------------------------------------------------------------------

  it('loginWithGoogle calls POST /auth/google with id_token', async () => {
    const fakeUser  = { id: 5, name: 'Google User', email: 'g@example.com', role: 'student' }
    const fakeToken = 'google-token-xyz'

    api.post.mockResolvedValueOnce({ data: { user: fakeUser, token: fakeToken } })

    const store = useAuthStore()
    await store.loginWithGoogle('google-id-token-from-js-sdk')

    expect(api.post).toHaveBeenCalledWith('/auth/google', { id_token: 'google-id-token-from-js-sdk' })
    expect(store.token).toBe(fakeToken)
    expect(store.user).toEqual(fakeUser)
  })

  // -------------------------------------------------------------------------
  // register
  // -------------------------------------------------------------------------

  it('register persists token and user after successful call', async () => {
    const fakeUser  = { id: 10, name: 'New User', email: 'new@example.com', role: 'student' }
    const fakeToken = 'register-token'

    api.post.mockResolvedValueOnce({ data: { user: fakeUser, token: fakeToken } })

    const store = useAuthStore()
    await store.register({ name: 'New User', email: 'new@example.com', password: 'pass' })

    expect(store.token).toBe(fakeToken)
    expect(store.user).toEqual(fakeUser)
  })
})
