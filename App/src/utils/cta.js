/**
 * safeCtaUrl — guard against javascript:/data: XSS in CTA href bindings.
 *
 * Ported unchanged from frontend/src/utils/cta.js (only this function --
 * isSafeLinkUrl/parseEmbedUrl belong to the admin TipTap editor, which this
 * app does not have).
 *
 * Returns the url only when it is a valid http: or https: URL.
 * Returns null for any other value (javascript:, data:, relative paths, empty, malformed).
 *
 * Uses new URL() for robust parsing — correctly rejects javascript:/data: by
 * protocol inspection, not prefix matching.
 *
 * @param {string|null|undefined} url
 * @returns {string|null}
 */
export function safeCtaUrl(url) {
  if (!url || typeof url !== 'string') return null
  const trimmed = url.trim()
  try {
    const u = new URL(trimmed)
    return ['http:', 'https:'].includes(u.protocol) ? trimmed : null
  } catch {
    return null
  }
}
