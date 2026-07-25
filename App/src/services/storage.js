import { Preferences } from '@capacitor/preferences'

// Well-known keys used across the app for the cached auth session.
export const TOKEN_KEY = 'ikena_auth_token'
export const USER_KEY = 'ikena_user'
// Cart contents (mobile-capacitor-setup Phase 8) — replaces the web's
// localStorage-backed 'ikena_cart' key with the same async Preferences path
// used for the auth session (see cart.js).
export const CART_KEY = 'ikena_cart'

// In-memory cache so synchronous consumers (the Axios request interceptor in
// api.js in particular) can read a value without awaiting the async
// Preferences native bridge on every single request. The cache is kept in
// sync by set()/remove(), and warmed from disk via hydrate() at app boot.
const cache = new Map()

/**
 * Reads a value from Preferences, JSON-parses it, and refreshes the
 * in-memory cache. Always reads through to Preferences (never serves a
 * stale cached value) so callers get the current persisted state.
 */
export async function get(key) {
  const { value } = await Preferences.get({ key })
  if (value === null) {
    cache.set(key, null)
    return null
  }

  try {
    const parsed = JSON.parse(value)
    cache.set(key, parsed)
    return parsed
  } catch {
    // Corrupted/malformed stored value: treat as "no cached session" instead
    // of throwing and stalling app boot (see main.js's bootstrap()).
    cache.set(key, null)
    return null
  }
}

/**
 * Persists a JSON-serializable value to Preferences and updates the cache.
 */
export async function set(key, value) {
  cache.set(key, value)
  await Preferences.set({ key, value: JSON.stringify(value) })
}

/**
 * Removes a value from Preferences and clears it from the cache.
 */
export async function remove(key) {
  cache.delete(key)
  await Preferences.remove({ key })
}

/**
 * Synchronous read of the in-memory cache only. Returns null if the key was
 * never hydrated/read/set in this process.
 */
export function getCached(key) {
  return cache.has(key) ? cache.get(key) : null
}

/**
 * Warms the in-memory cache for the auth session (token + user) and the
 * cart from Preferences. Call once at app boot before mounting routes that
 * depend on synchronous cached reads (e.g. the Axios request interceptor's
 * token read, and cart.js's store-init read — see stores/cart.js).
 */
export async function hydrate() {
  const [token, user, cart] = await Promise.all([get(TOKEN_KEY), get(USER_KEY), get(CART_KEY)])
  return { token, user, cart }
}
