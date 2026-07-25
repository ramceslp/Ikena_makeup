import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// ---------------------------------------------------------------------------
// Mock storage.js BEFORE importing the store, so the store's actions drive a
// controllable fake instead of the real Preferences bridge. Mirrors
// auth.store.test.js's pattern (mock the async persistence layer, unit-test
// the store's own add/remove/update/clear/getter logic in isolation). A
// separate integration test (cartPersistence.test.js) exercises the REAL
// storage.js + hydrate() round trip for the actual "restart" scenario.
// ---------------------------------------------------------------------------
vi.mock('../services/storage.js', () => ({
  set: vi.fn().mockResolvedValue(undefined),
  remove: vi.fn().mockResolvedValue(undefined),
  getCached: vi.fn(),
  CART_KEY: 'ikena_cart',
}))

import { set, remove, getCached } from '../services/storage.js'
import { useCartStore } from '../stores/cart.js'

const product = (overrides = {}) => ({
  id: 1,
  slug: 'paleta-editorial',
  title: 'Paleta Editorial',
  price: '19.99',
  thumbnail: 'thumb.jpg',
  stock_qty: 5,
  ...overrides,
})

describe('cart store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    getCached.mockReturnValue(null)
  })

  it('initializes items from the already-hydrated storage cache (see main.js bootstrap)', () => {
    getCached.mockReturnValue([
      { product_id: 1, slug: 'x', title: 'X', price: 9.99, thumbnail: null, stock_qty: 5, quantity: 2 },
    ])

    const store = useCartStore()

    expect(store.items).toHaveLength(1)
    expect(store.count).toBe(2)
  })

  it('drops malformed cached entries instead of producing a broken cart', () => {
    getCached.mockReturnValue([
      { product_id: 1, price: 'not-a-number', quantity: 1 },
      { product_id: 2, price: 9.99, quantity: 0 },
      null,
      { product_id: 3, price: 5, quantity: 2 },
    ])

    const store = useCartStore()

    expect(store.items).toEqual([{ product_id: 3, price: 5, quantity: 2 }])
  })

  it('isEmpty is true for a fresh cart with no cached items', () => {
    const store = useCartStore()
    expect(store.isEmpty).toBe(true)
    expect(store.count).toBe(0)
    expect(store.subtotal).toBe(0)
  })

  it('addItem adds a new product with quantity 1 and persists via storage.js', async () => {
    const store = useCartStore()

    await store.addItem(product())

    expect(store.items).toHaveLength(1)
    expect(store.items[0]).toMatchObject({
      product_id: 1,
      slug: 'paleta-editorial',
      title: 'Paleta Editorial',
      price: '19.99',
      thumbnail: 'thumb.jpg',
      stock_qty: 5,
      quantity: 1,
    })
    expect(set).toHaveBeenCalledWith('ikena_cart', store.items)
  })

  it('addItem increments quantity (clamped to stock_qty) when the product is already in the cart', async () => {
    const store = useCartStore()

    await store.addItem(product({ stock_qty: 2 }))
    await store.addItem(product({ stock_qty: 2 }))
    await store.addItem(product({ stock_qty: 2 }))

    expect(store.items).toHaveLength(1)
    expect(store.items[0].quantity).toBe(2)
  })

  it('addItem is a no-op when the product is out of stock', async () => {
    const store = useCartStore()

    await store.addItem(product({ stock_qty: 0 }))

    expect(store.items).toHaveLength(0)
    expect(set).not.toHaveBeenCalled()
  })

  it('removeItem removes the matching product and persists', async () => {
    const store = useCartStore()
    await store.addItem(product({ id: 1 }))
    await store.addItem(product({ id: 2, slug: 'otro' }))
    vi.clearAllMocks()

    await store.removeItem(1)

    expect(store.items).toHaveLength(1)
    expect(store.items[0].product_id).toBe(2)
    expect(set).toHaveBeenCalledWith('ikena_cart', store.items)
  })

  it('removeItem clears the persisted key entirely once the cart becomes empty', async () => {
    const store = useCartStore()
    await store.addItem(product())
    vi.clearAllMocks()

    await store.removeItem(1)

    expect(store.items).toHaveLength(0)
    expect(remove).toHaveBeenCalledWith('ikena_cart')
    expect(set).not.toHaveBeenCalled()
  })

  it('updateQuantity clamps between 1 and stock_qty and persists', async () => {
    const store = useCartStore()
    await store.addItem(product({ stock_qty: 3 }))
    vi.clearAllMocks()

    await store.updateQuantity(1, 10)
    expect(store.items[0].quantity).toBe(3)

    await store.updateQuantity(1, -5)
    expect(store.items[0].quantity).toBe(1)
    expect(set).toHaveBeenCalledWith('ikena_cart', store.items)
  })

  it('clear empties the cart and removes the persisted key', async () => {
    const store = useCartStore()
    await store.addItem(product())
    vi.clearAllMocks()

    await store.clear()

    expect(store.items).toEqual([])
    expect(remove).toHaveBeenCalledWith('ikena_cart')
  })

  it('subtotal sums price * quantity across all items', async () => {
    const store = useCartStore()
    await store.addItem(product({ id: 1, price: '10.00', stock_qty: 5 }))
    await store.addItem(product({ id: 2, price: '5.50', stock_qty: 5 }))
    await store.updateQuantity(1, 2)

    expect(store.subtotal).toBeCloseTo(25.5)
  })
})
