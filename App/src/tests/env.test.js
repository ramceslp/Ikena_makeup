import { describe, it, expect, vi, afterEach } from 'vitest'
import {
  resolveApiBaseUrl,
  resolveGoogleWebClientId,
  GOOGLE_WEB_CLIENT_ID,
  resolvePushEnabled,
  PUSH_ENABLED,
} from '../config/env.js'

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

describe('resolvePushEnabled', () => {
  // See env.js's PUSH_ENABLED doc comment for the full incident writeup:
  // PushNotifications.register() throws natively (unreachable from JS
  // try/catch, fatal to the whole process) when google-services.json is
  // absent, so the default MUST be false -- an unset env var must fail
  // safe, not fail fatal. Only the exact string 'true' enables the flag;
  // every other value (including '1', which some boolean-env conventions
  // treat as truthy) is intentionally treated as disabled, so there is
  // exactly one unambiguous way to turn this on.
  it('is disabled when the env var is unset (undefined) -- the safe default', () => {
    expect(resolvePushEnabled(undefined)).toBe(false)
  })

  it('is enabled only for the exact string "true"', () => {
    expect(resolvePushEnabled('true')).toBe(true)
  })

  it('is disabled for the string "false"', () => {
    expect(resolvePushEnabled('false')).toBe(false)
  })

  it('is disabled for the string "0"', () => {
    expect(resolvePushEnabled('0')).toBe(false)
  })

  it('is disabled for the empty string', () => {
    expect(resolvePushEnabled('')).toBe(false)
  })

  it('is disabled for the string "1" (not treated as truthy -- only "true" enables)', () => {
    expect(resolvePushEnabled('1')).toBe(false)
  })
})

describe('PUSH_ENABLED', () => {
  it('is exported as a boolean and never throws at import time, even when unset', () => {
    expect(typeof PUSH_ENABLED).toBe('boolean')
  })
})
