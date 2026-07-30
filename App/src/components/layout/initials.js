/**
 * Two-letter initials badge fallback for a missing avatar.
 *
 * Ported from frontend/src/components/NavBar.vue's inline helper. Extracted
 * to its own module because the App shell needs it in the bottom tab bar's
 * account tab, and any future Profile/account surface needs the exact same
 * fallback — `user.avatar` is part of the backend payload but is nullable
 * (an account created without a Google profile picture has none).
 *
 * Hardened over the web version: the original split on a single space, so a
 * name with double spaces or leading/trailing whitespace produced empty
 * segments whose [0] is undefined and rendered the literal text "undefined"
 * in the badge. Splitting on a whitespace run and dropping empty segments
 * removes that hole.
 *
 * @param {string|null|undefined} name
 * @returns {string} 1-2 uppercase letters, or '?' when there is no usable name
 */
export function initials(name) {
  if (typeof name !== 'string') return '?'

  const letters = name
    .split(/\s+/)
    .filter((part) => part.length > 0)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase()

  return letters.length > 0 ? letters : '?'
}
