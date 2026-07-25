import { describe, it, expect } from 'vitest'
import { formatPrice } from '../utils/formatPrice.js'

// Shared price-formatting policy used by FeaturedCourses.vue,
// FeaturedServices.vue and FeaturedProducts.vue -- see utils/formatPrice.js
// for why NaN/malformed prices must NOT render as a false "$0.00"/"Gratis".
describe('formatPrice', () => {
  it('formats a normal numeric price as currency', () => {
    expect(formatPrice('250.00')).toBe('$250.00')
    expect(formatPrice(120)).toBe('$120.00')
  })

  it('formats a real zero price as "Gratis"', () => {
    expect(formatPrice(0)).toBe('Gratis')
    expect(formatPrice('0')).toBe('Gratis')
    expect(formatPrice('0.00')).toBe('Gratis')
  })

  it('does NOT render a malformed/non-numeric price as a false "$0.00" or "Gratis" -- shows a neutral fallback instead', () => {
    expect(formatPrice('not-a-number')).toBe('Consultar precio')
    expect(formatPrice(undefined)).toBe('Consultar precio')
    expect(formatPrice(null)).toBe('Consultar precio')
    expect(formatPrice(NaN)).toBe('Consultar precio')
  })
})
