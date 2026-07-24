// Resolves the API base URL for the running build (Android emulator,
// physical device, or production). VITE_API_URL is sourced from Vite's
// mode-based env files:
//   - .env.development (committed): safe default for the Android emulator
//     (10.0.2.2 is the emulator's loopback alias to the host machine).
//   - .env.local (gitignored, per-developer): overrides VITE_API_URL with
//     your machine's LAN IP to test on a physical device.
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
        'App/.env.local (physical device — gitignored), or as a build-time env var for production.'
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
