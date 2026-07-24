/**
 * Formats a monetary amount given in cents to a localized currency string.
 * @param {number} cents - Amount in cents (e.g. 12345 → $123.45)
 * @param {string} currency - ISO 4217 currency code (default: 'USD')
 * @returns {string} Formatted currency string (e.g. "$123.45")
 */
export function formatCurrency(cents, currency = 'USD') {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
  }).format(cents / 100)
}
