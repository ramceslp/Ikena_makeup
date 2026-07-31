import { Browser } from '@capacitor/browser'
import api from './api.js'

// ⚠️ MODE, not PROD. `vite build` sets NODE_ENV=production regardless of the
// --mode flag, and `import.meta.env.PROD` is derived from NODE_ENV — so PROD
// is `true` even for the `vite build --mode development` build that gets
// installed on the emulator. Using it here compiled the guard below to
// isAllowedHandoffUrl(url, true), which rejected the perfectly valid
// http://localhost:5173 handoff URL the dev backend returns, and every
// checkout on the emulator died with "No se pudo iniciar el pago".
//
// `import.meta.env.MODE` reflects the --mode flag, which is the signal this
// codebase already standardised on: config/env.js's resolveApiBaseUrl()
// takes MODE for its own https-in-production rule.
const IS_PRODUCTION_BUILD = import.meta.env.MODE === 'production'

// Shared checkout-handoff launcher.
//
// Every paid action in this app follows the same three steps: POST a snapshot
// to /checkout/handoff, validate the resume URL that comes back, and open it
// with @capacitor/browser. That sequence is the app's ONLY sanctioned path to
// a payment screen — the spec's Mobile App Boundaries forbid rendering any
// payment UI inside the app's own WebView, so Browser.open() here is the one
// call allowed to load a payment-adjacent URL, and it deliberately never
// touches the app's router.
//
// It lived inline in stores/cart.js while the cart was the only payer. Two
// more payers (booking deposits and course enrollment) now need the identical
// sequence, and three hand-rolled copies of a security-sensitive URL check is
// how one copy quietly ends up weaker than the others.

/**
 * isAllowedHandoffUrl — the guard that decides whether a URL returned by the
 * backend may be handed to Browser.open().
 *
 * Without it, a malformed or missing `url` (e.g. `{ url: undefined }`) passes
 * straight through with no exception: Browser.open()'s web fallback resolves
 * that to `window.open(undefined, '_blank')`, silently opening a blank tab
 * while the caller's error state stays null and its CTA just resets.
 *
 * Production requires https. Development also accepts http, because
 * config('app.frontend_url') defaults to `http://localhost:5173` and the
 * handoff URL is built from it server-side — an https-only rule makes every
 * payment flow untestable outside production without teaching developers to
 * weaken the check itself. `isProd` is a parameter rather than a direct
 * `import.meta.env` read so both branches are unit-testable; see
 * IS_PRODUCTION_BUILD above for which env flag may be passed into it.
 *
 * @param {unknown} url
 * @param {boolean} isProd
 * @returns {boolean}
 */
export function isAllowedHandoffUrl(url, isProd) {
  if (typeof url !== 'string') return false
  if (url.startsWith('https://')) return true
  return !isProd && url.startsWith('http://')
}

/**
 * Snapshot a purchase behind a single-use handoff token and open the returned
 * web checkout URL in the system/in-app Browser container.
 *
 * Rejects (rather than returning a sentinel) so each caller keeps ownership of
 * its own error copy and loading flag — the stores already have established
 * error-field conventions and they differ per surface.
 *
 * @param {object} payload the /checkout/handoff body, including its `type` discriminator
 * @returns {Promise<string>} the URL that was opened
 */
export async function startCheckoutHandoff(payload) {
  const response = await api.post('/checkout/handoff', payload)
  const url = response?.data?.data?.url

  if (!isAllowedHandoffUrl(url, IS_PRODUCTION_BUILD)) {
    throw new Error('Invalid or missing checkout handoff URL')
  }

  await Browser.open({ url })

  return url
}
