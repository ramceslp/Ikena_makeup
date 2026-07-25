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

export const GOOGLE_WEB_CLIENT_ID = resolveGoogleWebClientId(
  import.meta.env.PROD,
  RAW_GOOGLE_WEB_CLIENT_ID
)
