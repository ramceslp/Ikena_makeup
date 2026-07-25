import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    name: 'home',
    component: () => import('../views/Home.vue'),
  },
  {
    // Placeholder route so the 401 response interceptor's redirect target
    // (see services/api.js) resolves to a real route. Replaced by the
    // ported Login view/logic in PR 6 (mobile-capacitor-setup Phase 6).
    path: '/login',
    name: 'login',
    component: () => import('../views/Login.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
