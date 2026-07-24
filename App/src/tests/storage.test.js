import { describe, it, expect, vi, beforeEach } from 'vitest'

// ---------------------------------------------------------------------------
// Mock the @capacitor/preferences plugin BEFORE importing storage.js, so the
// module under test receives the mock instance instead of the real native
// bridge (which isn't available in a jsdom/vitest environment).
// ---------------------------------------------------------------------------
vi.mock('@capacitor/preferences', () => ({
  Preferences: {
    get: vi.fn(),
    set: vi.fn(),
    remove: vi.fn(),
  },
}))

import { Preferences } from '@capacitor/preferences'
import { get, set, remove, getCached, hydrate, TOKEN_KEY, USER_KEY } from '../services/storage.js'

describe('storage service', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('set() persists a JSON-serialized value via Preferences.set and updates the cache', async () => {
    Preferences.set.mockResolvedValueOnce(undefined)

    await set(TOKEN_KEY, 'abc123')

    expect(Preferences.set).toHaveBeenCalledWith({ key: TOKEN_KEY, value: JSON.stringify('abc123') })
    expect(getCached(TOKEN_KEY)).toBe('abc123')
  })

  it('get() reads and JSON-parses a value from Preferences', async () => {
    Preferences.get.mockResolvedValueOnce({ value: JSON.stringify({ id: 1, name: 'Test' }) })

    const result = await get(USER_KEY)

    expect(result).toEqual({ id: 1, name: 'Test' })
    expect(Preferences.get).toHaveBeenCalledWith({ key: USER_KEY })
  })

  it('get() returns null when Preferences has no stored value', async () => {
    Preferences.get.mockResolvedValueOnce({ value: null })

    const result = await get(TOKEN_KEY)

    expect(result).toBeNull()
  })

  it('get() updates the cache so a subsequent getCached() call returns the same parsed value', async () => {
    Preferences.get.mockResolvedValueOnce({ value: JSON.stringify('fresh-token') })

    await get(TOKEN_KEY)

    expect(getCached(TOKEN_KEY)).toBe('fresh-token')
  })

  it('remove() calls Preferences.remove and clears the cached value', async () => {
    Preferences.set.mockResolvedValueOnce(undefined)
    Preferences.remove.mockResolvedValueOnce(undefined)

    await set(TOKEN_KEY, 'to-be-removed')
    await remove(TOKEN_KEY)

    expect(Preferences.remove).toHaveBeenCalledWith({ key: TOKEN_KEY })
    expect(getCached(TOKEN_KEY)).toBeNull()
  })

  it('hydrate() reads TOKEN_KEY and USER_KEY from Preferences and returns both parsed values', async () => {
    Preferences.get
      .mockResolvedValueOnce({ value: JSON.stringify('hydrated-token') })
      .mockResolvedValueOnce({ value: JSON.stringify({ id: 2, name: 'Hydrated User' }) })

    const result = await hydrate()

    expect(result).toEqual({ token: 'hydrated-token', user: { id: 2, name: 'Hydrated User' } })
    expect(getCached(TOKEN_KEY)).toBe('hydrated-token')
    expect(getCached(USER_KEY)).toEqual({ id: 2, name: 'Hydrated User' })
  })
})
