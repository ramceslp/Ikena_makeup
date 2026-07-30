import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

// The tab bar reads the auth store (auth-aware 5th tab), which pulls in
// api.js / storage.js / push.js. Mocked with the same doubles already used by
// auth.store.test.js so this stays a component test, not an integration test.
vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

vi.mock('../services/storage.js', () => ({
  set: vi.fn().mockResolvedValue(undefined),
  remove: vi.fn().mockResolvedValue(undefined),
  getCached: vi.fn().mockReturnValue(null),
  TOKEN_KEY: 'ikena_auth_token',
  USER_KEY: 'ikena_user',
  CART_KEY: 'ikena_cart',
}))

vi.mock('../stores/push.js', () => ({
  usePushStore: vi.fn(() => ({ init: vi.fn().mockResolvedValue(undefined) })),
}))

import { useAuthStore } from '../stores/auth.js'
import BottomTabBar from '../components/layout/BottomTabBar.vue'

const stub = { template: '<div/>' }

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: stub },
      { path: '/login', name: 'login', component: stub },
      // `courses` is owned by a parallel agent (path /cursos). The tab bar
      // links to it by name, so it must exist for the shell to resolve.
      { path: '/cursos', name: 'courses', component: stub },
      { path: '/cursos/:slug', name: 'course-detail', component: stub },
      { path: '/services', name: 'services', component: stub },
      { path: '/services/:slug', name: 'service-detail', component: stub },
      { path: '/products', name: 'products', component: stub },
      { path: '/products/:slug', name: 'product-detail', component: stub },
      { path: '/cart', name: 'cart', component: stub },
      { path: '/profile', name: 'profile', component: stub },
    ],
  })
}

async function mountBar(router) {
  const wrapper = mount(BottomTabBar, { global: { plugins: [router] } })
  await router.isReady()
  return wrapper
}

describe('BottomTabBar — structure [Skill: bottom-nav-limit, nav-label-icon]', () => {
  let router

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    router = makeRouter()
    await router.push('/')
  })

  it('is a real <nav> landmark with an accessible name', async () => {
    const wrapper = await mountBar(router)
    const nav = wrapper.find('nav')
    expect(nav.exists()).toBe(true)
    expect(nav.attributes('aria-label')).toBeTruthy()
  })

  it('renders exactly 5 tabs — never a 6th [Skill: bottom-nav-limit]', async () => {
    const wrapper = await mountBar(router)
    expect(wrapper.findAll('[data-tab]')).toHaveLength(5)
  })

  it('gives every tab a visible text label, never icon-only [Skill: nav-label-icon]', async () => {
    const wrapper = await mountBar(router)
    for (const tab of wrapper.findAll('[data-tab]')) {
      expect(tab.text().trim().length).toBeGreaterThan(0)
    }
  })

  it('renders the four primary destinations in order with their Spanish labels', async () => {
    const wrapper = await mountBar(router)
    const keys = wrapper.findAll('[data-tab]').map((t) => t.attributes('data-tab'))
    expect(keys).toEqual(['home', 'courses', 'services', 'products', 'account'])

    expect(wrapper.find('[data-tab="home"]').text()).toContain('Inicio')
    expect(wrapper.find('[data-tab="courses"]').text()).toContain('Cursos')
    expect(wrapper.find('[data-tab="services"]').text()).toContain('Servicios')
    expect(wrapper.find('[data-tab="products"]').text()).toContain('Productos')
  })

  it('links the Cursos tab to the /cursos route (owned by the courses feature)', async () => {
    const wrapper = await mountBar(router)
    expect(wrapper.find('[data-tab="courses"]').attributes('href')).toBe('/cursos')
  })

  it('still renders — instead of blanking the whole app — if the courses routes are not registered yet', async () => {
    // router.resolve({ name }) THROWS for an unregistered name, and this bar
    // renders on every screen, so an unguarded { name: 'courses' } would take
    // the entire app down rather than just this tab.
    const bare = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', name: 'home', component: stub },
        { path: '/login', name: 'login', component: stub },
        { path: '/services', name: 'services', component: stub },
        { path: '/products', name: 'products', component: stub },
        { path: '/cart', name: 'cart', component: stub },
        { path: '/profile', name: 'profile', component: stub },
      ],
    })
    await bare.push('/')

    const wrapper = await mountBar(bare)

    expect(wrapper.findAll('[data-tab]')).toHaveLength(5)
    expect(wrapper.find('[data-tab="courses"]').text()).toContain('Cursos')
    expect(wrapper.find('[data-tab="courses"]').attributes('href')).toBe('/cursos')
  })

  it('uses Material Symbols ligature glyphs, never emoji [Skill: no-emoji-icons]', async () => {
    const wrapper = await mountBar(router)
    const icons = wrapper.findAll('.material-symbols-outlined')
    expect(icons.length).toBeGreaterThan(0)
    for (const icon of icons) {
      expect(icon.attributes('aria-hidden')).toBe('true')
      // Ligature names are ASCII identifiers; an emoji would be non-ASCII.
      expect(icon.text()).toMatch(/^[a-z_]+$/)
    }
  })
})

describe('BottomTabBar — active state [Skill: nav-state-active]', () => {
  let router

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    router = makeRouter()
  })

  it('marks the current tab with aria-current="page" and leaves the others unset', async () => {
    await router.push('/services')
    const wrapper = await mountBar(router)

    expect(wrapper.find('[data-tab="services"]').attributes('aria-current')).toBe('page')
    expect(wrapper.find('[data-tab="home"]').attributes('aria-current')).toBeUndefined()
    expect(wrapper.find('[data-tab="products"]').attributes('aria-current')).toBeUndefined()
  })

  it('keeps the parent tab active on a nested detail route (deep page)', async () => {
    await router.push('/products/paleta-editorial')
    const wrapper = await mountBar(router)

    expect(wrapper.find('[data-tab="products"]').attributes('aria-current')).toBe('page')
    expect(wrapper.find('[data-tab="home"]').attributes('aria-current')).toBeUndefined()
  })

  it('does not mark Inicio active just because every path starts with "/"', async () => {
    await router.push('/products')
    const wrapper = await mountBar(router)
    expect(wrapper.find('[data-tab="home"]').attributes('aria-current')).toBeUndefined()
  })

  it('signals the active tab with more than colour — an indicator element [Skill: color-not-only]', async () => {
    await router.push('/services')
    const wrapper = await mountBar(router)

    expect(wrapper.find('[data-tab="services"] [data-tab-indicator]').exists()).toBe(true)
    expect(wrapper.find('[data-tab="home"] [data-tab-indicator]').exists()).toBe(false)
  })
})

describe('BottomTabBar — auth-aware account tab', () => {
  let router

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    router = makeRouter()
    await router.push('/')
  })

  it('unauthenticated: offers "Entrar" pointing at /login (login discoverability)', async () => {
    const wrapper = await mountBar(router)
    const account = wrapper.find('[data-tab="account"]')

    expect(account.text()).toContain('Entrar')
    expect(account.attributes('href')).toBe('/login')
    expect(wrapper.find('[data-tab-avatar]').exists()).toBe(false)
    expect(wrapper.find('[data-tab-initials]').exists()).toBe(false)
  })

  it('authenticated with an avatar: renders the image with referrerpolicy="no-referrer"', async () => {
    const auth = useAuthStore()
    auth.token = 'tok'
    auth.user = { id: 1, name: 'Ada Lovelace', avatar: 'https://lh3.googleusercontent.com/a/x' }

    const wrapper = await mountBar(router)
    const account = wrapper.find('[data-tab="account"]')
    const avatar = wrapper.find('[data-tab-avatar]')

    expect(account.text()).toContain('Perfil')
    expect(account.attributes('href')).toBe('/profile')
    expect(avatar.exists()).toBe(true)
    expect(avatar.attributes('src')).toBe('https://lh3.googleusercontent.com/a/x')
    // Google avatar URLs 403 without this — see frontend/src/components/NavBar.vue.
    expect(avatar.attributes('referrerpolicy')).toBe('no-referrer')
  })

  it('authenticated without an avatar: falls back to an initials badge', async () => {
    const auth = useAuthStore()
    auth.token = 'tok'
    auth.user = { id: 1, name: 'Ada Lovelace', avatar: null }

    const wrapper = await mountBar(router)

    expect(wrapper.find('[data-tab-avatar]').exists()).toBe(false)
    expect(wrapper.find('[data-tab-initials]').text()).toBe('AL')
    expect(wrapper.find('[data-tab="account"]').text()).toContain('Perfil')
  })

  it('never exposes logout in the tab bar [Skill: destructive-nav-separation]', async () => {
    const auth = useAuthStore()
    auth.token = 'tok'
    auth.user = { id: 1, name: 'Ada Lovelace', avatar: null }

    const wrapper = await mountBar(router)

    expect(wrapper.text().toLowerCase()).not.toContain('salir')
    expect(wrapper.text().toLowerCase()).not.toContain('cerrar sesión')
    expect(wrapper.findAll('button')).toHaveLength(0)
  })
})
