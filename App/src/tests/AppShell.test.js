import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('../services/storage.js', () => ({
  set: vi.fn().mockResolvedValue(undefined),
  remove: vi.fn().mockResolvedValue(undefined),
  getCached: vi.fn().mockReturnValue(null),
  TOKEN_KEY: 'ikena_auth_token',
  USER_KEY: 'ikena_user',
  CART_KEY: 'ikena_cart',
}))

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

vi.mock('@capacitor/browser', () => ({
  Browser: { open: vi.fn().mockResolvedValue(undefined) },
}))

vi.mock('../stores/push.js', () => ({
  usePushStore: vi.fn(() => ({ init: vi.fn().mockResolvedValue(undefined) })),
}))

import AppShell from '../components/layout/AppShell.vue'

const stub = { template: '<div data-view/>' }

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: stub },
      // hideChrome is the shell's opt-out contract. The nav components
      // themselves know nothing about route names.
      { path: '/login', name: 'login', component: stub, meta: { hideChrome: true } },
      { path: '/cursos', name: 'courses', component: stub },
      { path: '/services', name: 'services', component: stub },
      { path: '/services/:slug', name: 'service-detail', component: stub },
      { path: '/products', name: 'products', component: stub },
      { path: '/products/:slug', name: 'product-detail', component: stub },
      { path: '/cart', name: 'cart', component: stub },
      { path: '/profile', name: 'profile', component: stub },
    ],
  })
}

async function mountShell(router) {
  const wrapper = mount(AppShell, { global: { plugins: [router] } })
  await router.isReady()
  return wrapper
}

describe('AppShell — chrome visibility driven by route meta [Skill: persistent-nav]', () => {
  let router

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    router = makeRouter()
  })

  it('renders the top bar and the bottom tab bar on a normal route', async () => {
    await router.push('/')
    const wrapper = await mountShell(router)

    expect(wrapper.find('header[data-app-topbar]').exists()).toBe(true)
    expect(wrapper.find('nav[data-app-bottomnav]').exists()).toBe(true)
    expect(wrapper.find('[data-view]').exists()).toBe(true)
  })

  it('keeps the nav reachable from a deep detail route', async () => {
    await router.push('/products/paleta-editorial')
    const wrapper = await mountShell(router)

    expect(wrapper.find('nav[data-app-bottomnav]').exists()).toBe(true)
  })

  it('hides the chrome on a route flagged meta.hideChrome (auth entry screen)', async () => {
    await router.push('/login')
    const wrapper = await mountShell(router)

    expect(wrapper.find('nav[data-app-bottomnav]').exists()).toBe(false)
    expect(wrapper.find('header[data-app-topbar]').exists()).toBe(false)
    expect(wrapper.find('[data-view]').exists()).toBe(true)
  })

  it('restores the chrome when navigating away from a hideChrome route', async () => {
    await router.push('/login')
    const wrapper = await mountShell(router)
    expect(wrapper.find('nav[data-app-bottomnav]').exists()).toBe(false)

    await router.push('/profile')
    await wrapper.vm.$nextTick()

    expect(wrapper.find('nav[data-app-bottomnav]').exists()).toBe(true)
    expect(wrapper.find('header[data-app-topbar]').exists()).toBe(true)
  })

  it('reserves the fixed-bar inset once, on the outlet wrapper — not per view [Skill: fixed-element-offset]', async () => {
    await router.push('/')
    const wrapper = await mountShell(router)
    const content = wrapper.find('[data-app-content]')

    expect(content.exists()).toBe(true)
    // .app-content owns the bottom inset that clears the fixed tab bar +
    // gesture bar; see the app-shell block in src/style.css.
    expect(content.classes()).toContain('app-content')
    expect(content.classes()).not.toContain('app-content-bare')
  })

  it('swaps to the bare (safe-area-only) inset when the chrome is hidden', async () => {
    await router.push('/login')
    const wrapper = await mountShell(router)
    const content = wrapper.find('[data-app-content]')

    expect(content.classes()).toContain('app-content-bare')
    expect(content.classes()).not.toContain('app-content')
  })

  it('does not nest a second <main> landmark (views own their own <main>)', async () => {
    await router.push('/')
    const wrapper = await mountShell(router)
    expect(wrapper.findAll('main')).toHaveLength(0)
  })
})

describe('router (App) — login route carries the hideChrome contract', () => {
  it('flags /login with meta.hideChrome so the shell drops the nav there', async () => {
    // Asserted against the REAL router module: the shell reads meta, so the
    // flag has to actually be on the route record, not just in a test double.
    const { default: realRouter } = await import('../router/index.js')
    const login = realRouter.getRoutes().find((r) => r.path === '/login')

    expect(login).toBeDefined()
    expect(login.meta?.hideChrome).toBe(true)
  })

  it('leaves every other route with full chrome (nav stays reachable)', async () => {
    const { default: realRouter } = await import('../router/index.js')

    for (const route of realRouter.getRoutes()) {
      if (route.path === '/login') continue
      expect(route.meta?.hideChrome).toBeUndefined()
    }
  })
})
