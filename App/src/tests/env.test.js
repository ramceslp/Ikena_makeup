import { describe, it, expect, vi, afterEach } from 'vitest'
import { resolveApiBaseUrl, resolveGoogleWebClientId, GOOGLE_WEB_CLIENT_ID } from '../config/env.js'

describe('resolveApiBaseUrl', () => {
  it('returns the given URL unchanged for a non-production mode (emulator/device dev builds)', () => {
    expect(resolveApiBaseUrl('development', 'http://10.0.2.2:8000/api')).toBe('http://10.0.2.2:8000/api')
  })

  it('returns an https URL unchanged for production mode', () => {
    expect(resolveApiBaseUrl('production', 'https://api.example.com/api')).toBe('https://api.example.com/api')
  })

  it('throws when no URL is provided', () => {
    expect(() => resolveApiBaseUrl('development', undefined)).toThrow(/VITE_API_URL is not set/)
  })

  it('throws in production mode when the URL is not https (dev networking cleartext exceptions are native-layer only)', () => {
    expect(() => resolveApiBaseUrl('production', 'http://api.example.com/api')).toThrow(/HTTPS/)
  })
})

describe('GOOGLE_WEB_CLIENT_ID', () => {
  it('is exported as a string and never throws at import time, even when unset', () => {
    // Unlike VITE_API_URL (required for api.js's baseURL at import time), a
    // missing Google web client ID must not crash module import/test
    // collection -- see the VITE_API_URL CRITICAL bug fixed in PR5. Google
    // Sign-In simply won't work until it's configured; the rest of the app
    // (including every other test file that transitively imports env.js)
    // must keep working.
    expect(typeof GOOGLE_WEB_CLIENT_ID).toBe('string')
  })
})

describe('resolveGoogleWebClientId', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('returns the value unchanged in non-production mode when it is a real value', () => {
    expect(resolveGoogleWebClientId(false, 'real-client-id.apps.googleusercontent.com')).toBe(
      'real-client-id.apps.googleusercontent.com'
    )
  })

  it('warns (but does not throw) in non-production mode when the value is empty', () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {})

    expect(resolveGoogleWebClientId(false, '')).toBe('')
    expect(warnSpy).toHaveBeenCalled()
  })

  it('warns (but does not throw) in non-production mode when the value is still the committed placeholder', () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {})

    expect(resolveGoogleWebClientId(false, 'your-google-web-client-id-here')).toBe(
      'your-google-web-client-id-here'
    )
    expect(warnSpy).toHaveBeenCalled()
  })

  it('throws in production mode when the value is empty', () => {
    expect(() => resolveGoogleWebClientId(true, '')).toThrow(/VITE_GOOGLE_WEB_CLIENT_ID/)
  })

  it('throws in production mode when the value is still the committed placeholder string', () => {
    expect(() => resolveGoogleWebClientId(true, 'your-google-web-client-id-here')).toThrow(
      /VITE_GOOGLE_WEB_CLIENT_ID/
    )
  })

  it('returns the value unchanged in production mode when it is a real value', () => {
    expect(resolveGoogleWebClientId(true, 'real-client-id.apps.googleusercontent.com')).toBe(
      'real-client-id.apps.googleusercontent.com'
    )
  })
})
