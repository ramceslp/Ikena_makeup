/**
 * safeCtaUrl — guard against javascript:/data: XSS in CTA href bindings.
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

/**
 * isSafeLinkUrl — allowlist validator for TipTap editor link insertion.
 *
 * Allows: http:, https:, mailto:, tel:
 * Rejects: javascript:, data:, and anything else.
 *
 * Uses new URL() for robust parsing — correctly rejects javascript:/data: by
 * protocol inspection, not prefix matching.
 *
 * @param {string|null|undefined} url
 * @returns {boolean}
 */
export function isSafeLinkUrl(url) {
  if (!url || typeof url !== 'string') return false
  const trimmed = url.trim()
  try {
    const u = new URL(trimmed)
    return ['http:', 'https:', 'mailto:', 'tel:'].includes(u.protocol)
  } catch {
    return false
  }
}

/**
 * parseEmbedUrl — converts a user-provided video URL into an embeddable URL.
 *
 * Supported transformations:
 *   YouTube watch URL  → https://www.youtube.com/embed/{id}
 *   YouTube youtu.be   → https://www.youtube.com/embed/{id}
 *   Vimeo page URL     → https://player.vimeo.com/video/{id}
 *   Already-embed URL  → returned as-is
 *   Invalid / unknown  → null
 *
 * @param {string|null|undefined} url
 * @returns {{ type: 'youtube'|'vimeo', embedUrl: string }|null}
 */
export function parseEmbedUrl(url) {
  if (!url || typeof url !== 'string') return null

  // YouTube watch: https://www.youtube.com/watch?v=ID
  const ytWatch = url.match(/youtube\.com\/watch\?(?:.*&)?v=([\w-]+)/)
  if (ytWatch) {
    return { type: 'youtube', embedUrl: `https://www.youtube.com/embed/${ytWatch[1]}` }
  }

  // YouTube short: https://youtu.be/ID
  const ytShort = url.match(/youtu\.be\/([\w-]+)/)
  if (ytShort) {
    return { type: 'youtube', embedUrl: `https://www.youtube.com/embed/${ytShort[1]}` }
  }

  // YouTube already-embed
  const ytEmbed = url.match(/youtube\.com\/embed\/([\w-]+)/)
  if (ytEmbed) {
    return { type: 'youtube', embedUrl: `https://www.youtube.com/embed/${ytEmbed[1]}` }
  }

  // Vimeo page: https://vimeo.com/ID
  const vimeoPage = url.match(/vimeo\.com\/(\d+)/)
  if (vimeoPage) {
    return { type: 'vimeo', embedUrl: `https://player.vimeo.com/video/${vimeoPage[1]}` }
  }

  return null
}
