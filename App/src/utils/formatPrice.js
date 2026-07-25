// Shared price-formatting policy for Home's featured sections
// (FeaturedCourses.vue, FeaturedServices.vue, FeaturedProducts.vue).
//
// Before this util existed, the three components disagreed on what to do
// with a malformed/NaN price: FeaturedCourses/FeaturedServices treated
// `!num || num === 0` as "Gratis" (so a NaN silently became "free"), while
// FeaturedProducts treated `isNaN(num)` as "$0.00" (so a NaN silently became
// "zero dollars"). Both hide a real data problem behind a plausible-looking
// price, and the two components didn't even agree with each other.
//
// Deliberate policy (single source of truth):
//   - A genuine zero price (`0`, `"0"`, `"0.00"`) is a real "Gratis" course/
//     service/product -- keep showing "Gratis".
//   - A malformed/non-numeric price (NaN) is a data problem, not a real
//     price of zero or "free" -- show a neutral fallback that does NOT claim
//     a false price, instead of "$0.00" or "Gratis".
export function formatPrice(price) {
  const num = parseFloat(price)

  if (Number.isNaN(num)) {
    return 'Consultar precio'
  }

  if (num === 0) {
    return 'Gratis'
  }

  return `$${num.toFixed(2)}`
}
