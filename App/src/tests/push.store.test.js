import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// ---------------------------------------------------------------------------
// Mock api.js, storage.js, the native pushNotifications.js wrapper, and
// @capacitor/core BEFORE importing the store, so init()/unregister() drive
// controllable fakes instead of real HTTP/Preferences/native-plugin calls.
// Mirrors auth.store.test.js's/cart.store.test.js's mocking convention:
// the store mocks the *wrapper* service (services/pushNotifications.js),
// not the raw @capacitor/push-notifications plugin (that plugin's own
// service wrapper is unit-tested separately in pushNotifications.test.js).
// ---------------------------------------------------------------------------
vi.mock('../services/api.js', () => ({
  default: {
    post: vi.fn(),
    delete: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

vi.mock('../services/storage.js', () => ({
  get: vi.fn(),
  set: vi.fn().mockResolvedValue(undefined),
  remove: vi.fn().mockResolvedValue(undefined),
  getCached: vi.fn(),
  TOKEN_KEY: 'ikena_auth_token',
  PUSH_TOKEN_KEY: 'ikena_push_token',
}))

vi.mock('../services/pushNotifications.js', () => ({
  checkPushPermission: vi.fn(),
  requestPushPermission: vi.fn(),
  registerForPush: vi.fn(),
  addNotificationListeners: vi.fn(),
}))

// The store imports the router to open deep links on a notification tap.
// Delivery/deep-link behavior is covered in push.store.delivery.test.js.
vi.mock('../router/index.js', () => ({
  default: { push: vi.fn().mockResolvedValue(undefined) },
}))

vi.mock('@capacitor/core', () => ({
  Capacitor: {
    getPlatform: vi.fn().mockReturnValue('android'),
    isNativePlatform: vi.fn().mockReturnValue(true),
  },
}))

// This file exercises the store's normal (feature-enabled) behavior, so
// PUSH_ENABLED is mocked true regardless of the real build's
// VITE_PUSH_ENABLED value (unset in the Vitest env -- see vite.config.js --
// which would otherwise make PUSH_ENABLED false here too and short-circuit
// every test below via the guard added to init()). The disabled-flag guard
// itself is regression-tested separately in push.store.pushDisabled.test.js,
// where PUSH_ENABLED is mocked false instead.
vi.mock('../config/env.js', () => ({
  PUSH_ENABLED: true,
}))

import api from '../services/api.js'
import { get, set, remove, getCached } from '../services/storage.js'
import { checkPushPermission, requestPushPermission, registerForPush } from '../services/pushNotifications.js'
import { Capacitor } from '@capacitor/core'
import { usePushStore } from '../stores/push.js'

describe('push store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    getCached.mockReturnValue(null) // no cached auth token by default (not logged in)
    get.mockResolvedValue(null) // no previously-registered push token by default
    Capacitor.isNativePlatform.mockReturnValue(true) // native by default; web is opted into per-test below
  })

  // ── Web/dev-session gating ──────────────────────────────────────────────
  it('init() no-ops cleanly on web (Capacitor.isNativePlatform() === false): no permission check, no registration attempt, no error recorded', async () => {
    Capacitor.isNativePlatform.mockReturnValue(false)
    getCached.mockReturnValue('auth-tok') // even with a live session...

    const store = usePushStore()
    await store.init()

    expect(checkPushPermission).not.toHaveBeenCalled()
    expect(registerForPush).not.toHaveBeenCalled()
    expect(api.post).not.toHaveBeenCalled()
    expect(store.registered).toBe(false)
    expect(store.error).toBeNull() // web is an expected condition, not a failure
  })

  it('init() short-circuits with no permission check and no registration attempt when there is no authenticated session yet', async () => {
    getCached.mockReturnValue(null) // TOKEN_KEY not cached — not logged in

    const store = usePushStore()
    await store.init()

    expect(checkPushPermission).not.toHaveBeenCalled()
    expect(registerForPush).not.toHaveBeenCalled()
    expect(api.post).not.toHaveBeenCalled()
    expect(store.registered).toBe(false)
  })

  it('init() short-circuits when a device token was already successfully registered in a previous run', async () => {
    getCached.mockReturnValue('auth-tok')
    get.mockResolvedValue('previously-registered-fcm-token')

    const store = usePushStore()
    await store.init()

    expect(checkPushPermission).not.toHaveBeenCalled()
    expect(registerForPush).not.toHaveBeenCalled()
    expect(store.registered).toBe(true)
  })

  // ── Task 8.7: permission denied ─────────────────────────────────────────
  it('[Spec: push permission denied] continues normally with no registration attempt when the user denies push permission', async () => {
    getCached.mockReturnValue('auth-tok')
    checkPushPermission.mockResolvedValue('prompt')
    requestPushPermission.mockResolvedValue('denied')

    const store = usePushStore()
    await store.init()

    expect(requestPushPermission).toHaveBeenCalled()
    expect(registerForPush).not.toHaveBeenCalled()
    expect(api.post).not.toHaveBeenCalled()
    expect(store.permissionState).toBe('denied')
    expect(store.registered).toBe(false)
    expect(store.error).toBeNull() // denial is not an error state — the app continues normally
  })

  it('does not re-prompt when permission was already granted (checkPermissions alone reports granted)', async () => {
    getCached.mockReturnValue('auth-tok')
    checkPushPermission.mockResolvedValue('granted')
    registerForPush.mockResolvedValue(undefined)

    const store = usePushStore()
    await store.init()

    expect(requestPushPermission).not.toHaveBeenCalled()
    expect(registerForPush).toHaveBeenCalled()
  })

  it('registers the device token with the backend once the native plugin reports a token, and persists it', async () => {
    getCached.mockReturnValue('auth-tok')
    checkPushPermission.mockResolvedValue('granted')
    api.post.mockResolvedValue({ data: { data: { status: 'registered' } } })
    registerForPush.mockImplementation(async (onToken) => {
      await onToken('fresh-fcm-token')
    })

    const store = usePushStore()
    await store.init()

    expect(api.post).toHaveBeenCalledWith(
      '/device-tokens',
      {
        token: 'fresh-fcm-token',
        platform: 'android',
      },
      { skipAuthRedirect: true }
    )
    expect(set).toHaveBeenCalledWith('ikena_push_token', 'fresh-fcm-token')
    expect(store.registered).toBe(true)
    expect(store.error).toBeNull()
  })

  // ── Task 8.8: registration call fails, retries later, never blocks ─────
  it('[Spec: registration call fails] does not throw and does not persist a token when the backend POST /device-tokens call fails', async () => {
    getCached.mockReturnValue('auth-tok')
    checkPushPermission.mockResolvedValue('granted')
    api.post.mockRejectedValue(new Error('Network Error'))
    registerForPush.mockImplementation(async (onToken) => {
      await onToken('fresh-fcm-token')
    })

    const store = usePushStore()
    await expect(store.init()).resolves.toBeUndefined()

    expect(set).not.toHaveBeenCalled()
    expect(store.registered).toBe(false)
    expect(store.error).not.toBeNull()
  })

  it('[Spec: registration call fails] retries on the next init() call (e.g. next app boot) after a previous backend failure, since nothing was persisted', async () => {
    getCached.mockReturnValue('auth-tok')
    checkPushPermission.mockResolvedValue('granted')
    registerForPush.mockImplementation(async (onToken) => {
      await onToken('fresh-fcm-token')
    })

    // First attempt: backend rejects.
    api.post.mockRejectedValueOnce(new Error('Network Error'))
    const store = usePushStore()
    await store.init()
    expect(store.registered).toBe(false)

    // Simulate "next app boot": get(PUSH_TOKEN_KEY) still resolves null
    // because nothing was persisted after the failure above.
    get.mockResolvedValue(null)
    api.post.mockResolvedValueOnce({ data: { data: { status: 'registered' } } })
    await store.init()

    expect(api.post).toHaveBeenCalledTimes(2)
    expect(store.registered).toBe(true)
  })

  it('does not throw and records the error when the native register() call itself rejects', async () => {
    getCached.mockReturnValue('auth-tok')
    checkPushPermission.mockResolvedValue('granted')
    registerForPush.mockRejectedValue(new Error('Google Play Services unavailable'))

    const store = usePushStore()
    await expect(store.init()).resolves.toBeUndefined()

    expect(api.post).not.toHaveBeenCalled()
    expect(store.registered).toBe(false)
    expect(store.error).not.toBeNull()
  })

  it('does not throw and records the error when the permission check itself rejects', async () => {
    getCached.mockReturnValue('auth-tok')
    checkPushPermission.mockRejectedValue(new Error('plugin unavailable'))

    const store = usePushStore()
    await expect(store.init()).resolves.toBeUndefined()

    expect(registerForPush).not.toHaveBeenCalled()
    expect(store.error).not.toBeNull()
  })

  it('the native registrationError listener path never blocks other app features (never throws)', async () => {
    getCached.mockReturnValue('auth-tok')
    checkPushPermission.mockResolvedValue('granted')
    registerForPush.mockImplementation(async (onToken, onError) => {
      onError({ error: 'no Google Play Services' })
    })

    const store = usePushStore()
    await expect(store.init()).resolves.toBeUndefined()

    expect(api.post).not.toHaveBeenCalled()
    expect(store.registered).toBe(false)
    expect(store.error).not.toBeNull()
  })

  // ── unregister() — DELETE /api/device-tokens ────────────────────────────
  it('unregister() is a no-op when no device token was ever persisted', async () => {
    get.mockResolvedValue(null)

    const store = usePushStore()
    await store.unregister()

    expect(api.delete).not.toHaveBeenCalled()
  })

  it('unregister() calls DELETE /api/device-tokens with the persisted token and clears it', async () => {
    get.mockResolvedValue('registered-fcm-token')
    api.delete.mockResolvedValue({ data: { data: { status: 'removed' } } })

    const store = usePushStore()
    store.registered = true
    await store.unregister()

    expect(api.delete).toHaveBeenCalledWith('/device-tokens', { data: { token: 'registered-fcm-token' } })
    expect(remove).toHaveBeenCalledWith('ikena_push_token')
    expect(store.registered).toBe(false)
  })

  it('unregister() still clears the persisted token even when the DELETE call fails (never throws)', async () => {
    get.mockResolvedValue('registered-fcm-token')
    api.delete.mockRejectedValue(new Error('Network Error'))

    const store = usePushStore()
    await expect(store.unregister()).resolves.toBeUndefined()

    expect(remove).toHaveBeenCalledWith('ikena_push_token')
    expect(store.registered).toBe(false)
  })
})
