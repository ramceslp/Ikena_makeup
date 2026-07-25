import { defineStore } from 'pinia'
import { Capacitor } from '@capacitor/core'
import api from '../services/api.js'
import { checkPushPermission, requestPushPermission, registerForPush } from '../services/pushNotifications.js'
import { get, set, remove, getCached, TOKEN_KEY, PUSH_TOKEN_KEY } from '../services/storage.js'

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
export const usePushStore = defineStore('push', {
  state: () => ({
    permissionState: null, // null (not yet checked) | 'granted' | 'denied' | 'prompt' | 'prompt-with-rationale'
    registered: false,
    error: null,
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
