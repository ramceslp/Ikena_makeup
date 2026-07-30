import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    name: 'home',
    component: () => import('../views/Home.vue'),
  },
  {
    // Native Google Sign-In login (see views/Login.vue, mobile-capacitor-
    // setup Phase 6). Also the 401 response interceptor's redirect target
    // (see services/api.js).
    path: '/login',
    name: 'login',
    component: () => import('../views/Login.vue'),
    // Only route without the app's navigation chrome. AppShell reads this
    // flag (see components/layout/AppShell.vue) — an auth entry screen is
    // full-screen by convention, and the tab bar would offer destinations
    // that all bounce straight back here. Every other route keeps full
    // chrome so navigation stays reachable from deep pages.
    meta: { hideChrome: true },
  },
  // Product + Service catalog/booking (mobile-capacitor-setup Phase 7). No
  // admin/instructor routes exist anywhere in this router — see the spec's
  // Mobile App Boundaries ("admin route unreachable from app") and
  // src/tests/router.test.js, which asserts this invariant directly.
  {
    path: '/products',
    name: 'products',
    component: () => import('../views/Products.vue'),
  },
  {
    path: '/products/:slug',
    name: 'product-detail',
    component: () => import('../views/ProductDetail.vue'),
  },
  {
    path: '/services',
    name: 'services',
    component: () => import('../views/Services.vue'),
  },
  {
    path: '/services/:slug',
    name: 'service-detail',
    component: () => import('../views/ServiceDetail.vue'),
  },
  // Cart build/manage UX (mobile-capacitor-setup Phase 8, task 8.1). The
  // "pay" action (checkout-handoff + @capacitor/browser) is wired in a later
  // PR (tasks 8.3-8.5) — see views/Cart.vue's header comment.
  {
    path: '/cart',
    name: 'cart',
    component: () => import('../views/Cart.vue'),
  },
  // Profile/history (mobile-capacitor-setup Phase 8, tasks 8.9-8.10). The
  // app's FIRST auth-gated route — unlike /cart (guest carts intentionally
  // allowed, see frontend/src/router/index.js's own comment on that), a
  // user's purchase/appointment history is meaningless without a session, so
  // it carries `requiresAuth: true` (same meta shape as frontend's
  // /profile route) and is enforced by the beforeEach guard below.
  {
    path: '/profile',
    name: 'profile',
    component: () => import('../views/Profile.vue'),
    meta: { requiresAuth: true },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

/**
 * Pure guard-decision function — extracted so it can be unit-tested without
 * needing a running router or real navigation (same convention as
 * frontend/src/router/index.js's resolveGuard). App only ever needs the
 * requiresAuth branch: there is no admin/instructor surface (Phase 7's own
 * invariant — see router.test.js) and no requiresGuest-restricted route.
 *
 * Returns:
 *   null           → proceed (call next() with no arguments)
 *   { name, ... }  → redirect target (call next(destination))
 */
export function resolveGuard(to, authStore) {
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  return null
}

router.beforeEach((to, _from, next) => {
  // Import the store inside the guard to avoid a circular-dependency chain:
  // router/index.js -> stores/auth.js -> services/api.js -> router/index.js.
  // By the time any navigation fires, Pinia is already installed via
  // main.js's bootstrap(), same precondition frontend's guard relies on.
  import('../stores/auth.js')
    .then(({ useAuthStore }) => {
      const authStore = useAuthStore()
      const destination = resolveGuard(to, authStore)
      if (destination !== null) {
        next(destination)
      } else {
        next()
      }
    })
    .catch(() => {
      // [Judgment Day fix, PR8d Round 1]: a rejected dynamic import (chunk-
      // load failure, e.g. a flaky mobile connection -- the exact failure
      // class services/api.js's handleResponseError comment already
      // anticipates for the sibling /login lazy-import path) previously left
      // next() uncalled entirely, hanging the pending navigation forever, on
      // EVERY route in the app (this guard runs on every navigation, not
      // just /profile). next(false) cancels the navigation cleanly instead.
      next(false)
    })
})

export default router
