/**
 * Router test for the /checkout/resume route (mobile-capacitor-setup PR 4).
 * Validates the route resolves correctly and is intentionally public
 * (no requiresAuth) — the single-use token in the URL fragment is the
 * authorization mechanism, not a logged-in session.
 */
import { describe, it, expect } from 'vitest'
import router from '../router/index.js'

function findRoute(name) {
  return router.getRoutes().find((r) => r.name === name)
}

describe('checkout-resume route', () => {
  it('/checkout/resume route exists with name CheckoutResume', () => {
    const route = findRoute('CheckoutResume')
    expect(route).toBeDefined()
    expect(route.path).toBe('/checkout/resume')
  })

  it('/checkout/resume route has no requiresAuth meta', () => {
    const route = findRoute('CheckoutResume')
    expect(route.meta?.requiresAuth).toBeFalsy()
  })
})
