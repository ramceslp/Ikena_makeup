import { describe, it, expect } from 'vitest'
import { initials } from '../components/layout/initials.js'

// Ported from frontend/src/components/NavBar.vue's inline initials() helper
// (mobile-capacitor-setup Phase 9, navigation shell). Extracted to its own
// module here because the App shell needs it in the bottom tab bar's account
// tab AND it is the documented fallback whenever `user.avatar` is null (the
// backend payload includes `avatar`, but it is nullable for accounts created
// without a Google picture).
describe('initials()', () => {
  it('returns the first letter of the first two words, uppercased', () => {
    expect(initials('Ada Lovelace')).toBe('AL')
  })

  it('uses a single letter for a single-word name', () => {
    expect(initials('Ada')).toBe('A')
  })

  it('caps at two letters even for long multi-word names', () => {
    expect(initials('Maria Fernanda de los Angeles')).toBe('MF')
  })

  it('falls back to "?" for null/undefined/empty input (never renders blank)', () => {
    expect(initials(null)).toBe('?')
    expect(initials(undefined)).toBe('?')
    expect(initials('')).toBe('?')
  })

  it('ignores stray whitespace instead of emitting an empty initial', () => {
    // frontend's version splits on a single space, so '  Ada  Lovelace '
    // yields empty segments whose [0] is undefined -> 'undefined' in the
    // output. This port must not have that hole.
    expect(initials('  Ada  Lovelace ')).toBe('AL')
    expect(initials('   ')).toBe('?')
  })
})
