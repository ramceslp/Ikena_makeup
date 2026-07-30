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

import { useCartStore } from '../stores/cart.js'
import TopAppBar from '../components/layout/TopAppBar.vue'

const stub = { template: '<div/>' }

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: stub },
      { path: '/cart', name: 'cart', component: stub },
    ],
  })
}

function item(overrides = {}) {
  return {
    product_id: 1,
    slug: 'paleta-editorial',
    title: 'Paleta Editorial',
    price: '19.99',
    thumbnail: null,
    stock_qty: 50,
    quantity: 1,
    ...overrides,
  }
}

describe('TopAppBar — brand + cart', () => {
  let router

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    router = makeRouter()
    await router.push('/')
  })

  async function mountBar() {
    const wrapper = mount(TopAppBar, { global: { plugins: [router] } })
    await router.isReady()
    return wrapper
  }

  it('links the "Ikena" wordmark to home', async () => {
    const wrapper = await mountBar()
    const brand = wrapper.find('[data-brand]')
    expect(brand.text()).toBe('Ikena')
    expect(brand.attributes('href')).toBe('/')
  })

  it('links the cart icon to /cart with an accessible label', async () => {
    const wrapper = await mountBar()
    const cart = wrapper.find('[data-cart-link]')
    expect(cart.attributes('href')).toBe('/cart')
    expect(cart.attributes('aria-label')).toBeTruthy()
  })

  it('hides the count badge when the cart is empty', async () => {
    const wrapper = await mountBar()
    expect(wrapper.find('[data-cart-badge]').exists()).toBe(false)
  })

  it('shows the count badge only when count > 0', async () => {
    const cart = useCartStore()
    cart.items = [item({ quantity: 3 })]

    const wrapper = await mountBar()
    expect(wrapper.find('[data-cart-badge]').text()).toBe('3')
  })

  it('caps the badge at 99+ so a large count cannot blow out the layout', async () => {
    const cart = useCartStore()
    cart.items = [item({ quantity: 150 })]

    const wrapper = await mountBar()
    expect(wrapper.find('[data-cart-badge]').text()).toBe('99+')
  })

  it('announces the count in the cart link label, not by badge colour alone', async () => {
    const cart = useCartStore()
    cart.items = [item({ quantity: 2 })]

    const wrapper = await mountBar()
    expect(wrapper.find('[data-cart-link]').attributes('aria-label')).toContain('2')
  })
})
