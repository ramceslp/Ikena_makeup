import { PushNotifications } from '@capacitor/push-notifications'

// Thin wrapper around @capacitor/push-notifications (mobile-capacitor-setup
// Phase 8, tasks 8.6-8.8). Mirrors services/googleAuth.js's shape: native-
// plugin calls only, no persistence, no HTTP calls, no business logic --
// stores/push.js owns the registration workflow (permission gating, the
// POST/DELETE /api/device-tokens calls, and the persisted
// already-registered state).

/**
 * Reads the current push permission state without prompting the user.
 * Returns one of the plugin's `PermissionState` values: 'granted',
 * 'denied', 'prompt', or 'prompt-with-rationale'.
 */
export async function checkPushPermission() {
  const status = await PushNotifications.checkPermissions()
  return status.receive
}

/**
 * Prompts the user for push permission (native OS dialog). On most
 * platforms, once the user has denied it once, the OS itself declines to
 * show the dialog again on a subsequent call and simply resolves 'denied'
 * immediately -- this module does not implement its own denial-memory or
 * prompt-suppression logic, since duplicating OS-level behavior would risk
 * diverging from it.
 */
export async function requestPushPermission() {
  const status = await PushNotifications.requestPermissions()
  return status.receive
}

// Registers the 'registration'/'registrationError' listeners exactly once
// per app process (register() can legitimately be called again later --
// e.g. a retried boot -- and must not accumulate duplicate listeners that
// would each separately invoke onToken/onError for the same event).
let listenersAttached = false

/**
 * Attaches native listeners for the 'registration' (token) and
 * 'registrationError' events (idempotent -- see listenersAttached above),
 * then triggers `PushNotifications.register()`. `onToken`/`onError` are
 * plain callbacks (not returned promises) because the plugin's events are
 * asynchronous and unrelated to this call's own promise: `register()`
 * itself only confirms that registration was *requested*, not that it
 * succeeded -- the token (or failure) always arrives later via one of the
 * two listeners.
 */
export async function registerForPush(onToken, onError) {
  if (!listenersAttached) {
    PushNotifications.addListener('registration', (token) => onToken(token.value))
    PushNotifications.addListener('registrationError', (error) => onError(error))
    listenersAttached = true
  }
  await PushNotifications.register()
}

// Same attach-once discipline as listenersAttached above, tracked separately
// because the two listener sets are attached at different points in the boot
// sequence: registration listeners only when a registration is actually
// attempted, delivery listeners on every enabled boot (see stores/push.js).
let deliveryListenersAttached = false

/**
 * Attaches the two delivery listeners (idempotent):
 *
 *  - 'pushNotificationReceived' fires when a notification arrives while the
 *    app is in the FOREGROUND. Android does not draw a tray notification in
 *    that case, so this is the only signal the app gets.
 *  - 'pushNotificationActionPerformed' fires when the user TAPS a
 *    notification, including a cold start from the tray.
 *
 * Deliberately does not navigate on 'received': the user is already looking
 * at some screen, and yanking them elsewhere because a message arrived would
 * be hostile. Only a tap expresses intent to go somewhere.
 */
export function addNotificationListeners(onReceived, onAction) {
  if (deliveryListenersAttached) return

  PushNotifications.addListener('pushNotificationReceived', (notification) => onReceived(notification))
  PushNotifications.addListener('pushNotificationActionPerformed', (action) => onAction(action))
  deliveryListenersAttached = true
}

/**
 * The id of the notification channel every push from this app lands on.
 *
 * MUST stay byte-identical to `default_notification_channel_id` in
 * android/app/src/main/res/values/strings.xml, which AndroidManifest.xml hands
 * to FCM as the default channel for incoming messages. Nothing in either
 * toolchain enforces that — src/tests/androidNotificationChannel.test.js is
 * what does, by reading both sources and comparing them.
 *
 * Treat it as frozen. Android identifies an existing channel by this string, so
 * changing it does not rename the channel on devices that already have the app:
 * it creates a second one and abandons the first, silently discarding whatever
 * importance or mute the user had configured on it.
 */
export const DEFAULT_NOTIFICATION_CHANNEL_ID = 'ikena_general'

/**
 * Creates (or updates) the app's single notification channel.
 *
 * ANDROID ONLY — the plugin's iOS implementation rejects with "not
 * implemented", and iOS has no channel concept at all. The caller owns the
 * platform check (see stores/push.js), keeping this module a thin plugin
 * wrapper, and owns the failure too: this deliberately does not swallow a
 * rejection, so the store can decide that a missing channel must not abort
 * registration.
 *
 * Safe to call on every boot. Android's createNotificationChannel is an upsert,
 * and it will not raise an importance the user has since lowered — re-running
 * it can never override their choice.
 */
export async function createDefaultNotificationChannel() {
  await PushNotifications.createChannel({
    id: DEFAULT_NOTIFICATION_CHANNEL_ID,
    name: 'Novedades de Ikena',
    description: 'Nuevas publicaciones, cursos y anuncios de Ikena Makeup.',
    // 4 = IMPORTANCE_HIGH: sound plus a heads-up banner. Chosen over the
    // plugin's default 3 because these are the announcements the user opted
    // into by granting permission, and a named channel is exactly what lets
    // them turn it down in system settings if they disagree — which the
    // generic FCM fallback channel this replaced did not.
    importance: 4,
    // `visibility` is deliberately NOT passed. The plugin forwards it to
    // NotificationChannel.setLockscreenVisibility(), but Android discards an
    // app-supplied value and stores VISIBILITY_NO_OVERRIDE (-1000) instead --
    // verified on an API 36 emulator, where passing VISIBILITY_PUBLIC (1) still
    // dumped as mLockscreenVisibility=-1000. Lockscreen visibility is the
    // notification's and the user's call, not the channel's. Setting it here
    // would read as a working preference while doing nothing.
  })
}
