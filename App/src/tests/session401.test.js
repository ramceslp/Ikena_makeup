import { describe, it, expect, vi, beforeEach } from 'vitest'

// ---------------------------------------------------------------------------
// Integration test for the "Bearer token expired mid-session" spec scenario,
// tying the auth store's session (task 6.1) to the response-error guard
// already built and unit-tested in services/api.js (PR5). Per task 6.4's
// instruction, this does NOT reimplement or duplicate that guard -- it
// mocks only the lowest-level native bridge (@capacitor/preferences) and the
// router, then exercises the REAL storage.js + REAL api.js together, proving
// an actual persisted session is cleared (not just a mocked storage.js
// module reporting that it "would" clear).
// ---------------------------------------------------------------------------
vi.mock('@capacitor/preferences', () => ({
  Preferences: {
    get: vi.fn().mockResolvedValue({ value: null }),
    set: vi.fn().mockResolvedValue(undefined),
    remove: vi.fn().mockResolvedValue(undefined),
  },
}))

vi.mock('../router/index.js', () => ({
  default: {
    push: vi.fn().mockResolvedValue(undefined),
    currentRoute: { value: { path: '/' } },
  },
}))

import { Preferences } from '@capacitor/preferences'
import router from '../router/index.js'
import { set, getCached, TOKEN_KEY, USER_KEY } from '../services/storage.js'
import { handleResponseError } from '../services/api.js'

describe('mid-session 401 clears the real persisted session (integration)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    router.currentRoute.value.path = '/'
  })

  it('clears the real cached token/user and routes to /login via the router (never window.location) when a bearer token expires mid-session', async () => {
    // Arrange: a real, live session -- persisted through the real storage.js
    // module, not a mock of it.
    await set(TOKEN_KEY, 'live-session-token')
    await set(USER_KEY, { id: 7, name: 'Live User' })
    expect(getCached(TOKEN_KEY)).toBe('live-session-token')
    expect(getCached(USER_KEY)).toEqual({ id: 7, name: 'Live User' })
    router.currentRoute.value.path = '/'

    // Act: any API call returns 401 mid-session.
    const error = { response: { status: 401 } }
    await expect(handleResponseError(error)).rejects.toBe(error)

    // Assert: the real cached session is gone...
    expect(getCached(TOKEN_KEY)).toBeNull()
    expect(getCached(USER_KEY)).toBeNull()
    expect(Preferences.remove).toHaveBeenCalledWith({ key: TOKEN_KEY })
    expect(Preferences.remove).toHaveBeenCalledWith({ key: USER_KEY })
    // ...and the app navigated via the SPA router (no window.location, no
    // hard WebView reload).
    expect(router.push).toHaveBeenCalledWith('/login')
  })
})
