// Resolves the API base URL for the running build (Android emulator,
// physical device, or production). VITE_API_URL is sourced from Vite's
// mode-based env files:
//   - .env.development (committed): safe default for the Android emulator
//     (10.0.2.2 is the emulator's loopback alias to the host machine).
//   - .env.development.local (gitignored, per-developer): overrides
//     VITE_API_URL with your machine's LAN IP to test on a physical device.
//     This is the mode-specific `.local` variant — per Vite's env-file
//     precedence (.env < .env.local < .env.[mode] < .env.[mode].local), it
//     is the only file that actually outranks the committed
//     .env.development; a plain .env.local or .env would silently lose.
//   - production: VITE_API_URL must be injected by the CI/CD build
//     environment — never committed, per design decision #5/#6 (prod is
//     HTTPS-only, no cleartext exceptions at any layer).
//
// resolveApiBaseUrl is a pure function so it can be unit tested directly,
// without mocking import.meta.env.
export function resolveApiBaseUrl(mode, url) {
  if (!url) {
    throw new Error(
      'VITE_API_URL is not set. Define it in App/.env.development (emulator), ' +
        'App/.env.development.local (physical device — gitignored), or as a build-time env var for production.'
    )
  }

  if (mode === 'production' && !url.startsWith('https://')) {
    throw new Error(
      `VITE_API_URL must use HTTPS in production. Cleartext dev-networking exceptions are scoped ` +
        `to the native Android/iOS layer only, never the production API URL. Got: ${url}`
    )
  }

  return url
}

export const API_BASE_URL = resolveApiBaseUrl(import.meta.env.MODE, import.meta.env.VITE_API_URL)

// Google OAuth web client ID (matches backend's GOOGLE_CLIENT_ID / frontend's
// VITE_GOOGLE_CLIENT_ID -- the app's native Google Sign-In plugin requests an
// ID token audienced to this same client, since AuthController::google()
// verifies it via that client). Deliberately does NOT throw in non-production
// modes when unset -- unlike VITE_API_URL, a missing client ID must not crash
// module import/test collection during local dev; it only disables Google
// Sign-In until configured (see services/googleAuth.js).
//
// The committed App/.env.development ships a literal placeholder string
// (`your-google-web-client-id-here`) as a reminder for whoever configures a
// real client ID -- an empty-string check alone would NOT catch that
// placeholder leaking into a production build. In production, both the
// empty case AND the known placeholder are treated as misconfiguration and
// fail loudly, matching the VITE_API_URL convention (resolveApiBaseUrl
// above) of never silently shipping an unusable value to production.
const RAW_GOOGLE_WEB_CLIENT_ID = import.meta.env.VITE_GOOGLE_WEB_CLIENT_ID || ''
const GOOGLE_WEB_CLIENT_ID_PLACEHOLDER = 'your-google-web-client-id-here'

export function resolveGoogleWebClientId(isProd, value) {
  const isUnset = !value
  const isPlaceholder = value === GOOGLE_WEB_CLIENT_ID_PLACEHOLDER

  if (isProd && (isUnset || isPlaceholder)) {
    throw new Error(
      'VITE_GOOGLE_WEB_CLIENT_ID is not set to a real value in production. ' +
        `Got: ${isUnset ? '(empty)' : `"${value}"`}. Define a real Google OAuth web client ID ` +
        'as a build-time env var for production -- the committed App/.env.development placeholder ' +
        `("${GOOGLE_WEB_CLIENT_ID_PLACEHOLDER}") must never reach a production build.`
    )
  }

  if (!isProd && (isUnset || isPlaceholder)) {
    // eslint-disable-next-line no-console
    console.warn(
      'VITE_GOOGLE_WEB_CLIENT_ID is unset or still the placeholder value -- Google Sign-In will not work ' +
        'until a real client ID is configured (see App/.env.development.local).'
    )
  }

  return value
}

// ⚠️ MODE, not PROD. `vite build` sets NODE_ENV=production regardless of the
// --mode flag, and `import.meta.env.PROD` is derived from NODE_ENV — so PROD
// is `true` even for the `vite build --mode development` build installed on
// the emulator. Passing it here made this call throw on the committed
// placeholder, killing module import for an ordinary dev build instead of
// just disabling Google Sign-In. Same trap that broke every emulator checkout
// through services/checkoutHandoff.js; MODE reflects the --mode flag and is
// the signal this codebase standardised on (see resolveApiBaseUrl above).
export const GOOGLE_WEB_CLIENT_ID = resolveGoogleWebClientId(
  import.meta.env.MODE === 'production',
  RAW_GOOGLE_WEB_CLIENT_ID
)

// ---------------------------------------------------------------------------
// PUSH_ENABLED — build-time kill switch for push notifications.
// ---------------------------------------------------------------------------
// WHY THIS FLAG EXISTS (read this before "cleaning up" and deleting it):
//
// @capacitor/push-notifications' PushNotifications.register() calls into
// FirebaseMessaging.getInstance() natively, which requires
// android/app/google-services.json to exist (it does not, anywhere in this
// repo, as of this flag's introduction -- no Firebase project has been wired
// up yet). Without it, register() throws:
//   java.lang.IllegalStateException: Default FirebaseApp is not initialized
//   in this process com.ikenamakeup.app.
//
// The critical, non-obvious part: that throw happens on Capacitor's own
// `CapacitorPlugins` HandlerThread (Bridge.callPluginMethod's reflection
// call into the plugin), NOT on the JS thread and NOT as a rejected promise.
// stores/push.js wraps every native call in `try { await ... } catch`, and
// none of that JS error handling can ever see this exception -- it
// propagates straight to HandlerThread.run() uncaught, which kills the
// entire app process. Reproduced on-device: user logs in with Google, taps
// "Allow" on the notification-permission dialog, and the whole app dies
// (FATAL EXCEPTION on the CapacitorPlugins thread).
//
// It gets worse: android/app/build.gradle's `apply plugin:
// com.google.gms.google-services` is wrapped by Capacitor's own generated
// Gradle template in `try { if (servicesJSON.text) {...} } catch { logger
// .info(...) }` (see build.gradle around the google-services block). A
// missing google-services.json therefore does NOT fail the build -- it logs
// at `info` level (invisible in a normal build run) and silently compiles
// and ships an app that is guaranteed to crash the first time a user grants
// push permission. There is no build-time signal that anything is wrong.
//
// PUSH_ENABLED is the only thing standing between "Firebase not configured"
// and "process death on first login". stores/push.js's init() checks this
// flag BEFORE calling checkPushPermission()/requestPushPermission() too, not
// just before registerForPush() -- prompting the user for a permission that
// can never be honored (because register() will crash regardless of what
// they answer) is worse UX than simply not asking.
//
// DEFAULT IS FALSE. This is the single most important property of this
// flag: an unset VITE_PUSH_ENABLED must fail SAFE (feature silently off),
// not fail FATAL (process death). Do not flip the default to true, and do
// not replace resolvePushEnabled's exact 'true' check with a looser
// truthiness check (`Boolean(import.meta.env.VITE_PUSH_ENABLED)` would treat
// the *string* 'false' as truthy, since any non-empty string is truthy in
// JS -- env vars are always strings, never real booleans).
//
// To enable push notifications, BOTH of the following are required (see
// README.md's "Push notifications" section for the full walkthrough):
//   1. A real android/app/google-services.json from a configured Firebase
//      project (package name com.ikenamakeup.app).
//   2. VITE_PUSH_ENABLED=true in the build's env file.
// Setting only #2 without #1 re-introduces this exact crash.
export function resolvePushEnabled(value) {
  // Intentionally strict: only the exact string 'true' enables the flag.
  // Every other value -- unset/undefined, '', 'false', '0', '1', or any
  // other string -- is treated as disabled. Some env-boolean conventions
  // also accept '1' as truthy; this flag deliberately does not, so there is
  // exactly one unambiguous way to turn on a feature that can crash the app
  // if turned on incorrectly.
  return value === 'true'
}

export const PUSH_ENABLED = resolvePushEnabled(import.meta.env.VITE_PUSH_ENABLED)
