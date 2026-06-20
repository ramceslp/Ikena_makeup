import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
    patch: vi.fn(),
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
}))

import NavBar from '../components/NavBar.vue'
import { useCartStore } from '../stores/cart.js'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', component: { template: '<div/>' } },
    { path: '/cart', name: 'Cart', component: { template: '<div/>' } },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

describe('NavBar — cart badge', () => {
  let pinia

  beforeEach(() => {
    localStorage.clear()
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('does not show cart badge when cart is empty', () => {
    const wrapper = mount(NavBar, {
      global: { plugins: [pinia, router] },
    })
    expect(wrapper.find('[data-cart-badge]').exists()).toBe(false)
  })

  it('shows cart badge with correct count when cart has items', () => {
    const store = useCartStore()
    store.items = [
      { product_id: 1, title: 'A', price: '10.00', stock_qty: 5, quantity: 2, slug: 'a', thumbnail: null },
      { product_id: 2, title: 'B', price: '20.00', stock_qty: 3, quantity: 1, slug: 'b', thumbnail: null },
    ]

    const wrapper = mount(NavBar, {
      global: { plugins: [pinia, router] },
    })
    const badge = wrapper.find('[data-cart-badge]')
    expect(badge.exists()).toBe(true)
    expect(badge.text()).toBe('3') // 2 + 1
  })

  it('cart icon links to /cart route', () => {
    const store = useCartStore()
    store.items = [
      { product_id: 1, title: 'A', price: '10.00', stock_qty: 5, quantity: 1, slug: 'a', thumbnail: null },
    ]

    const wrapper = mount(NavBar, {
      global: { plugins: [pinia, router] },
    })
    // The cart link should point to /cart
    const cartLink = wrapper.find('[data-cart-link]')
    expect(cartLink.exists()).toBe(true)
    expect(wrapper.html()).toContain('/cart')
  })
})
