import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
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
// PayPhone test helpers
// ---------------------------------------------------------------------------

/**
 * Stub PayPhone so tests don't hang on waitForConstructor (which polls up to
 * 8 s). Pre-setting window.PPaymentButtonBox makes the first interval tick
 * resolve immediately.
 *
 * Also stubs injectPayPhoneAssets by pre-setting a matching script tag so the
 * "already present → resolve immediately" branch fires.
 */
function stubPayPhoneSuccess() {
  // PPaymentButtonBox must be a proper constructor (not an arrow function)
  // so `new window.PPaymentButtonBox(...)` works in jsdom.
  const renderMock = vi.fn()
  function FakePPaymentButtonBox() {
    this.render = renderMock
  }
  window.PPaymentButtonBox = FakePPaymentButtonBox

  // Make injectPayPhoneAssets resolve immediately (script already in DOM)
  const PAYPHONE_JS = 'https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.js'
  if (!document.querySelector(`script[src="${PAYPHONE_JS}"]`)) {
    const script = document.createElement('script')
    script.src = PAYPHONE_JS
    document.head.appendChild(script)
  }
}

function teardownPayPhone() {
  delete window.PPaymentButtonBox
  const PAYPHONE_JS = 'https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.js'
  const el = document.querySelector(`script[src="${PAYPHONE_JS}"]`)
  if (el) el.remove()
}

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
    teardownPayPhone()
  })

  afterEach(() => {
    localStorage.clear()
    teardownPayPhone()
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

  it('shows 409 error naming the specific product', async () => {
    const store = useCartStore()
    store.items = [{ ...sampleItem }]

    api.post.mockRejectedValueOnce({
      response: {
        status: 409,
        data: { message: 'Insufficient stock.', product_id: 1 },
      },
    })

    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })

    const checkoutBtn = wrapper.find('[data-checkout-btn]')
    await checkoutBtn.trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-checkout-error]').exists()).toBe(true)
    // Must name the product title (sampleItem.title = 'Master Palette')
    expect(wrapper.text()).toContain('Master Palette')
    expect(wrapper.text()).toMatch(/stock/i)
    // Cart must NOT be cleared
    expect(store.items).toHaveLength(1)
  })

  it('shows 422 error naming the specific product', async () => {
    const store = useCartStore()
    store.items = [{ ...sampleItem }]

    api.post.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { message: 'Product unpublished.', product_id: 1 },
      },
    })

    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })

    const checkoutBtn = wrapper.find('[data-checkout-btn]')
    await checkoutBtn.trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-checkout-error]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Master Palette')
    expect(wrapper.text()).toMatch(/disponible/i)
    // Cart must NOT be cleared
    expect(store.items).toHaveLength(1)
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

  it('SUCCESS: clears cart and shows widget after PayPhone renders', async () => {
    // Pre-set window.PPaymentButtonBox as a proper constructor function so
    // waitForConstructor resolves on the first interval tick.
    // The script tag stub makes injectPayPhoneAssets go to the "already loaded"
    // branch and resolve immediately, avoiding network I/O.
    stubPayPhoneSuccess()

    const store = useCartStore()
    store.items = [{ ...sampleItem }]

    api.post.mockResolvedValueOnce({
      data: { data: { order_id: 42, provider: 'payphone', config: { token: 'tok', amount: 13920 } } },
    })

    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })

    const checkoutBtn = wrapper.find('[data-checkout-btn]')
    await checkoutBtn.trigger('click')

    // Phase 1: let the api.post() promise microtask resolve
    await flushPromises()
    // Phase 2: injectPayPhoneAssets resolves via the "already present" branch
    // (synchronous resolve), waitForConstructor starts its setInterval (100ms).
    // We yield 110ms of real time so the first interval tick fires.
    await new Promise((r) => setTimeout(r, 110))
    // Phase 3: waitForConstructor resolves, then setTimeout(0) fires for render.
    // Flush remaining microtasks + the zero-delay timer callback.
    await new Promise((r) => setTimeout(r, 10))
    await flushPromises()

    // Flush Vue's reactivity: two ticks — one for cart.clear() (isEmpty → re-render)
    // and one for boxReady = true (switches the v-else-if to show the widget panel).
    await nextTick()
    await nextTick()

    // Cart must be cleared after successful widget render
    expect(store.items).toHaveLength(0)
    // PayPhone widget container must be visible (boxReady = true shows the widget section)
    expect(wrapper.find('#pp-cart-button').exists()).toBe(true)
  })

  it('FAILURE: cart remains intact when PayPhone asset injection fails', async () => {
    // Do NOT pre-set PPaymentButtonBox — make asset injection fail
    const PAYPHONE_JS = 'https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.js'
    // Inject a script tag with onerror so the "already present" branch fires
    // but window.PPaymentButtonBox stays undefined — we simulate failure by
    // injecting the script and then making it fire onerror synchronously
    // via a data: script that never sets the constructor.
    // Simpler approach: mock the DOM append to fire onerror immediately.
    const origAppendChild = document.head.appendChild.bind(document.head)
    vi.spyOn(document.head, 'appendChild').mockImplementation((el) => {
      if (el.tagName === 'SCRIPT' && el.src === PAYPHONE_JS) {
        // Trigger onerror asynchronously to simulate load failure
        Promise.resolve().then(() => el.onerror && el.onerror(new Error('load failed')))
        return el
      }
      return origAppendChild(el)
    })

    const store = useCartStore()
    store.items = [{ ...sampleItem }]

    api.post.mockResolvedValueOnce({
      data: { data: { order_id: 42, provider: 'payphone', config: { token: 'tok', amount: 13920 } } },
    })

    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })

    const checkoutBtn = wrapper.find('[data-checkout-btn]')
    await checkoutBtn.trigger('click')
    await flushPromises()

    // Cart must NOT be cleared — widget never rendered
    expect(store.items).toHaveLength(1)
    // Error must be surfaced
    expect(wrapper.find('[data-checkout-error]').exists()).toBe(true)

    vi.restoreAllMocks()
  })

  it('blocks double-submit: second click while loading is a no-op', async () => {
    const store = useCartStore()
    store.items = [{ ...sampleItem }]

    let resolvePost
    api.post.mockReturnValueOnce(
      new Promise((resolve) => { resolvePost = resolve }),
    )

    const wrapper = mount(Cart, {
      global: { plugins: [pinia, router] },
    })

    const checkoutBtn = wrapper.find('[data-checkout-btn]')
    await checkoutBtn.trigger('click')
    await checkoutBtn.trigger('click') // second click while in flight
    await checkoutBtn.trigger('click') // third click

    // Only ONE api.post call should have been made
    expect(api.post).toHaveBeenCalledTimes(1)

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
