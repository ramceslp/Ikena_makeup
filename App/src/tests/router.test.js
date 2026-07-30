import { describe, it, expect, vi, afterEach } from 'vitest'
import { isNavigationFailure, NavigationFailureType } from 'vue-router'
import router, { resolveGuard } from '../router/index.js'

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
    expect(paths).toContain('/cursos')
    expect(paths).toContain('/cursos/:slug')
    expect(paths).toContain('/products')
    expect(paths).toContain('/products/:slug')
    expect(paths).toContain('/services')
    expect(paths).toContain('/services/:slug')
    expect(paths).toContain('/cart')
    expect(paths).toContain('/profile')
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

// mobile-capacitor-setup Phase 8, task 8.9 — Profile/history is the app's
// first auth-gated route (unlike /cart, which intentionally allows guest
// carts — see frontend/src/router/index.js's own comment on that). This
// establishes the requiresAuth meta convention for App/ (mirroring
// frontend/src/router/index.js's meta shape) plus a real navigation guard,
// neither of which existed anywhere in App/src/router/index.js before this.
describe('router (App) — /profile is auth-gated [Spec: Profile and History]', () => {
  it('registers /profile with requiresAuth meta', () => {
    const profileRoute = router.getRoutes().find((r) => r.path === '/profile')
    expect(profileRoute).toBeDefined()
    expect(profileRoute.meta?.requiresAuth).toBe(true)
  })
})

// Pure guard-decision function, unit-tested directly with a stubbed auth
// store — same convention as frontend/src/tests/router.guard.test.js.
// App only ever needs the requiresAuth branch: there is no admin/instructor
// surface (Phase 7's own invariant, asserted above) and no requiresGuest
// route (native Google Sign-In's /login has no meta restricting it).
describe('resolveGuard (App)', () => {
  function makeTo(meta, path = '/profile') {
    return { meta, fullPath: path }
  }

  function makeAuth(isAuthenticated) {
    return { isAuthenticated }
  }

  it('unauthenticated user hitting a requiresAuth route is redirected to login with a redirect query', () => {
    const result = resolveGuard(makeTo({ requiresAuth: true }), makeAuth(false))
    expect(result).toEqual({ name: 'login', query: { redirect: '/profile' } })
  })

  it('authenticated user is allowed to proceed to a requiresAuth route', () => {
    const result = resolveGuard(makeTo({ requiresAuth: true }), makeAuth(true))
    expect(result).toBeNull()
  })

  it('proceeds for a public route (no meta) regardless of auth state', () => {
    expect(resolveGuard(makeTo({}), makeAuth(false))).toBeNull()
    expect(resolveGuard(makeTo({}), makeAuth(true))).toBeNull()
  })
})

// [Judgment Day fix, PR8d Round 1]: the real registered router.beforeEach
// dynamically imports stores/auth.js with no .catch(). If that import
// rejects (a chunk-load failure -- the same failure class services/api.js's
// handleResponseError comment already anticipates for the sibling /login
// lazy-import path), next() was never called at all, hanging the pending
// navigation forever for EVERY route, not just /profile. Exercises the REAL
// registered guard (via a freshly re-imported router module, so the mocked
// rejection is picked up), not just the extracted pure resolveGuard().
describe('router beforeEach guard (App) — dynamic import failure defense', () => {
  afterEach(() => {
    vi.doUnmock('../stores/auth.js')
    vi.resetModules()
  })

  it('calls next(false) instead of hanging when the dynamic import of stores/auth.js rejects', async () => {
    vi.doMock('../stores/auth.js', () => {
      throw new Error('Simulated chunk-load failure')
    })
    vi.resetModules()

    const { default: freshRouter } = await import('../router/index.js')
    const result = await freshRouter.push('/profile')

    expect(isNavigationFailure(result, NavigationFailureType.aborted)).toBe(true)
  })
})
