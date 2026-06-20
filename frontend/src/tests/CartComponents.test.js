import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'

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

import CartItemRow from '../components/cart/CartItemRow.vue'
import CartSummary from '../components/cart/CartSummary.vue'
import { useCartStore } from '../stores/cart.js'

// ---------------------------------------------------------------------------
// CartItemRow
// ---------------------------------------------------------------------------

const cartItem = {
  product_id: 1,
  slug: 'master-palette',
  title: 'Master Palette',
  price: '120.00',
  thumbnail: 'https://example.com/thumb.jpg',
  stock_qty: 10,
  quantity: 2,
}

let sharedPinia

function mountCartItemRow(item = cartItem) {
  return mount(CartItemRow, {
    props: { item },
    global: { plugins: [sharedPinia] },
  })
}

describe('CartItemRow.vue — display', () => {
  beforeEach(() => {
    localStorage.clear()
    sharedPinia = createPinia()
    setActivePinia(sharedPinia)
    vi.clearAllMocks()
  })

  it('renders the product title', () => {
    const wrapper = mountCartItemRow()
    expect(wrapper.text()).toContain('Master Palette')
  })

  it('renders the unit price', () => {
    const wrapper = mountCartItemRow()
    expect(wrapper.text()).toContain('$120.00')
  })

  it('renders the current quantity', () => {
    const wrapper = mountCartItemRow()
    expect(wrapper.find('[data-qty-value]').text()).toBe('2')
  })

  it('renders the line total (price * quantity)', () => {
    const wrapper = mountCartItemRow()
    // 120.00 * 2 = 240.00
    expect(wrapper.text()).toContain('$240.00')
  })

  it('renders the product thumbnail image when thumbnail is present', () => {
    const wrapper = mountCartItemRow()
    const img = wrapper.find('img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://example.com/thumb.jpg')
  })

  it('renders SVG fallback when thumbnail is null', () => {
    const wrapper = mountCartItemRow({ ...cartItem, thumbnail: null })
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('svg').exists()).toBe(true)
  })
})

describe('CartItemRow.vue — quantity stepper', () => {
  let pinia

  beforeEach(() => {
    localStorage.clear()
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
  })

  it('renders increment and decrement buttons', () => {
    const wrapper = mount(CartItemRow, {
      props: { item: cartItem },
      global: { plugins: [pinia] },
    })
    expect(wrapper.find('[data-qty-dec]').exists()).toBe(true)
    expect(wrapper.find('[data-qty-inc]').exists()).toBe(true)
  })

  it('decrement button calls updateQuantity with qty - 1', async () => {
    const store = useCartStore()
    store.items = [{ ...cartItem }]

    const wrapper = mount(CartItemRow, {
      props: { item: store.items[0] },
      global: { plugins: [pinia] },
    })

    const updateSpy = vi.spyOn(store, 'updateQuantity')
    await wrapper.find('[data-qty-dec]').trigger('click')
    expect(updateSpy).toHaveBeenCalledWith(1, 1) // qty 2 - 1 = 1
  })

  it('increment button calls updateQuantity with qty + 1', async () => {
    const store = useCartStore()
    store.items = [{ ...cartItem }]

    const wrapper = mount(CartItemRow, {
      props: { item: store.items[0] },
      global: { plugins: [pinia] },
    })

    const updateSpy = vi.spyOn(store, 'updateQuantity')
    await wrapper.find('[data-qty-inc]').trigger('click')
    expect(updateSpy).toHaveBeenCalledWith(1, 3) // qty 2 + 1 = 3
  })

  it('increment button is disabled when quantity equals stock_qty', () => {
    const fullStockItem = { ...cartItem, quantity: 10, stock_qty: 10 }
    const wrapper = mount(CartItemRow, {
      props: { item: fullStockItem },
      global: { plugins: [pinia] },
    })
    const incBtn = wrapper.find('[data-qty-inc]')
    expect(incBtn.attributes('disabled')).toBeDefined()
  })

  it('decrement button is disabled when quantity equals 1', () => {
    const wrapper = mount(CartItemRow, {
      props: { item: { ...cartItem, quantity: 1 } },
      global: { plugins: [pinia] },
    })
    const decBtn = wrapper.find('[data-qty-dec]')
    expect(decBtn.attributes('disabled')).toBeDefined()
  })
})

describe('CartItemRow.vue — remove', () => {
  let pinia

  beforeEach(() => {
    localStorage.clear()
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
  })

  it('renders a remove button', () => {
    const wrapper = mount(CartItemRow, {
      props: { item: cartItem },
      global: { plugins: [pinia] },
    })
    expect(wrapper.find('[data-remove-btn]').exists()).toBe(true)
  })

  it('remove button calls removeItem with the product_id', async () => {
    const store = useCartStore()
    store.items = [{ ...cartItem }]

    const wrapper = mount(CartItemRow, {
      props: { item: store.items[0] },
      global: { plugins: [pinia] },
    })

    const removeSpy = vi.spyOn(store, 'removeItem')
    await wrapper.find('[data-remove-btn]').trigger('click')
    expect(removeSpy).toHaveBeenCalledWith(1)
  })
})

// ---------------------------------------------------------------------------
// CartSummary
// ---------------------------------------------------------------------------

describe('CartSummary.vue — display', () => {
  let pinia

  beforeEach(() => {
    localStorage.clear()
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
  })

  it('renders subtotal amount', () => {
    const store = useCartStore()
    store.items = [
      { ...cartItem, quantity: 2 }, // 120.00 * 2 = 240.00
    ]
    const wrapper = mount(CartSummary, {
      global: { plugins: [pinia] },
    })
    expect(wrapper.text()).toContain('240.00')
  })

  it('renders IVA label (15%)', () => {
    const store = useCartStore()
    store.items = [{ ...cartItem, quantity: 1 }]
    const wrapper = mount(CartSummary, {
      global: { plugins: [pinia] },
    })
    expect(wrapper.text()).toMatch(/IVA.*15%/i)
  })

  it('renders total (subtotal + IVA)', () => {
    const store = useCartStore()
    store.items = [{ ...cartItem, quantity: 1 }] // subtotal=120, tax=18, total=138
    const wrapper = mount(CartSummary, {
      global: { plugins: [pinia] },
    })
    // total = 120 + round(120*0.15) = 120 + 18 = 138
    expect(wrapper.text()).toContain('138.00')
  })

  it('renders item count', () => {
    const store = useCartStore()
    store.items = [
      { ...cartItem, quantity: 2 },
      { product_id: 2, title: 'Brush', price: '50.00', stock_qty: 5, quantity: 1, slug: 'brush', thumbnail: null },
    ]
    const wrapper = mount(CartSummary, {
      global: { plugins: [pinia] },
    })
    // count = 2 + 1 = 3
    expect(wrapper.text()).toMatch(/3/)
  })

  it('renders a checkout CTA button', () => {
    const store = useCartStore()
    store.items = [{ ...cartItem, quantity: 1 }]
    const wrapper = mount(CartSummary, {
      global: { plugins: [pinia] },
    })
    expect(wrapper.find('[data-checkout-btn]').exists()).toBe(true)
  })

  it('emits checkout event when CTA is clicked', async () => {
    const store = useCartStore()
    store.items = [{ ...cartItem, quantity: 1 }]
    const wrapper = mount(CartSummary, {
      global: { plugins: [pinia] },
    })
    await wrapper.find('[data-checkout-btn]').trigger('click')
    expect(wrapper.emitted('checkout')).toBeTruthy()
  })
})
