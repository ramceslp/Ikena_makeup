import { describe, it, expect } from 'vitest'
import { resolveVideo } from '../utils/video.js'

describe('resolveVideo', () => {
  // ── YouTube ──────────────────────────────────────────────────────────────

  it('resolves youtube.com/watch?v= to youtube type with correct embedUrl', () => {
    const result = resolveVideo('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
    expect(result.type).toBe('youtube')
    expect(result.embedUrl).toBe('https://www.youtube.com/embed/dQw4w9WgXcQ')
  })

  it('resolves youtube.com/watch?v= with extra query params', () => {
    const result = resolveVideo('https://www.youtube.com/watch?list=PL123&v=abc123XYZ')
    expect(result.type).toBe('youtube')
    expect(result.embedUrl).toBe('https://www.youtube.com/embed/abc123XYZ')
  })

  it('resolves youtu.be/<id> to youtube type', () => {
    const result = resolveVideo('https://youtu.be/dQw4w9WgXcQ')
    expect(result.type).toBe('youtube')
    expect(result.embedUrl).toBe('https://www.youtube.com/embed/dQw4w9WgXcQ')
  })

  it('resolves youtube.com/embed/<id> to youtube type', () => {
    const result = resolveVideo('https://www.youtube.com/embed/dQw4w9WgXcQ')
    expect(result.type).toBe('youtube')
    expect(result.embedUrl).toBe('https://www.youtube.com/embed/dQw4w9WgXcQ')
  })

  // ── Vimeo ─────────────────────────────────────────────────────────────────

  it('resolves vimeo.com/<digits> to vimeo type', () => {
    const result = resolveVideo('https://vimeo.com/123456789')
    expect(result.type).toBe('vimeo')
    expect(result.embedUrl).toBe('https://player.vimeo.com/video/123456789')
  })

  it('resolves player.vimeo.com/video/<id> to vimeo type', () => {
    const result = resolveVideo('https://player.vimeo.com/video/987654321')
    expect(result.type).toBe('vimeo')
    expect(result.embedUrl).toBe('https://player.vimeo.com/video/987654321')
  })

  // ── MP4 ───────────────────────────────────────────────────────────────────

  it('resolves a direct .mp4 URL to mp4 type with src', () => {
    const url = 'https://cdn.example.com/videos/lesson1.mp4'
    const result = resolveVideo(url)
    expect(result.type).toBe('mp4')
    expect(result.src).toBe(url)
  })

  it('resolves an http .mp4 URL to mp4 type', () => {
    const url = 'http://cdn.example.com/video.mp4'
    const result = resolveVideo(url)
    expect(result.type).toBe('mp4')
    expect(result.src).toBe(url)
  })

  // ── Unknown ───────────────────────────────────────────────────────────────

  it('returns unknown type for junk URLs', () => {
    const result = resolveVideo('https://somerandsite.com/video/watch')
    expect(result.type).toBe('unknown')
  })

  it('returns unknown type for null', () => {
    const result = resolveVideo(null)
    expect(result.type).toBe('unknown')
  })

  it('returns unknown type for undefined', () => {
    const result = resolveVideo(undefined)
    expect(result.type).toBe('unknown')
  })

  it('returns unknown type for empty string', () => {
    const result = resolveVideo('')
    expect(result.type).toBe('unknown')
  })

  it('returns unknown type for whitespace-only string', () => {
    const result = resolveVideo('   ')
    expect(result.type).toBe('unknown')
  })
})
