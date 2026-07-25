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
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
