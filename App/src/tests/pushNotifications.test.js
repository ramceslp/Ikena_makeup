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
    createChannel: vi.fn(),
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

  // -------------------------------------------------------------------
  // Delivery listeners (push-notifications Slice 5)
  // -------------------------------------------------------------------

  it('addNotificationListeners subscribes to both delivery events', async () => {
    const { PushNotifications } = await import('@capacitor/push-notifications')

    const { addNotificationListeners } = await import('../services/pushNotifications.js')
    addNotificationListeners(vi.fn(), vi.fn())

    const events = PushNotifications.addListener.mock.calls.map((call) => call[0])
    expect(events).toContain('pushNotificationReceived')
    expect(events).toContain('pushNotificationActionPerformed')
  })

  it('addNotificationListeners forwards a foreground notification to onReceived', async () => {
    const { PushNotifications } = await import('@capacitor/push-notifications')

    const { addNotificationListeners } = await import('../services/pushNotifications.js')
    const onReceived = vi.fn()
    addNotificationListeners(onReceived, vi.fn())

    const handler = PushNotifications.addListener.mock.calls.find(
      (call) => call[0] === 'pushNotificationReceived'
    )[1]
    const notification = { title: 'Nueva noticia', body: 'Mirá esto', data: { route: '/noticias/x' } }
    handler(notification)

    expect(onReceived).toHaveBeenCalledWith(notification)
  })

  it('addNotificationListeners forwards a tap to onAction', async () => {
    const { PushNotifications } = await import('@capacitor/push-notifications')

    const { addNotificationListeners } = await import('../services/pushNotifications.js')
    const onAction = vi.fn()
    addNotificationListeners(vi.fn(), onAction)

    const handler = PushNotifications.addListener.mock.calls.find(
      (call) => call[0] === 'pushNotificationActionPerformed'
    )[1]
    const action = { actionId: 'tap', notification: { data: { route: '/cursos/x' } } }
    handler(action)

    expect(onAction).toHaveBeenCalledWith(action)
  })

  /**
   * init() runs at every app boot AND after every login, so a non-idempotent
   * attach would fire each callback twice for a single notification.
   */
  it('addNotificationListeners attaches only once per app process', async () => {
    const { PushNotifications } = await import('@capacitor/push-notifications')

    const { addNotificationListeners } = await import('../services/pushNotifications.js')
    addNotificationListeners(vi.fn(), vi.fn())
    addNotificationListeners(vi.fn(), vi.fn())
    addNotificationListeners(vi.fn(), vi.fn())

    const deliveryCalls = PushNotifications.addListener.mock.calls.filter((call) =>
      ['pushNotificationReceived', 'pushNotificationActionPerformed'].includes(call[0])
    )
    expect(deliveryCalls).toHaveLength(2)
  })

  // -------------------------------------------------------------------
  // Default notification channel (Android O+)
  // -------------------------------------------------------------------

  it('createDefaultNotificationChannel registers a channel whose id matches the manifest string resource', async () => {
    const { PushNotifications } = await import('@capacitor/push-notifications')
    PushNotifications.createChannel.mockResolvedValueOnce(undefined)

    const { createDefaultNotificationChannel, DEFAULT_NOTIFICATION_CHANNEL_ID } = await import(
      '../services/pushNotifications.js'
    )
    await createDefaultNotificationChannel()

    expect(PushNotifications.createChannel).toHaveBeenCalledTimes(1)
    expect(PushNotifications.createChannel).toHaveBeenCalledWith(
      expect.objectContaining({ id: DEFAULT_NOTIFICATION_CHANNEL_ID })
    )
  })

  /**
   * The channel's name and description are what the user reads in Android's
   * notification settings -- the entire reason for declaring a channel instead
   * of letting FCM fall back to its generic one. An empty or missing label
   * would reintroduce exactly the problem this replaced.
   */
  it('createDefaultNotificationChannel gives the channel a user-facing name and description', async () => {
    const { PushNotifications } = await import('@capacitor/push-notifications')
    PushNotifications.createChannel.mockResolvedValueOnce(undefined)

    const { createDefaultNotificationChannel } = await import('../services/pushNotifications.js')
    await createDefaultNotificationChannel()

    const channel = PushNotifications.createChannel.mock.calls[0][0]
    expect(channel.name).toBeTruthy()
    expect(channel.description).toBeTruthy()
    expect(channel.importance).toBeGreaterThanOrEqual(3) // at least DEFAULT: makes a sound
  })

  it('createDefaultNotificationChannel rejects when the plugin rejects (the caller owns the failure)', async () => {
    const { PushNotifications } = await import('@capacitor/push-notifications')
    PushNotifications.createChannel.mockRejectedValueOnce(new Error('not implemented'))

    const { createDefaultNotificationChannel } = await import('../services/pushNotifications.js')

    await expect(createDefaultNotificationChannel()).rejects.toThrow('not implemented')
  })
})
