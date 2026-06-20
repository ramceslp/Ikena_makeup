import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
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

// Mock PayPhone asset injection — no real DOM/network in tests
vi.mock('../composables/usePayPhone.js', () => ({
  usePayPhone: vi.fn(() => ({
    init: vi.fn().mockResolvedValue(undefined),
    render: vi.fn(),
  })),
}))

import api from '../services/api.js'
import Cart from '../views/Cart.vue'
import { useCartStore } from '../stores/cart.js'
import { useAuthStore } from '../stores/auth.js'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/cart', name: 'Cart', component: Cart },
    { path: '/login', name: 'Login', component: { template: '<div/>' } },
    { path: '/products', name: 'Products', component: { template: '<div/>' } },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

const sampleItem = {
  product_id: 1,
  slug: 'master-palette',
  title: 'Master Palette',
  price: '120.00',
  thumbnail: null,
  stock_qty: 10,
  quantity: 2,
}

// ---------------------------------------------------------------------------
// Empty state
// ---------------------------------------------------------------------------

describe('Cart.vue — empty state', () => {
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

  it('shows empty cart message when cart has no items', () => {
    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })
    expect(wrapper.find('[data-empty-cart]').exists()).toBe(true)
  })

  it('shows a link to the products catalog when cart is empty', () => {
    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })
    const link = wrapper.find('[data-browse-link]')
    expect(link.exists()).toBe(true)
  })

  it('does not render cart items list when cart is empty', () => {
    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })
    expect(wrapper.find('[data-cart-items]').exists()).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// Cart with items
// ---------------------------------------------------------------------------

describe('Cart.vue — with items', () => {
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

  it('renders the items list when cart has items', () => {
    const store = useCartStore()
    store.items = [{ ...sampleItem }]

    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })
    expect(wrapper.find('[data-cart-items]').exists()).toBe(true)
  })

  it('renders a CartItemRow for each item', () => {
    const store = useCartStore()
    store.items = [
      { ...sampleItem },
      { ...sampleItem, product_id: 2, title: 'Brush Set', slug: 'brush-set' },
    ]

    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })
    const rows = wrapper.findAll('[data-cart-row]')
    expect(rows).toHaveLength(2)
  })

  it('renders product titles for items in the cart', () => {
    const store = useCartStore()
    store.items = [{ ...sampleItem }]

    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })
    expect(wrapper.text()).toContain('Master Palette')
  })

  it('does not show the empty cart state when cart has items', () => {
    const store = useCartStore()
    store.items = [{ ...sampleItem }]

    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })
    expect(wrapper.find('[data-empty-cart]').exists()).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// Checkout flow
// ---------------------------------------------------------------------------

describe('Cart.vue — checkout flow', () => {
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

  it('redirects to login when checkout returns 401 (unauthenticated)', async () => {
    const store = useCartStore()
    store.items = [{ ...sampleItem }]

    api.post.mockRejectedValueOnce({ response: { status: 401, data: {} } })

    await router.push('/cart')
    await router.isReady()

    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })

    // Trigger checkout via CartSummary button
    const checkoutBtn = wrapper.find('[data-checkout-btn]')
    await checkoutBtn.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('Login')
  })

  it('shows 409 out-of-stock error message after checkout returns 409', async () => {
    const store = useCartStore()
    store.items = [{ ...sampleItem }]

    api.post.mockRejectedValueOnce({
      response: {
        status: 409,
        data: { message: 'Insufficient stock for one or more items.', product_id: 1 },
      },
    })

    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })

    const checkoutBtn = wrapper.find('[data-checkout-btn]')
    await checkoutBtn.trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-checkout-error]').exists()).toBe(true)
    expect(wrapper.text()).toMatch(/stock|disponible|agotado/i)
  })

  it('shows loading state during checkout', async () => {
    const store = useCartStore()
    store.items = [{ ...sampleItem }]

    let resolvePost
    api.post.mockReturnValueOnce(
      new Promise((resolve) => {
        resolvePost = resolve
      }),
    )

    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })

    const checkoutBtn = wrapper.find('[data-checkout-btn]')
    await checkoutBtn.trigger('click')

    // While pending, loading state should be visible
    expect(wrapper.find('[data-checkout-loading]').exists()).toBe(true)

    // Cleanup
    resolvePost({ data: { data: { order_id: 1, provider: 'payphone', config: {} } } })
    await flushPromises()
  })
})

// ---------------------------------------------------------------------------
// NavBar cart badge (inline — NavBar itself tested separately)
// ---------------------------------------------------------------------------

describe('Cart.vue — page title', () => {
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

  it('renders a heading with "Carrito" text', () => {
    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })
    expect(wrapper.text()).toMatch(/carrito/i)
  })
})
