import { defineStore } from 'pinia'
import { Capacitor } from '@capacitor/core'
import api from '../services/api.js'
import {
  checkPushPermission,
  requestPushPermission,
  registerForPush,
  addNotificationListeners,
  createDefaultNotificationChannel,
} from '../services/pushNotifications.js'
import router from '../router/index.js'
import { get, set, remove, getCached, TOKEN_KEY, PUSH_TOKEN_KEY } from '../services/storage.js'
import { PUSH_ENABLED } from '../config/env.js'

// Device-token push registration (mobile-capacitor-setup Phase 8, tasks
// 8.6-8.8, design.md Decision 2). Registers with the backend's
// POST /api/device-tokens once the user grants push permission, and exposes
// DELETE /api/device-tokens as `unregister()` for a future logout flow (no
// logout surface exists in the app yet as of this PR — see apply-progress
// for the documented deviation).
//
// Silent-by-design on every failure path (permission denied, native
// register() failure, backend POST/DELETE failure): unlike
// cart.js's persistError/payError -- which are the direct consequence of a
// user-initiated tap and are rendered in Cart.vue -- push registration runs
// unprompted in the background at app boot and after login. The spec's
// "Registration call fails" scenario explicitly requires the app to retry
// later "without blocking any other feature" and never leave "the user ...
// stuck in a permission loop"; the "Push permission denied" scenario
// requires the app to continue "with no ... blocking prompt loop". Neither
// scenario describes any user-visible error state, unlike the cart's
// pay/persist flows, which are describing a tap the user just made and
// expects feedback on. `error` is still recorded (not swallowed silently in
// memory) so a future notification-settings screen could surface it to a
// curious user, but no current view reads it -- this is a deliberate,
// considered choice given the twice-confirmed "state set but never
// rendered" bug class from PR8a/8b, not an oversight repeating it.
/**
 * Reads `data.route` from an FCM payload and returns it only if it is a safe
 * internal path, otherwise null.
 *
 * The backend already constrains this field (StorePushNotificationRequest
 * rejects anything not matching /^\/(?!\/)/), so this is defence in depth
 * rather than the only check — a push payload is attacker-influenceable in a
 * way an ordinary API response is not, since anyone holding the FCM server
 * key could craft one, and it is handed straight to the router.
 *
 * Rejects:
 *  - anything not starting with '/'  → 'https://evil.com', 'javascript:...'
 *  - a protocol-relative '//host'    → the router would treat it as an
 *                                      external origin
 *
 * FCM delivers all data values as strings, so a non-string here means a
 * malformed payload and is rejected too.
 */
export function extractRoute(data) {
  const route = data?.route

  if (typeof route !== 'string') return null
  if (!route.startsWith('/')) return null
  if (route.startsWith('//')) return null

  return route
}

/**
 * Whether a path actually opens a screen in THIS build of the app.
 *
 * Needed because vue-router 4 does not reject `push()` to an unknown path — it
 * resolves with an empty `matched` array and only warns on the console. So the
 * try/catch around the navigation below never fired, and a bad deep link
 * rendered AppShell's chrome around an empty <RouterView>: the blank screen
 * this whole fix exists for.
 *
 * A path is also unreachable when it matches only the catch-all: `/:pathMatch`
 * matches literally everything, so `matched.length > 0` alone would now be true
 * for every string on earth.
 *
 * Server-side validation against config/push_destinations.php already stops
 * such a link being SENT. This is the receiving half, and it is not redundant:
 * notifications sent before that validation shipped are still sitting in
 * people's trays, and a link can name a screen that exists in a newer app
 * version than the one installed.
 */
export function isReachableRoute(route) {
  try {
    const resolved = router.resolve(route)

    return resolved.matched.length > 0 && resolved.name !== 'not-found'
  } catch {
    // resolve() throws on a malformed path (bad percent-encoding, for one).
    return false
  }
}

export const usePushStore = defineStore('push', {
  state: () => ({
    permissionState: null, // null (not yet checked) | 'granted' | 'denied' | 'prompt' | 'prompt-with-rationale'
    registered: false,
    error: null,
    // Last notification delivered while the app was in the foreground. Set so
    // a future in-app banner can render it; no view reads it yet.
    //
    // Its `route` has passed extractRoute (safe internal path) but NOT
    // isReachableRoute — there is no navigation here to guard. Whatever
    // eventually turns this into a tappable banner must run that check before
    // routing, or it reintroduces the blank screen on a surface that never had
    // it.
    lastReceived: null,
  }),

  actions: {
    /**
     * Idempotent — safe to call at every app boot (see main.js's
     * bootstrap()) and after every successful login (see
     * stores/auth.js's loginWithGoogle()). Short-circuits immediately when:
     * (a) there is no authenticated session yet (POST /device-tokens
     * requires auth:sanctum), or (b) a device token was already
     * successfully registered in a previous run (read from storage.js —
     * this is what makes tasks 8.6-8.8's "retry later" mechanism concrete:
     * the retry happens on the *next* call to init(), which only proceeds
     * past this guard when the previous attempt did NOT persist a token).
     * Never throws — every failure path below is caught and recorded on
     * `error` without blocking any other app feature [Spec: registration
     * call fails].
     */
    async init() {
      this.error = null

      if (!Capacitor.isNativePlatform()) {
        // @capacitor/push-notifications has no web implementation -- every
        // method on the plugin throws "... is not implemented on web" (see
        // services/pushNotifications.js's plugin wrapper). init() runs
        // unconditionally at every app boot and login (see main.js/auth.js),
        // which includes every `npm run dev` session in a browser. This is
        // an expected, not-a-failure condition for that platform, so it is
        // a clean no-op: `registered` stays false, `error` stays null
        // (denial/skip here is not the "registration call fails" or
        // "permission denied" spec scenario -- there is no native
        // permission system to check or deny on web), and nothing is
        // logged, since there is nothing to retry or report.
        return
      }

      if (!PUSH_ENABLED) {
        // Build-time kill switch (config/env.js's PUSH_ENABLED) -- see its
        // doc comment for the full incident writeup. Short version:
        // PushNotifications.register() throws natively on a Capacitor
        // plugin thread when android/app/google-services.json is absent,
        // that throw is unreachable from any try/catch in this file, and it
        // kills the entire app process. Checked here, before
        // checkPushPermission()/requestPushPermission(), not just before
        // registerForPush() below -- prompting the user for a permission
        // that can never be honored (register() will crash regardless of
        // the answer) is worse UX than not asking at all.
        //
        // Same reasoning as the web no-op branch above: a deliberately
        // disabled optional feature is not a failure, so `registered` stays
        // false and `error` stays null -- there is nothing to retry or
        // report.
        return
      }

      // Attached HERE, before every remaining early return, and deliberately
      // so. The common case on a returning user is the `alreadyRegistered`
      // branch below, which exits init() immediately — if the delivery
      // listeners were attached after it, tapping a notification would do
      // nothing on exactly the devices that have been using the app longest.
      // The not-logged-in return above it has the same problem on a cold
      // start from a tapped notification. Attaching is idempotent and cheap.
      addNotificationListeners(
        (notification) => this._onNotificationReceived(notification),
        (action) => this._onNotificationTapped(action),
      )

      await this._ensureNotificationChannel()

      if (!getCached(TOKEN_KEY)) {
        // Not logged in yet — loginWithGoogle() calls init() again right
        // after a session exists.
        return
      }

      const alreadyRegistered = await get(PUSH_TOKEN_KEY)
      if (alreadyRegistered) {
        this.registered = true
        return
      }

      let permission
      try {
        permission = await checkPushPermission()
        if (permission === 'prompt' || permission === 'prompt-with-rationale') {
          permission = await requestPushPermission()
        }
      } catch (err) {
        // Native permission API itself failed (rare). Silent by design —
        // see the class doc comment above. Retried on the next init() call
        // since nothing was persisted.
        console.error('Failed to check/request push notification permission:', err)
        this.error = 'permission-check-failed'
        return
      }

      this.permissionState = permission

      if (permission !== 'granted') {
        // [Spec: push permission denied] no registration attempt at all.
        // Re-prompt suppression after a denial is the native OS's
        // responsibility (see services/pushNotifications.js), not this
        // store's — duplicating that logic here would risk diverging from
        // platform behavior.
        return
      }

      try {
        await registerForPush(
          (token) => this._onToken(token),
          (err) => this._onRegistrationError(err)
        )
      } catch (err) {
        // register() itself rejected (rare — most native failures surface
        // via the 'registrationError' listener instead, handled by
        // _onRegistrationError below). Silent by design, retried on the
        // next init() call since nothing was persisted.
        console.error('Failed to start push registration:', err)
        this.error = 'register-call-failed'
      }
    },

    /**
     * Declares the Android notification channel that AndroidManifest.xml names
     * as FCM's default (see services/pushNotifications.js's
     * DEFAULT_NOTIFICATION_CHANNEL_ID). The manifest only NAMES the channel;
     * nothing creates it. When FCM finds that the named channel does not
     * exist it quietly substitutes its own `fcm_fallback_notification_channel`
     * ("Miscellaneous"), which is the exact generic, unmanageable entry this
     * change exists to replace — so without this call the manifest half of the
     * work buys nothing.
     *
     * Called from init() before every remaining early return, for the same
     * reason addNotificationListeners is — the common path on a device that
     * has had the app installed for a while exits at `alreadyRegistered`, and
     * those are precisely the devices that receive pushes. It is also the
     * reason this runs unconditionally rather than only on a first
     * registration: an install that predates this code has no channel yet, and
     * will never take the registration path again.
     *
     * Never throws. Per the above, a failure here costs the channel's name and
     * the user's control over it — degraded, but notifications still arrive;
     * letting that abort init() would cost the device registration entirely,
     * which is strictly worse. Deliberately does NOT set `error`: that field
     * means "device-token registration did not happen", and a future
     * notification-settings screen reading it must not be told registration
     * failed when it in fact succeeded.
     */
    async _ensureNotificationChannel() {
      // Channels are an Android concept; the plugin's iOS implementation
      // rejects with "not implemented".
      if (Capacitor.getPlatform() !== 'android') return

      try {
        await createDefaultNotificationChannel()
      } catch (err) {
        console.error('Failed to create the default notification channel:', err)
      }
    },

    /**
     * A notification arrived while the app was in the foreground. Recorded
     * only — see addNotificationListeners' doc comment for why this must not
     * navigate.
     */
    _onNotificationReceived(notification) {
      this.lastReceived = {
        title: notification?.title ?? null,
        body: notification?.body ?? null,
        route: extractRoute(notification?.data),
      }
    },

    /**
     * The user tapped a notification (from the tray, possibly cold-starting
     * the app). Navigates to the deep link the backend attached as
     * `data.route`.
     *
     * Never throws: this runs from a fire-and-forget native listener, so an
     * uncaught rejection would surface as an unhandled promise rejection
     * rather than anything actionable. A failed navigation simply leaves the
     * user on whatever screen the app opened to, which is a working app.
     */
    async _onNotificationTapped(action) {
      const route = extractRoute(action?.notification?.data)

      if (route === null) return

      // Checked BEFORE navigating, not caught after: router.push() to an
      // unmatched path resolves successfully (see isReachableRoute), so the
      // catch below never saw the failure this guard now prevents.
      //
      // Staying put beats routing to the 404 view. The user tapped a
      // notification expecting content; the app opens on Home either way, and
      // Home is a working app. The catch-all view is for links the user
      // followed deliberately, where an explanation is the useful answer.
      if (!isReachableRoute(route)) {
        console.error('Push notification deep link matches no route in this build:', route)

        return
      }

      try {
        await router.push(route)
      } catch (err) {
        // A guard rejection or redirect (e.g. /profile while logged out sends
        // the user to /login) surfaces here as a navigation failure.
        console.error('Failed to open push notification deep link:', route, err)
      }
    },

    async _onToken(token) {
      try {
        // skipAuthRedirect: true -- this call runs unprompted in the
        // background (app boot / post-login), so a stale/expired cached
        // token must not force-clear the session and redirect the user away
        // from whatever screen they're on (see services/api.js's
        // handleResponseError doc comment). The catch block below still
        // records the failure on `error` normally either way.
        await api.post(
          '/device-tokens',
          { token, platform: Capacitor.getPlatform() },
          { skipAuthRedirect: true }
        )
        await set(PUSH_TOKEN_KEY, token)
        this.registered = true
        this.error = null
      } catch (err) {
        // [Spec: registration call fails] backend POST failed (network or
        // server error). Deliberately NOT persisted, so the next init()
        // call (next app boot, or next login) retries automatically. Never
        // re-thrown — this callback runs from registerForPush's fire-and-
        // forget 'registration' listener, so an uncaught rejection here
        // would surface as an unhandled promise rejection instead of being
        // recorded.
        console.error('Failed to register device token with backend:', err)
        this.error = 'backend-registration-failed'
      }
    },

    _onRegistrationError(err) {
      // Native registration itself failed (e.g. no Google Play Services /
      // FCM unavailable on the device). Silent by design, retried on the
      // next init() call since nothing was persisted.
      console.error('Native push registration failed:', err)
      this.error = 'native-registration-failed'
    },

    /**
     * Unregisters the current device token (DELETE /api/device-tokens).
     * Not yet wired to any UI — no logout surface exists in the app as of
     * this PR (Profile/history lands in tasks 8.9-8.10, a later PR).
     * Exposed now so a future logout action can call it without needing to
     * touch this store again. Never throws: the persisted token is always
     * cleared locally even if the backend call fails, since a stale local
     * token pointing at a possibly-already-removed backend row is worse
     * than an extra no-op DELETE attempt after a re-login.
     */
    async unregister() {
      const token = await get(PUSH_TOKEN_KEY)
      if (!token) return

      try {
        await api.delete('/device-tokens', { data: { token } })
      } catch (err) {
        console.error('Failed to unregister device token:', err)
      } finally {
        await remove(PUSH_TOKEN_KEY)
        this.registered = false
      }
    },
  },
})
