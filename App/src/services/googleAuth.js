import { SocialLogin } from '@capgo/capacitor-social-login'
import { GOOGLE_WEB_CLIENT_ID } from '../config/env.js'

// Module-level flag so the plugin is only initialized once per app process,
// no matter how many times the user opens/retries the sign-in sheet.
let initialized = false

async function ensureInitialized() {
  if (initialized) return
  await SocialLogin.initialize({ google: { webClientId: GOOGLE_WEB_CLIENT_ID } })
  initialized = true
}

/**
 * Triggers the native Google Sign-In sheet and returns the plugin's raw
 * result ({ idToken, accessToken, profile, responseType }). The auth store
 * posts `idToken` to `POST /api/auth/google` (see stores/auth.js).
 *
 * On user cancellation/dismissal, the plugin rejects with
 * `code === 'USER_CANCELLED'` (see @capgo/capacitor-social-login's
 * SocialLoginError) -- this function does not swallow or reinterpret that
 * error; callers (Login.vue) check `error.code` to distinguish a cancel from
 * a real failure and return to the login screen without a crash or an error
 * message, per the spec's "Google Sign-In cancelled" scenario.
 */
export async function signInWithGoogle() {
  await ensureInitialized()
  const { result } = await SocialLogin.login({ provider: 'google', options: {} })
  return result
}
