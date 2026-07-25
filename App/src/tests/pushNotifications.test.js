import { describe, it, expect, vi, beforeEach } from 'vitest'

// ---------------------------------------------------------------------------
// Mock @capacitor/push-notifications BEFORE importing the module under test,
// so checkPushPermission/requestPushPermission/registerForPush drive a
// controllable fake instead of the real native plugin bridge. Mirrors
// googleAuth.test.js's convention for wrapping a native Capacitor plugin.
// ---------------------------------------------------------------------------
vi.mock('@capacitor/push-notifications', () => ({
  PushNotifications: {
    checkPermissions: vi.fn(),
    requestPermissions: vi.fn(),
    register: vi.fn(),
    addListener: vi.fn(),
  },
}))

describe('pushNotifications service', () => {
  beforeEach(() => {
    vi.resetModules()
    vi.clearAllMocks()
  })

  it('checkPushPermission returns the plugin-reported permission state', async () => {
    const { PushNotifications } = await import('@capacitor/push-notifications')
    PushNotifications.checkPermissions.mockResolvedValueOnce({ receive: 'granted' })

    const { checkPushPermission } = await import('../services/pushNotifications.js')
    const state = await checkPushPermission()

    expect(state).toBe('granted')
  })

  it('requestPushPermission returns the plugin-reported permission state after prompting', async () => {
    const { PushNotifications } = await import('@capacitor/push-notifications')
    PushNotifications.requestPermissions.mockResolvedValueOnce({ receive: 'denied' })

    const { requestPushPermission } = await import('../services/pushNotifications.js')
    const state = await requestPushPermission()

    expect(state).toBe('denied')
  })

  it('registerForPush attaches registration/registrationError listeners once and calls PushNotifications.register()', async () => {
    const { PushNotifications } = await import('@capacitor/push-notifications')
    PushNotifications.register.mockResolvedValue(undefined)

    const { registerForPush } = await import('../services/pushNotifications.js')
    const onToken = vi.fn()
    const onError = vi.fn()

    await registerForPush(onToken, onError)
    await registerForPush(onToken, onError)

    expect(PushNotifications.addListener).toHaveBeenCalledTimes(2) // 'registration' + 'registrationError', once each
    expect(PushNotifications.addListener).toHaveBeenCalledWith('registration', expect.any(Function))
    expect(PushNotifications.addListener).toHaveBeenCalledWith('registrationError', expect.any(Function))
    expect(PushNotifications.register).toHaveBeenCalledTimes(2)
  })

  it('registerForPush\'s registration listener invokes onToken with the plugin token value', async () => {
    const { PushNotifications } = await import('@capacitor/push-notifications')
    PushNotifications.register.mockResolvedValue(undefined)

    const { registerForPush } = await import('../services/pushNotifications.js')
    const onToken = vi.fn()
    const onError = vi.fn()
    await registerForPush(onToken, onError)

    const registrationHandler = PushNotifications.addListener.mock.calls.find(
      (call) => call[0] === 'registration'
    )[1]
    registrationHandler({ value: 'fcm-token-abc' })

    expect(onToken).toHaveBeenCalledWith('fcm-token-abc')
  })

  it('registerForPush\'s registrationError listener invokes onError with the plugin error', async () => {
    const { PushNotifications } = await import('@capacitor/push-notifications')
    PushNotifications.register.mockResolvedValue(undefined)

    const { registerForPush } = await import('../services/pushNotifications.js')
    const onToken = vi.fn()
    const onError = vi.fn()
    await registerForPush(onToken, onError)

    const errorHandler = PushNotifications.addListener.mock.calls.find(
      (call) => call[0] === 'registrationError'
    )[1]
    const pluginError = { error: 'no Google Play Services' }
    errorHandler(pluginError)

    expect(onError).toHaveBeenCalledWith(pluginError)
  })
})
