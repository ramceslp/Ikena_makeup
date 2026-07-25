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

import api from '../services/api.js'
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
function mockApi({ probe = 'ok', orders = fakeOrders, ordersFail = false } = {}) {
  api.get.mockImplementation((url) => {
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
    api.get.mockImplementation((url) => {
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
