import { describe, it, expect } from 'vitest'
import { resolveApiBaseUrl } from '../config/env.js'

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
