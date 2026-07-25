import { describe, it, expect } from 'vitest'
import router from '../router/index.js'

// mobile-capacitor-setup Phase 7, task 7.5 [Spec: admin route unreachable
// from app]. The app shell must never expose a route, link, or navigation
// entry to admin/instructor views (AdminServiceSlots, AdminAppointments,
// AdminServices, etc.) — those remain web-only (frontend/). This asserts the
// invariant directly against the REAL router module (not a test double), so
// it fails the moment anyone adds an admin route/import here.
describe('router (App) — no path to admin/instructor views [Spec: admin route unreachable from app]', () => {
  it('registers the expected public app routes (Phase 5-7)', () => {
    const paths = router.getRoutes().map((r) => r.path)
    expect(paths).toContain('/')
    expect(paths).toContain('/login')
    expect(paths).toContain('/products')
    expect(paths).toContain('/products/:slug')
    expect(paths).toContain('/services')
    expect(paths).toContain('/services/:slug')
    expect(paths).toContain('/cart')
  })

  it('has no route whose path or name references admin/instructor views', () => {
    const routes = router.getRoutes()
    expect(routes.length).toBeGreaterThan(0)

    for (const route of routes) {
      expect(route.path.toLowerCase()).not.toContain('admin')
      expect(String(route.name ?? '').toLowerCase()).not.toContain('admin')
    }
  })

  it('has no route whose lazy-loaded component import path references an admin view', () => {
    const routes = router.getRoutes()

    for (const route of routes) {
      // Route records store the raw component loader function(s) on
      // `route.components.default` (named-view shape) internally — reading
      // the ORIGINAL route config's `component` via `record.components` is
      // vue-router-internal, so inspect via the matcher's raw record instead,
      // which vue-router exposes as `route.components`.
      const loader = route.components?.default
      if (typeof loader === 'function') {
        expect(loader.toString().toLowerCase()).not.toContain('admin')
      }
    }
  })

  it('has no route with a requiresAdmin meta flag (no admin guard surface exists to bypass)', () => {
    const routes = router.getRoutes()
    for (const route of routes) {
      expect(route.meta?.requiresAdmin).toBeUndefined()
    }
  })
})
