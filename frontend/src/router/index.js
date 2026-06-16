import { createRouter, createWebHistory } from 'vue-router'
import Home from '../views/Home.vue'
import Login from '../views/Login.vue'
import Register from '../views/Register.vue'
import CourseDetail from '../views/CourseDetail.vue'
import MyCourses from '../views/MyCourses.vue'
import Player from '../views/Player.vue'
import Checkout from '../views/Checkout.vue'
import PaymentCallback from '../views/PaymentCallback.vue'

const InstructorCourses = () => import('../views/InstructorCourses.vue')
const InstructorCourseForm = () => import('../views/InstructorCourseForm.vue')
const InstructorCourseEdit = () => import('../views/InstructorCourseEdit.vue')
const InstructorDashboard = () => import('../views/InstructorDashboard.vue')

const routes = [
  {
    path: '/',
    name: 'Home',
    component: Home,
  },
  {
    path: '/login',
    name: 'Login',
    component: Login,
    meta: { requiresGuest: true },
  },
  {
    path: '/register',
    name: 'Register',
    component: Register,
    meta: { requiresGuest: true },
  },
  {
    path: '/courses/:slug',
    name: 'CourseDetail',
    component: CourseDetail,
  },
  {
    path: '/my-courses',
    name: 'MyCourses',
    component: MyCourses,
    meta: { requiresAuth: true },
  },
  {
    path: '/learn/:slug',
    name: 'Player',
    component: Player,
    meta: { requiresAuth: true },
  },
  {
    path: '/checkout/:slug',
    name: 'Checkout',
    component: Checkout,
    meta: { requiresAuth: true },
  },
  {
    path: '/payment/callback',
    name: 'PaymentCallback',
    component: PaymentCallback,
    meta: { requiresAuth: true },
  },
  {
    path: '/instructor',
    name: 'InstructorCourses',
    component: InstructorCourses,
    meta: { requiresInstructor: true },
  },
  {
    path: '/instructor/dashboard',
    name: 'InstructorDashboard',
    component: InstructorDashboard,
    meta: { requiresInstructor: true },
  },
  {
    path: '/instructor/courses/new',
    name: 'InstructorCourseForm',
    component: InstructorCourseForm,
    meta: { requiresInstructor: true },
  },
  {
    path: '/instructor/courses/:slug/edit',
    name: 'InstructorCourseEdit',
    component: InstructorCourseEdit,
    meta: { requiresInstructor: true },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})

router.beforeEach((to, _from, next) => {
  // Import the store inside the guard to avoid circular dependency issues.
  // By the time any navigation fires, pinia is already installed via main.js.
  import('../stores/auth.js').then(({ useAuthStore }) => {
    const authStore = useAuthStore()

    if (to.meta.requiresInstructor) {
      if (!authStore.isAuthenticated) { next({ name: 'Login', query: { redirect: to.fullPath } }); return }
      if (authStore.user?.role !== 'instructor') { next({ name: 'Home' }); return }
      next()
      return
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
      next({ name: 'Login', query: { redirect: to.fullPath } })
      return
    }

    if (to.meta.requiresGuest && authStore.isAuthenticated) {
      next({ name: 'Home' })
      return
    }

    next()
  })
})

export default router
