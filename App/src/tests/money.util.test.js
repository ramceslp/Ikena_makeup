import { describe, it, expect } from 'vitest'
import { formatCurrency } from '../utils/money.js'

describe('formatCurrency', () => {
  it('formats a normal value in USD (cents → dollars)', () => {
    const result = formatCurrency(12345)
    // $123.45 — exact symbol may vary by locale but must include 123.45
    expect(result).toContain('123.45')
    expect(result).toMatch(/\$|USD/)
  })

  it('formats zero as $0.00', () => {
    const result = formatCurrency(0)
    expect(result).toContain('0.00')
  })

  it('respects the currency code parameter', () => {
    const result = formatCurrency(5000, 'EUR')
    expect(result).toContain('50.00')
    expect(result).toMatch(/€|EUR/)
  })
})
