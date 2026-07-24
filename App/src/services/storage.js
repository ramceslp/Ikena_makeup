import { Preferences } from '@capacitor/preferences'

// Well-known keys used across the app for the cached auth session.
export const TOKEN_KEY = 'ikena_auth_token'
export const USER_KEY = 'ikena_user'

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
  const parsed = value === null ? null : JSON.parse(value)
  cache.set(key, parsed)
  return parsed
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
 * Warms the in-memory cache for the auth session (token + user) from
 * Preferences. Call once at app boot before mounting routes that depend on
 * synchronous cached-token reads (e.g. the Axios request interceptor).
 */
export async function hydrate() {
  const [token, user] = await Promise.all([get(TOKEN_KEY), get(USER_KEY)])
  return { token, user }
}
