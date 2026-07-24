import { describe, it, expect } from 'vitest'
import { resolveApiBaseUrl, GOOGLE_WEB_CLIENT_ID } from '../config/env.js'

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
