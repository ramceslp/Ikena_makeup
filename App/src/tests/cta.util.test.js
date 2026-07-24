/**
 * Trimmed port of frontend/src/tests/cta.util.test.js: only safeCtaUrl,
 * used by the ported FeaturedNewsHero.vue/LatestNewsGrid.vue CTA links.
 * isSafeLinkUrl (TipTap editor link validator) and parseEmbedUrl (video
 * embed parsing) belong to the admin content-authoring surface, which this
 * app does not have (see spec's "Admin/instructor views excluded" non-goal).
 */
import { describe, it, expect } from 'vitest'
import { safeCtaUrl } from '../utils/cta.js'

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

  it('returns null for empty string', () => {
    expect(safeCtaUrl('')).toBeNull()
  })

  it('returns the trimmed url when input has leading/trailing whitespace', () => {
    expect(safeCtaUrl('  https://example.com  ')).toBe('https://example.com')
  })

  it('returns null for mailto: URL (CTAs are web-links only)', () => {
    expect(safeCtaUrl('mailto:a@b.com')).toBeNull()
  })
})
