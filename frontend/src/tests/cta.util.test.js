/**
 * Tests for src/utils/cta.js
 *
 * Covers:
 *   - safeCtaUrl (Fix 1 — CTA href XSS guard)
 *   - isSafeLinkUrl (Fix 2 — TipTap link URL validator)
 *   - parseEmbedUrl (Fix 3 — embed URL parsing for YouTube/Vimeo)
 */
import { describe, it, expect } from 'vitest'
import { safeCtaUrl, isSafeLinkUrl, parseEmbedUrl } from '../utils/cta.js'

// ---------------------------------------------------------------------------
// safeCtaUrl
// ---------------------------------------------------------------------------
describe('safeCtaUrl', () => {
  it('returns the url when it starts with https://', () => {
    expect(safeCtaUrl('https://example.com/oferta')).toBe('https://example.com/oferta')
  })

  it('returns the url when it starts with http://', () => {
    expect(safeCtaUrl('http://example.com')).toBe('http://example.com')
  })

  it('returns null for javascript: URLs', () => {
    expect(safeCtaUrl('javascript:alert(1)')).toBeNull()
  })

  it('returns null for data: URLs', () => {
    expect(safeCtaUrl('data:text/html,<script>alert(1)</script>')).toBeNull()
  })

  it('returns null for relative paths', () => {
    expect(safeCtaUrl('/noticias/slug')).toBeNull()
  })

  it('returns null for null input', () => {
    expect(safeCtaUrl(null)).toBeNull()
  })

  it('returns null for undefined input', () => {
    expect(safeCtaUrl(undefined)).toBeNull()
  })

  it('returns null for empty string', () => {
    expect(safeCtaUrl('')).toBeNull()
  })
})

// ---------------------------------------------------------------------------
// isSafeLinkUrl (Fix 2 — TipTap link validator)
// ---------------------------------------------------------------------------
describe('isSafeLinkUrl', () => {
  it('returns true for https: URL', () => {
    expect(isSafeLinkUrl('https://example.com')).toBe(true)
  })

  it('returns true for http: URL', () => {
    expect(isSafeLinkUrl('http://example.com')).toBe(true)
  })

  it('returns true for mailto: URL', () => {
    expect(isSafeLinkUrl('mailto:user@example.com')).toBe(true)
  })

  it('returns true for tel: URL', () => {
    expect(isSafeLinkUrl('tel:+5491112345678')).toBe(true)
  })

  it('returns FALSE for javascript: URL', () => {
    expect(isSafeLinkUrl('javascript:alert(1)')).toBe(false)
  })

  it('returns FALSE for JAVASCRIPT: (case-insensitive reject)', () => {
    expect(isSafeLinkUrl('JAVASCRIPT:alert(1)')).toBe(false)
  })

  it('returns FALSE for data: URL', () => {
    expect(isSafeLinkUrl('data:text/html,xss')).toBe(false)
  })

  it('returns FALSE for null', () => {
    expect(isSafeLinkUrl(null)).toBe(false)
  })

  it('returns FALSE for empty string', () => {
    expect(isSafeLinkUrl('')).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// parseEmbedUrl (Fix 3 — embed URL parsing)
// ---------------------------------------------------------------------------
describe('parseEmbedUrl', () => {
  it('converts YouTube watch URL to embed URL', () => {
    const result = parseEmbedUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
    expect(result).not.toBeNull()
    expect(result.type).toBe('youtube')
    expect(result.embedUrl).toBe('https://www.youtube.com/embed/dQw4w9WgXcQ')
  })

  it('converts youtu.be short URL to embed URL', () => {
    const result = parseEmbedUrl('https://youtu.be/dQw4w9WgXcQ')
    expect(result).not.toBeNull()
    expect(result.type).toBe('youtube')
    expect(result.embedUrl).toBe('https://www.youtube.com/embed/dQw4w9WgXcQ')
  })

  it('returns youtube type for already-embed YouTube URL', () => {
    const result = parseEmbedUrl('https://www.youtube.com/embed/dQw4w9WgXcQ')
    expect(result).not.toBeNull()
    expect(result.type).toBe('youtube')
    expect(result.embedUrl).toBe('https://www.youtube.com/embed/dQw4w9WgXcQ')
  })

  it('converts vimeo.com/ID to player.vimeo.com/video/ID', () => {
    const result = parseEmbedUrl('https://vimeo.com/123456789')
    expect(result).not.toBeNull()
    expect(result.type).toBe('vimeo')
    expect(result.embedUrl).toBe('https://player.vimeo.com/video/123456789')
  })

  it('returns null for an invalid / unrecognized URL', () => {
    expect(parseEmbedUrl('https://example.com/video')).toBeNull()
  })

  it('returns null for null input', () => {
    expect(parseEmbedUrl(null)).toBeNull()
  })

  it('returns null for empty string', () => {
    expect(parseEmbedUrl('')).toBeNull()
  })
})
