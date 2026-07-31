import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { reactive } from 'vue'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

// Profile.vue reads (never triggers) push-registration status — see the
// header comment in views/Profile.vue for the full reasoning. Mocked as its
// own store double (same convention as auth.store.test.js mocking
// stores/push.js) so this stays a pure view test, not dragging in the real
// store's native-plugin/HTTP dependencies just to read two fields.
const pushState = reactive({ registered: false, permissionState: null, error: null })
vi.mock('../stores/push.js', () => ({
  usePushStore: vi.fn(() => pushState),
}))

// Logout (destructive-adjacent) is exercised as a pure view test too — same
// convention as Login.test.js mocking stores/auth.js so the click handler
// drives a controllable fake instead of the real store's HTTP/Preferences
// calls.
vi.mock('../stores/auth.js', () => ({
  useAuthStore: vi.fn(),
}))

import api from '../services/api.js'
import { useAuthStore } from '../stores/auth.js'
import Profile from '../views/Profile.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/profile', component: Profile, name: 'profile' },
      { path: '/login', component: { template: '<div/>' }, name: 'login' },
      { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
    ],
  })
}

const fakeOrders = [
  { id: 2, type: 'product_cart', status: 'paid', amount_cents: 5000, currency: 'USD', created_at: '2026-07-20T10:00:00Z', items: [{ product_title: 'Paleta', quantity: 1, line_total_cents: 5000 }] },
  { id: 1, type: 'appointment', status: 'confirmed', currency: 'USD', created_at: '2026-07-01T10:00:00Z', appointment: { service_title: 'Corte', scheduled_date: '2026-07-05', scheduled_time: '10:00', deposit_amount_cents: 1000 } },
]

// GET /products (per_page: 1) is the connectivity probe shared with
// Home.vue/Products.vue/Services.vue (Phase 6-7 convention). GET
// /profile/orders is the history fetch (stores/profile.js).
const fakeMyCourses = [
  {
    id: 4,
    title: 'Maquillaje Nupcial',
    slug: 'maquillaje-nupcial',
    thumbnail: null,
    instructor: { id: 3, name: 'Ana Torres' },
    total_lessons: 10,
    completed_lessons: 4,
    progress_percentage: 40,
    web_url: 'https://ikena.test/learn/maquillaje-nupcial',
  },
]

const fakeUpcoming = [
  {
    id: 11,
    scheduled_date: '2026-08-01',
    scheduled_time: '10:00',
    scheduled_end_time: '12:00',
    status: 'paid',
    deposit_amount_cents: 4000,
    service: { id: 5, title: 'Maquillaje de Novia', slug: 'maquillaje-novia', thumbnail: null, duration_hours: 2 },
    order: { id: 3, status: 'paid', amount_cents: 4000, currency: 'USD' },
  },
]

function mockApi({
  probe = 'ok',
  orders = fakeOrders,
  ordersFail = false,
  myCourses = fakeMyCourses,
  upcoming = fakeUpcoming,
  past = [],
} = {}) {
  api.get.mockImplementation((url, config) => {
    if (url === '/products') {
      if (probe === 'error') {
        return Promise.reject(new Error('Network Error'))
      }
      return Promise.resolve({ data: { data: [], meta: {} } })
    }
    if (url === '/profile/orders') {
      if (ordersFail) {
        return Promise.reject(new Error('server down'))
      }
      return Promise.resolve({ data: { data: orders, meta: { current_page: 1, last_page: 1 } } })
    }
    // "Mis cursos" (stores/myCourses.js).
    if (url === '/my-courses') {
      return Promise.resolve({ data: { data: myCourses } })
    }
    // "Mi agenda" (stores/appointments.js) — two scopes, two requests.
    if (url === '/profile/appointments') {
      return Promise.resolve({
        data: {
          data: config?.params?.scope === 'past' ? past : upcoming,
          meta: { current_page: 1, last_page: 1 },
        },
      })
    }
    return Promise.reject(new Error(`unexpected url ${url}`))
  })
}

describe('Profile.vue (App) — history [Spec: history loads / no history yet]', () => {
  let router

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    pushState.registered = false
    pushState.permissionState = null
    pushState.error = null
    useAuthStore.mockReturnValue({ logout: vi.fn().mockResolvedValue(undefined) })
    router = makeRouter()
    await router.push('/profile')
  })

  it('renders history entries reverse-chronological, in the order the API returns them [Spec: history loads]', async () => {
    mockApi()
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()

    const rows = wrapper.findAll('[data-history-row]')
    expect(rows).toHaveLength(2)
    // fakeOrders[0] (id 2, created_at 2026-07-20) is newer than fakeOrders[1]
    // (id 1, created_at 2026-07-01) — asserting DOM order matches API-array
    // order proves the view renders as-received, no client re-sort.
    expect(rows[0].text()).toContain('Paleta')
    expect(rows[1].text()).toContain('Corte')
  })

  it('shows an empty state, not an error, for a new user with no history [Spec: no history yet]', async () => {
    mockApi({ orders: [] })
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-history-empty]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Aún no tienes compras')
    expect(wrapper.find('[data-history-error]').exists()).toBe(false)
    expect(wrapper.find('[data-history-row]').exists()).toBe(false)
  })

  it('shows a connectivity error instead of stale/cached data when offline', async () => {
    mockApi({ probe: 'error' })
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-profile-error]').exists()).toBe(true)
    expect(wrapper.find('[data-history-row]').exists()).toBe(false)
  })

  it('shows a loading indicator while the connectivity probe is pending (no blank screen)', async () => {
    let resolveProbe
    api.get.mockImplementation((url, config) => {
      if (url === '/products') {
        return new Promise((resolve) => { resolveProbe = resolve })
      }
      return Promise.resolve({ data: { data: fakeOrders, meta: {} } })
    })

    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await Promise.resolve()
    await Promise.resolve()

    expect(wrapper.find('[data-profile-checking]').exists()).toBe(true)
    expect(wrapper.find('[data-history-row]').exists()).toBe(false)

    resolveProbe({ data: { data: [], meta: {} } })
    await flushPromises()

    expect(wrapper.find('[data-profile-checking]').exists()).toBe(false)
  })

  it('shows the history error banner (not the empty state) when the orders fetch itself fails after connectivity is confirmed', async () => {
    mockApi({ ordersFail: true })
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-history-error]').exists()).toBe(true)
    expect(wrapper.find('[data-history-empty]').exists()).toBe(false)
    expect(wrapper.find('[data-history-row]').exists()).toBe(false)
  })

  // ── Push-notification status row (read-only) ────────────────────────────
  // Judgment Day PR8c follow-up: push.js's silent-by-design `error`/
  // `permissionState` fields were flagged as only justified if a later
  // screen actually surfaces them. This is that screen — a read-only status
  // line, deliberately with NO re-request-permission action (see
  // views/Profile.vue's header comment for why that stays out of scope).

  it('shows "Activadas" when push registration already succeeded', async () => {
    pushState.registered = true
    mockApi()
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-notification-status]').text()).toContain('Activadas')
  })

  it('shows "Desactivadas" when the user denied push permission', async () => {
    pushState.registered = false
    pushState.permissionState = 'denied'
    mockApi()
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-notification-status]').text()).toContain('Desactivadas')
  })

  it('shows "Pendiente" when push registration has not resolved either way yet', async () => {
    mockApi()
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-notification-status]').text()).toContain('Pendiente')
  })

  // [Judgment Day fix, PR8d Round 1]: pushStore.error was recorded by
  // push.js (register-call-failed/backend-registration-failed/native-
  // registration-failed) but never rendered anywhere -- a third instance of
  // this codebase's "state set but never rendered" bug class if left alone.
  it('shows "Error" when push permission was granted but registration then failed', async () => {
    pushState.registered = false
    pushState.permissionState = 'granted'
    pushState.error = 'backend-registration-failed'
    mockApi()
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-notification-status]').text()).toContain('Error')
  })
})

// ── Logout ────────────────────────────────────────────────────────────────
// Surfaced ONLY from Profile.vue, in its own "danger zone" region, separate
// from the history card and never in the bottom tab bar (Skill:
// destructive-nav-separation). Requires an explicit inline confirmation
// step before it fires (Skill: confirmation-dialogs) since it ends the
// session. authStore.logout() itself never rejects and never redirects on
// its own (see stores/auth.js/auth.store.test.js) — Profile.vue's handler
// owns the post-logout navigation to /login, asserted here as a pure view
// test against a mocked store (same convention as Login.test.js).
describe('Profile.vue (App) — logout', () => {
  let router
  let logoutMock

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    pushState.registered = false
    pushState.permissionState = null
    pushState.error = null
    logoutMock = vi.fn().mockResolvedValue(undefined)
    useAuthStore.mockReturnValue({ logout: logoutMock })
    mockApi({ orders: [] })
    router = makeRouter()
    await router.push('/profile')
  })

  it('renders the logout action inside its own danger-zone region, not inside the history card', async () => {
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()

    const dangerZone = wrapper.find('[data-danger-zone]')
    expect(dangerZone.exists()).toBe(true)
    expect(dangerZone.find('[data-logout-btn]').exists()).toBe(true)
    // The danger zone must not be nested inside the purchase-history card —
    // spatial separation, not just a different button style.
    expect(wrapper.find('[data-danger-zone] [data-history-empty]').exists()).toBe(false)
  })

  it('tapping "Cerrar sesión" shows an inline confirmation instead of logging out immediately', async () => {
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()

    await wrapper.find('[data-logout-btn]').trigger('click')

    expect(logoutMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-logout-confirm]').exists()).toBe(true)
  })

  it('tapping "Cancelar" dismisses the confirmation without logging out', async () => {
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()
    await wrapper.find('[data-logout-btn]').trigger('click')

    await wrapper.find('[data-logout-cancel]').trigger('click')

    expect(logoutMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-logout-confirm]').exists()).toBe(false)
    expect(wrapper.find('[data-logout-btn]').exists()).toBe(true)
  })

  it('confirming logout calls authStore.logout() and redirects to /login', async () => {
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()
    await wrapper.find('[data-logout-btn]').trigger('click')

    await wrapper.find('[data-logout-confirm-btn]').trigger('click')
    await flushPromises()

    expect(logoutMock).toHaveBeenCalledTimes(1)
    expect(router.currentRoute.value.path).toBe('/login')
  })

  it('shows a loading state on the confirm button while logout is in flight, and disables both buttons', async () => {
    let resolveLogout
    logoutMock.mockReturnValueOnce(new Promise((resolve) => { resolveLogout = resolve }))

    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()
    await wrapper.find('[data-logout-btn]').trigger('click')
    await wrapper.find('[data-logout-confirm-btn]').trigger('click')

    expect(wrapper.find('[data-logout-confirm-btn]').text()).toContain('Cerrando sesión')
    expect(wrapper.find('[data-logout-confirm-btn]').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-logout-cancel]').attributes('disabled')).toBeDefined()

    resolveLogout()
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/login')
  })
})

// ── The two account sections added alongside the purchase history ───────────
// "Mis cursos" and "Mi agenda". The agenda in particular had NO customer-facing
// endpoint at all before this — appointments were readable only through the
// admin list, so a customer could not check when their next appointment was.

describe('Profile.vue (App) — mis cursos y mi agenda', () => {
  let router

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    pushState.registered = false
    pushState.permissionState = null
    pushState.error = null
    useAuthStore.mockReturnValue({ logout: vi.fn().mockResolvedValue(undefined) })
    router = makeRouter()
    await router.push('/profile')
  })

  it('renders all three account sections', async () => {
    mockApi()
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-my-courses-card]').exists()).toBe(true)
    expect(wrapper.find('[data-agenda-card]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Historial de compras')
  })

  it('loads enrolled courses and the agenda alongside the purchase history', async () => {
    mockApi()
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/my-courses')
    expect(api.get).toHaveBeenCalledWith('/profile/appointments', {
      params: { scope: 'upcoming', page: 1 },
    })
    expect(api.get).toHaveBeenCalledWith('/profile/appointments', {
      params: { scope: 'past', page: 1 },
    })

    expect(wrapper.findAll('[data-my-course-row]')).toHaveLength(1)
    expect(wrapper.findAll('[data-agenda-row]')).toHaveLength(1)
    expect(wrapper.text()).toContain('Maquillaje de Novia')
  })

  /**
   * The three sections are fetched independently and each renders its own
   * state, so one failing must not take the others down with it.
   */
  it('keeps the other sections usable when the history fetch fails', async () => {
    mockApi({ ordersFail: true })
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-history-error]').exists()).toBe(true)
    expect(wrapper.findAll('[data-my-course-row]')).toHaveLength(1)
    expect(wrapper.findAll('[data-agenda-row]')).toHaveLength(1)
  })

  it('shows per-section empty states for an account with nothing in it', async () => {
    mockApi({ orders: [], myCourses: [], upcoming: [], past: [] })
    const wrapper = mount(Profile, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-my-courses-empty]').exists()).toBe(true)
    expect(wrapper.find('[data-agenda-empty]').exists()).toBe(true)
    expect(wrapper.find('[data-history-empty]').exists()).toBe(true)
  })

  it('does not fetch any account data when the connectivity probe fails', async () => {
    mockApi({ probe: 'error' })
    mount(Profile, { global: { plugins: [router] } })
    await flushPromises()

    expect(api.get).not.toHaveBeenCalledWith('/my-courses')
    expect(api.get).not.toHaveBeenCalledWith('/profile/appointments', expect.anything())
  })
})
