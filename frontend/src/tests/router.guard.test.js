/**
 * Router guard tests — requiresAdmin and requiresInstructor meta handling.
 *
 * Tests the REAL resolveGuard() exported from router/index.js.
 * A plain stubbed authStore object is passed as the second argument —
 * no mocks of the dynamic import are needed because resolveGuard is pure.
 *
 * Convention for assertions:
 *   null       → guard says "proceed" (no redirect)
 *   { name }   → guard says "redirect to this route"
 */
import { describe, it, expect, beforeEach } from 'vitest'
import { resolveGuard } from '../router/index.js'

// ---------------------------------------------------------------------------
// Helper — build a synthetic "to" route object with meta and a fullPath.
// ---------------------------------------------------------------------------
function makeTo(meta, path = '/test-route') {
  return { meta, fullPath: path }
}

// ---------------------------------------------------------------------------
// Helper — build a stubbed auth store.
// ---------------------------------------------------------------------------
function makeAuth(isAuthenticated, role = null) {
  return {
    isAuthenticated,
    user: isAuthenticated ? { role } : null,
  }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('resolveGuard', () => {
  // -------------------------------------------------------------------------
  // requiresAdmin
  // -------------------------------------------------------------------------

  describe('requiresAdmin guard', () => {
    it('unauthenticated user is redirected to Login', () => {
      const result = resolveGuard(
        makeTo({ requiresAdmin: true }),
        makeAuth(false),
      )
      expect(result).toEqual({ name: 'Login', query: { redirect: '/test-route' } })
    })

    it('student is redirected to Home', () => {
      const result = resolveGuard(
        makeTo({ requiresAdmin: true }),
        makeAuth(true, 'student'),
      )
      expect(result).toEqual({ name: 'Home' })
    })

    it('instructor is redirected to Home', () => {
      const result = resolveGuard(
        makeTo({ requiresAdmin: true }),
        makeAuth(true, 'instructor'),
      )
      expect(result).toEqual({ name: 'Home' })
    })

    it('admin is allowed to proceed', () => {
      const result = resolveGuard(
        makeTo({ requiresAdmin: true }),
        makeAuth(true, 'admin'),
      )
      expect(result).toBeNull()
    })
  })

  // -------------------------------------------------------------------------
  // requiresInstructor (existing + widened to admit admin as superuser)
  // -------------------------------------------------------------------------

  describe('requiresInstructor guard', () => {
    it('unauthenticated user is redirected to Login', () => {
      const result = resolveGuard(
        makeTo({ requiresInstructor: true }),
        makeAuth(false),
      )
      expect(result).toEqual({ name: 'Login', query: { redirect: '/test-route' } })
    })

    it('student is redirected to Home', () => {
      const result = resolveGuard(
        makeTo({ requiresInstructor: true }),
        makeAuth(true, 'student'),
      )
      expect(result).toEqual({ name: 'Home' })
    })

    it('instructor is allowed to proceed', () => {
      const result = resolveGuard(
        makeTo({ requiresInstructor: true }),
        makeAuth(true, 'instructor'),
      )
      expect(result).toBeNull()
    })

    it('admin is allowed to proceed (superuser hierarchy)', () => {
      const result = resolveGuard(
        makeTo({ requiresInstructor: true }),
        makeAuth(true, 'admin'),
      )
      expect(result).toBeNull()
    })
  })

  // -------------------------------------------------------------------------
  // requiresAuth (existing behavior — regression guard)
  // -------------------------------------------------------------------------

  describe('requiresAuth guard', () => {
    it('unauthenticated user is redirected to Login', () => {
      const result = resolveGuard(
        makeTo({ requiresAuth: true }),
        makeAuth(false),
      )
      expect(result).toEqual({ name: 'Login', query: { redirect: '/test-route' } })
    })

    it('authenticated user is allowed to proceed', () => {
      const result = resolveGuard(
        makeTo({ requiresAuth: true }),
        makeAuth(true, 'student'),
      )
      expect(result).toBeNull()
    })
  })

  // -------------------------------------------------------------------------
  // requiresGuest (existing behavior — regression guard)
  // -------------------------------------------------------------------------

  describe('requiresGuest guard', () => {
    it('authenticated user is redirected to Home', () => {
      const result = resolveGuard(
        makeTo({ requiresGuest: true }),
        makeAuth(true, 'student'),
      )
      expect(result).toEqual({ name: 'Home' })
    })

    it('unauthenticated user is allowed to proceed', () => {
      const result = resolveGuard(
        makeTo({ requiresGuest: true }),
        makeAuth(false),
      )
      expect(result).toBeNull()
    })
  })

  // -------------------------------------------------------------------------
  // No meta (public route) — regression guard
  // -------------------------------------------------------------------------

  describe('public route (no guard meta)', () => {
    it('proceeds for unauthenticated user', () => {
      const result = resolveGuard(makeTo({}), makeAuth(false))
      expect(result).toBeNull()
    })

    it('proceeds for authenticated user', () => {
      const result = resolveGuard(makeTo({}), makeAuth(true, 'student'))
      expect(result).toBeNull()
    })
  })
})
