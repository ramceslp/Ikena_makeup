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
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
