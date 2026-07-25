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
