import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
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

import api from '../services/api.js'
import { useCartStore } from '../stores/cart.js'

const STORAGE_KEY = 'ikena_cart'

const fakeProduct = {
  id: 1,
  slug: 'master-palette',
  title: 'Master Palette',
  price: '120.00',
  thumbnail: 'https://example.com/thumb.jpg',
  stock_qty: 10,
  stock_state: 'En Stock',
}

const anotherProduct = {
  id: 2,
  slug: 'pro-brush-set',
  title: 'Pro Brush Set',
  price: '50.00',
  thumbnail: null,
  stock_qty: 3,
  stock_state: 'Últimas unidades',
}

const outOfStockProduct = {
  id: 3,
  slug: 'sold-out',
  title: 'Sold Out Product',
  price: '30.00',
  thumbnail: null,
  stock_qty: 0,
  stock_state: 'Agotado',
}

// ---------------------------------------------------------------------------
// addItem
// ---------------------------------------------------------------------------

describe('cart store — addItem', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('adds a new item to an empty cart', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)
    expect(store.items).toHaveLength(1)
    expect(store.items[0].product_id).toBe(1)
    expect(store.items[0].quantity).toBe(1)
  })

  it('increments quantity when adding an existing item', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)
    store.addItem(fakeProduct)
    expect(store.items).toHaveLength(1)
    expect(store.items[0].quantity).toBe(2)
  })

  it('stores title, slug, price, thumbnail on the line item', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)
    const item = store.items[0]
    expect(item.title).toBe('Master Palette')
    expect(item.slug).toBe('master-palette')
    expect(item.price).toBe('120.00')
    expect(item.thumbnail).toBe('https://example.com/thumb.jpg')
  })

  it('stores stock_qty on the line item', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)
    expect(store.items[0].stock_qty).toBe(10)
  })

  it('does NOT add a product with stock_state Agotado', () => {
    const store = useCartStore()
    store.addItem(outOfStockProduct)
    expect(store.items).toHaveLength(0)
  })

  it('clamps quantity to stock_qty when incrementing would exceed stock', () => {
    const store = useCartStore()
    const lowStock = { ...fakeProduct, stock_qty: 2 }
    store.addItem(lowStock)
    store.addItem(lowStock)
    store.addItem(lowStock) // would be qty=3 but stock=2
    expect(store.items[0].quantity).toBe(2)
  })

  it('can add multiple distinct products', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)
    store.addItem(anotherProduct)
    expect(store.items).toHaveLength(2)
  })
})

// ---------------------------------------------------------------------------
// removeItem
// ---------------------------------------------------------------------------

describe('cart store — removeItem', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('removes the correct item from the cart', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)
    store.addItem(anotherProduct)
    store.removeItem(1)
    expect(store.items).toHaveLength(1)
    expect(store.items[0].product_id).toBe(2)
  })

  it('does nothing when removing a product not in cart', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)
    store.removeItem(999)
    expect(store.items).toHaveLength(1)
  })
})

// ---------------------------------------------------------------------------
// updateQuantity
// ---------------------------------------------------------------------------

describe('cart store — updateQuantity', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('updates the quantity of an existing item', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)
    store.updateQuantity(1, 5)
    expect(store.items[0].quantity).toBe(5)
  })

  it('clamps quantity to stock_qty maximum', () => {
    const store = useCartStore()
    store.addItem(fakeProduct) // stock_qty = 10
    store.updateQuantity(1, 99)
    expect(store.items[0].quantity).toBe(10)
  })

  it('clamps quantity to minimum of 1', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)
    store.updateQuantity(1, 0)
    expect(store.items[0].quantity).toBe(1)
  })

  it('clamps negative quantity to 1', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)
    store.updateQuantity(1, -5)
    expect(store.items[0].quantity).toBe(1)
  })

  it('does nothing for unknown product_id', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)
    store.updateQuantity(999, 3)
    expect(store.items[0].quantity).toBe(1) // unchanged
  })
})

// ---------------------------------------------------------------------------
// clear
// ---------------------------------------------------------------------------

describe('cart store — clear', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('removes all items from the cart', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)
    store.addItem(anotherProduct)
    store.clear()
    expect(store.items).toHaveLength(0)
  })

  it('clears localStorage after clear()', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)
    store.clear()
    const stored = localStorage.getItem(STORAGE_KEY)
    expect(stored).toBeNull()
  })
})

// ---------------------------------------------------------------------------
// Getters: count + subtotal
// ---------------------------------------------------------------------------

describe('cart store — getters', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('count is 0 when cart is empty', () => {
    const store = useCartStore()
    expect(store.count).toBe(0)
  })

  it('count is the sum of all quantities', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)   // qty=1
    store.addItem(anotherProduct) // qty=1
    store.updateQuantity(1, 3)   // now product 1 qty=3
    expect(store.count).toBe(4)
  })

  it('isEmpty returns true when cart has no items', () => {
    const store = useCartStore()
    expect(store.isEmpty).toBe(true)
  })

  it('isEmpty returns false after adding an item', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)
    expect(store.isEmpty).toBe(false)
  })

  it('subtotal is 0 when cart is empty', () => {
    const store = useCartStore()
    expect(store.subtotal).toBe(0)
  })

  it('subtotal sums line totals (price * quantity) correctly', () => {
    const store = useCartStore()
    store.addItem(fakeProduct)   // 120.00 * 1 = 120.00
    store.addItem(anotherProduct) // 50.00 * 1 = 50.00
    store.updateQuantity(1, 2)   // 120.00 * 2 = 240.00
    // 240.00 + 50.00 = 290.00
    expect(store.subtotal).toBeCloseTo(290.00, 2)
  })
})

// ---------------------------------------------------------------------------
// localStorage persistence
// ---------------------------------------------------------------------------

describe('cart store — localStorage persistence', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.clearAllMocks()
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('persists items to localStorage after addItem', () => {
    setActivePinia(createPinia())
    const store = useCartStore()
    store.addItem(fakeProduct)

    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY))
    expect(stored).toHaveLength(1)
    expect(stored[0].product_id).toBe(1)
  })

  it('hydrates items from localStorage on store init', () => {
    // Pre-populate localStorage
    const savedItems = [
      {
        product_id: 1,
        slug: 'master-palette',
        title: 'Master Palette',
        price: '120.00',
        thumbnail: null,
        stock_qty: 10,
        quantity: 3,
      },
    ]
    localStorage.setItem(STORAGE_KEY, JSON.stringify(savedItems))

    // Create a fresh pinia + store — simulates page reload
    setActivePinia(createPinia())
    const store = useCartStore()

    expect(store.items).toHaveLength(1)
    expect(store.items[0].quantity).toBe(3)
  })

  it('persists items after updateQuantity', () => {
    setActivePinia(createPinia())
    const store = useCartStore()
    store.addItem(fakeProduct)
    store.updateQuantity(1, 5)

    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY))
    expect(stored[0].quantity).toBe(5)
  })

  it('persists items after removeItem', () => {
    setActivePinia(createPinia())
    const store = useCartStore()
    store.addItem(fakeProduct)
    store.addItem(anotherProduct)
    store.removeItem(1)

    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY))
    expect(stored).toHaveLength(1)
    expect(stored[0].product_id).toBe(2)
  })
})

// ---------------------------------------------------------------------------
// checkout action
// ---------------------------------------------------------------------------

describe('cart store — checkout', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('POSTs items to /cart/checkout and returns response data', async () => {
    const store = useCartStore()
    store.addItem(fakeProduct)
    store.updateQuantity(1, 2)

    const fakeResponse = {
      data: {
        order_id: 42,
        provider: 'payphone',
        config: { token: 'abc', amount: 27732 },
      },
    }
    api.post.mockResolvedValueOnce({ data: fakeResponse })

    const result = await store.checkout()

    expect(api.post).toHaveBeenCalledWith('/cart/checkout', {
      items: [{ product_id: 1, quantity: 2 }],
    })
    expect(result).toEqual(fakeResponse)
  })

  it('clears the cart after a successful checkout', async () => {
    const store = useCartStore()
    store.addItem(fakeProduct)

    api.post.mockResolvedValueOnce({
      data: { data: { order_id: 1, provider: 'payphone', config: {} } },
    })

    await store.checkout()
    expect(store.items).toHaveLength(0)
  })

  it('does NOT clear the cart when checkout fails', async () => {
    const store = useCartStore()
    store.addItem(fakeProduct)

    api.post.mockRejectedValueOnce({ response: { status: 409, data: { message: 'Out of stock' } } })

    await expect(store.checkout()).rejects.toBeDefined()
    expect(store.items).toHaveLength(1) // cart preserved on failure
  })
})
