import { createRouter, createWebHistory } from 'vue-router'
import Home from '../views/Home.vue'
import Login from '../views/Login.vue'
import Register from '../views/Register.vue'
import CourseDetail from '../views/CourseDetail.vue'
import MyCourses from '../views/MyCourses.vue'
import Player from '../views/Player.vue'
import Checkout from '../views/Checkout.vue'
import PaymentCallback from '../views/PaymentCallback.vue'

const Certificate = () => import('../views/Certificate.vue')
const InstructorCourses = () => import('../views/InstructorCourses.vue')
const InstructorCourseForm = () => import('../views/InstructorCourseForm.vue')
const InstructorCourseEdit = () => import('../views/InstructorCourseEdit.vue')
const InstructorDashboard = () => import('../views/InstructorDashboard.vue')
const InstructorSubmissions = () => import('../views/InstructorSubmissions.vue')
const Profile = () => import('../views/Profile.vue')

// Products — public
const Products = () => import('../views/Products.vue')
const ProductDetail = () => import('../views/ProductDetail.vue')
const CartView = () => import('../views/Cart.vue')

// Services — public
const Services = () => import('../views/Services.vue')
const ServiceDetail = () => import('../views/ServiceDetail.vue')

// Services — admin
const AdminServices = () => import('../views/admin/AdminServices.vue')
const AdminServiceCreate = () => import('../views/admin/AdminServiceCreate.vue')
const AdminServiceEdit = () => import('../views/admin/AdminServiceEdit.vue')

// Products — admin
const AdminProducts = () => import('../views/admin/AdminProducts.vue')
const AdminProductCreate = () => import('../views/admin/AdminProductCreate.vue')
const AdminProductEdit = () => import('../views/admin/AdminProductEdit.vue')

// News (noticias) — public
const News = () => import('../views/News.vue')
const NewsDetail = () => import('../views/NewsDetail.vue')

// News (noticias) — admin
const AdminPosts = () => import('../views/admin/AdminPosts.vue')
const AdminPostCreate = () => import('../views/admin/AdminPostCreate.vue')
const AdminPostEdit = () => import('../views/admin/AdminPostEdit.vue')

// Booking admin
const AdminServiceSlots = () => import('../views/admin/AdminServiceSlots.vue')
const AdminAppointments = () => import('../views/admin/AdminAppointments.vue')

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
    path: '/profile',
    name: 'Profile',
    component: Profile,
    meta: { requiresAuth: true },
  },
  {
    path: '/courses/:slug/certificate',
    name: 'Certificate',
    component: Certificate,
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
    path: '/instructor/submissions',
    name: 'InstructorSubmissions',
    component: InstructorSubmissions,
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

  // ── Public: Products ─────────────────────────────────────────────────────
  {
    path: '/products',
    name: 'Products',
    component: Products,
  },
  {
    path: '/products/:slug',
    name: 'ProductDetail',
    component: ProductDetail,
  },
  {
    // Intentionally NO requiresAuth: guest carts are allowed.
    // The 401 returned by the checkout endpoint handles auth enforcement,
    // so adding requiresAuth here would break the guest-cart flow.
    path: '/cart',
    name: 'Cart',
    component: CartView,
  },

  // ── Public: Services ──────────────────────────────────────────────────────
  {
    path: '/services',
    name: 'Services',
    component: Services,
  },
  {
    path: '/services/:slug',
    name: 'ServiceDetail',
    component: ServiceDetail,
  },

  // ── Admin: Services ───────────────────────────────────────────────────────
  {
    path: '/admin/services',
    name: 'AdminServices',
    component: AdminServices,
    meta: { requiresAdmin: true },
  },
  {
    path: '/admin/services/new',
    name: 'AdminServiceCreate',
    component: AdminServiceCreate,
    meta: { requiresAdmin: true },
  },
  {
    path: '/admin/services/:id/edit',
    name: 'AdminServiceEdit',
    component: AdminServiceEdit,
    meta: { requiresAdmin: true },
  },

  // ── Admin: Products ───────────────────────────────────────────────────────
  {
    path: '/admin/products',
    name: 'AdminProducts',
    component: AdminProducts,
    meta: { requiresAdmin: true },
  },
  {
    path: '/admin/products/new',
    name: 'AdminProductCreate',
    component: AdminProductCreate,
    meta: { requiresAdmin: true },
  },
  {
    path: '/admin/products/:id/edit',
    name: 'AdminProductEdit',
    component: AdminProductEdit,
    meta: { requiresAdmin: true },
  },

  // ── Public: News (noticias) ───────────────────────────────────────────────
  {
    path: '/noticias',
    name: 'News',
    component: News,
  },
  {
    path: '/noticias/:slug',
    name: 'NewsDetail',
    component: NewsDetail,
  },

  // ── Admin: Posts (noticias) ───────────────────────────────────────────────
  {
    path: '/admin/posts',
    name: 'AdminPosts',
    component: AdminPosts,
    meta: { requiresAdmin: true },
  },
  {
    path: '/admin/posts/new',
    name: 'AdminPostCreate',
    component: AdminPostCreate,
    meta: { requiresAdmin: true },
  },
  {
    path: '/admin/posts/:id/edit',
    name: 'AdminPostEdit',
    component: AdminPostEdit,
    meta: { requiresAdmin: true },
  },

  // ── Admin: Booking / Slots / Appointments ────────────────────────────────
  {
    path: '/admin/services/:id/slots',
    name: 'AdminServiceSlots',
    component: AdminServiceSlots,
    meta: { requiresAdmin: true },
  },
  {
    path: '/admin/appointments',
    name: 'AdminAppointments',
    component: AdminAppointments,
    meta: { requiresAdmin: true },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})

/**
 * Pure guard-decision function — extracted so it can be unit-tested without
 * needing a running router or real navigation.
 *
 * Returns:
 *   null             → proceed (call next() with no arguments)
 *   { name, ... }   → redirect target (call next(destination))
 */
export function resolveGuard(to, authStore) {
  if (to.meta.requiresAdmin) {
    if (!authStore.isAuthenticated) {
      return { name: 'Login', query: { redirect: to.fullPath } }
    }
    if (authStore.user?.role !== 'admin') {
      return { name: 'Home' }
    }
    return null
  }

  if (to.meta.requiresInstructor) {
    if (!authStore.isAuthenticated) {
      return { name: 'Login', query: { redirect: to.fullPath } }
    }
    const role = authStore.user?.role
    if (role !== 'instructor' && role !== 'admin') {
      return { name: 'Home' }
    }
    return null
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { name: 'Login', query: { redirect: to.fullPath } }
  }

  if (to.meta.requiresGuest && authStore.isAuthenticated) {
    return { name: 'Home' }
  }

  return null
}

router.beforeEach((to, _from, next) => {
  // Import the store inside the guard to avoid circular dependency issues.
  // By the time any navigation fires, pinia is already installed via main.js.
  import('../stores/auth.js').then(({ useAuthStore }) => {
    const authStore = useAuthStore()
    const destination = resolveGuard(to, authStore)
    if (destination !== null) {
      next(destination)
    } else {
      next()
    }
  })
})

export default router
