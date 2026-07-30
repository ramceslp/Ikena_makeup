import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// ---------------------------------------------------------------------------
// Regression guard for the process-killing crash: PushNotifications.register()
// throws natively (on Capacitor's CapacitorPlugins HandlerThread, unreachable
// from any JS try/catch) when android/app/google-services.json is absent.
// PUSH_ENABLED (config/env.js) exists specifically to stop init() from ever
// reaching checkPushPermission()/registerForPush() when that's the case. This
// file mocks PUSH_ENABLED false and asserts init() short-circuits before any
// of those calls -- including before the permission PROMPT, since asking for
// a permission that can never be fulfilled is worse UX than not asking.
//
// This intentionally lives in its own file (rather than a describe block in
// push.store.test.js) because vi.mock('../config/env.js', ...) is hoisted
// and file-scoped -- push.store.test.js already mocks PUSH_ENABLED true to
// cover the enabled/normal behavior, so the disabled case needs a separate
// module registry.
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
}))

vi.mock('@capacitor/core', () => ({
  Capacitor: {
    getPlatform: vi.fn().mockReturnValue('android'),
    isNativePlatform: vi.fn().mockReturnValue(true),
  },
}))

vi.mock('../config/env.js', () => ({
  PUSH_ENABLED: false,
}))

import api from '../services/api.js'
import { getCached, get } from '../services/storage.js'
import { checkPushPermission, requestPushPermission, registerForPush } from '../services/pushNotifications.js'
import { Capacitor } from '@capacitor/core'
import { usePushStore } from '../stores/push.js'

describe('push store — PUSH_ENABLED false (build-time flag disabled)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    // A live session AND a native platform, so the ONLY thing standing
    // between this test and the normal registration flow is PUSH_ENABLED --
    // proving the guard fires on the flag itself, not on the platform check
    // or the auth-session check that already existed above/below it.
    getCached.mockReturnValue('auth-tok')
    get.mockResolvedValue(null)
    Capacitor.isNativePlatform.mockReturnValue(true)
  })

  it('init() short-circuits before the permission prompt -- no checkPushPermission, no requestPushPermission, no registerForPush', async () => {
    const store = usePushStore()
    await store.init()

    expect(checkPushPermission).not.toHaveBeenCalled()
    expect(requestPushPermission).not.toHaveBeenCalled()
    expect(registerForPush).not.toHaveBeenCalled()
    expect(api.post).not.toHaveBeenCalled()
  })

  it('leaves the store in the same clean, non-error state as the web no-op branch: registered false, error null', async () => {
    const store = usePushStore()
    await store.init()

    expect(store.registered).toBe(false)
    expect(store.error).toBeNull()
  })

  it('never throws', async () => {
    const store = usePushStore()
    await expect(store.init()).resolves.toBeUndefined()
  })
})
