import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// ---------------------------------------------------------------------------
// Notification DELIVERY: what happens when a push arrives or is tapped
// (push-notifications Slice 5). Registration behavior lives in
// push.store.test.js; this file only covers the two delivery listeners and
// the deep-link route guard.
// ---------------------------------------------------------------------------
vi.mock('../services/api.js', () => ({
  default: {
    post: vi.fn(),
    delete: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

vi.mock('../services/storage.js', () => ({
  get: vi.fn().mockResolvedValue(null),
  set: vi.fn().mockResolvedValue(undefined),
  remove: vi.fn().mockResolvedValue(undefined),
  getCached: vi.fn().mockReturnValue(null),
  TOKEN_KEY: 'ikena_auth_token',
  PUSH_TOKEN_KEY: 'ikena_push_token',
}))

vi.mock('../services/pushNotifications.js', () => ({
  checkPushPermission: vi.fn().mockResolvedValue('granted'),
  requestPushPermission: vi.fn().mockResolvedValue('granted'),
  registerForPush: vi.fn(),
  addNotificationListeners: vi.fn(),
}))

vi.mock('@capacitor/core', () => ({
  Capacitor: {
    getPlatform: vi.fn().mockReturnValue('android'),
    isNativePlatform: vi.fn().mockReturnValue(true),
  },
}))

vi.mock('../config/env.js', () => ({ PUSH_ENABLED: true, API_URL: 'http://localhost' }))

vi.mock('../router/index.js', () => ({
  default: { push: vi.fn().mockResolvedValue(undefined) },
}))

import router from '../router/index.js'
import { getCached, get } from '../services/storage.js'
import { addNotificationListeners } from '../services/pushNotifications.js'
import { usePushStore, extractRoute } from '../stores/push.js'

describe('push store — notification delivery', () => {
  let store

  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
    store = usePushStore()
  })

  // -------------------------------------------------------------------
  // extractRoute — the deep-link guard
  // -------------------------------------------------------------------

  describe('extractRoute', () => {
    it('accepts an internal absolute path', () => {
      expect(extractRoute({ route: '/noticias/mi-noticia' })).toBe('/noticias/mi-noticia')
    })

    /**
     * A push payload is attacker-influenceable in a way an ordinary API
     * response is not — anyone holding the FCM server key could craft one —
     * and it is handed straight to the router.
     */
    it.each([
      ['an absolute external URL', 'https://evil.com'],
      ['a protocol-relative host', '//evil.com'],
      ['a javascript: URL', 'javascript:alert(1)'],
      ['a bare relative path', 'cursos'],
      ['an empty string', ''],
    ])('rejects %s', (_label, route) => {
      expect(extractRoute({ route })).toBeNull()
    })

    it('rejects a non-string route', () => {
      expect(extractRoute({ route: 42 })).toBeNull()
      expect(extractRoute({ route: { path: '/cursos' } })).toBeNull()
    })

    it('returns null when there is no data or no route', () => {
      expect(extractRoute(undefined)).toBeNull()
      expect(extractRoute({})).toBeNull()
    })
  })

  // -------------------------------------------------------------------
  // Tap -> navigate
  // -------------------------------------------------------------------

  describe('on tap', () => {
    it('navigates to the deep link', async () => {
      await store._onNotificationTapped({
        notification: { data: { route: '/cursos/maquillaje-de-novias' } },
      })

      expect(router.push).toHaveBeenCalledWith('/cursos/maquillaje-de-novias')
    })

    it('does not navigate when the route is unsafe', async () => {
      await store._onNotificationTapped({ notification: { data: { route: 'https://evil.com' } } })

      expect(router.push).not.toHaveBeenCalled()
    })

    it('does not navigate when the notification carries no route', async () => {
      await store._onNotificationTapped({ notification: { data: {} } })

      expect(router.push).not.toHaveBeenCalled()
    })

    /**
     * A notification can name a screen that only exists in a newer app
     * version. Failing to navigate must not surface as an unhandled
     * rejection from a fire-and-forget native listener.
     */
    it('swallows a failed navigation', async () => {
      router.push.mockRejectedValueOnce(new Error('No match for /nueva-pantalla'))
      const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})

      await expect(
        store._onNotificationTapped({ notification: { data: { route: '/nueva-pantalla' } } }),
      ).resolves.toBeUndefined()

      consoleError.mockRestore()
    })
  })

  // -------------------------------------------------------------------
  // Foreground receipt
  // -------------------------------------------------------------------

  describe('on foreground receipt', () => {
    it('records the notification', () => {
      store._onNotificationReceived({
        title: 'Nuevo curso disponible',
        body: 'Maquillaje de novias',
        data: { route: '/cursos/bridal' },
      })

      expect(store.lastReceived).toEqual({
        title: 'Nuevo curso disponible',
        body: 'Maquillaje de novias',
        route: '/cursos/bridal',
      })
    })

    /**
     * The user is already looking at a screen; navigating because a message
     * arrived would be hostile. Only a tap expresses intent.
     */
    it('does not navigate', () => {
      store._onNotificationReceived({ title: 'X', body: 'Y', data: { route: '/cursos' } })

      expect(router.push).not.toHaveBeenCalled()
    })

    it('nulls an unsafe route rather than storing it', () => {
      store._onNotificationReceived({ title: 'X', body: 'Y', data: { route: '//evil.com' } })

      expect(store.lastReceived.route).toBeNull()
    })
  })

  // -------------------------------------------------------------------
  // Listener attachment
  // -------------------------------------------------------------------

  describe('listener attachment in init()', () => {
    it('attaches delivery listeners when there is no session yet', async () => {
      getCached.mockReturnValue(null)

      await store.init()

      // A cold start from a tapped notification happens before any session
      // check completes, so the listeners must already be in place.
      expect(addNotificationListeners).toHaveBeenCalled()
    })

    /**
     * The regression that would silently break taps for the app's
     * longest-standing users: init() returns early once a token is already
     * persisted, so listeners attached after that check would never run.
     */
    it('attaches delivery listeners even when the device is already registered', async () => {
      getCached.mockReturnValue('session-token')
      get.mockResolvedValue('already-registered-push-token')

      await store.init()

      expect(store.registered).toBe(true)
      expect(addNotificationListeners).toHaveBeenCalled()
    })
  })
})
