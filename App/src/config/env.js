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
// verifies it via that client). Deliberately does NOT throw when unset --
// unlike VITE_API_URL, a missing client ID must not crash module import/test
// collection; it only disables Google Sign-In until configured (see
// services/googleAuth.js).
export const GOOGLE_WEB_CLIENT_ID = import.meta.env.VITE_GOOGLE_WEB_CLIENT_ID || ''
