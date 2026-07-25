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

// ---------------------------------------------------------------------------
// Mock api.js and @capacitor/browser for the pay() action (tasks 8.3-8.5):
// pay() POSTs to /checkout/handoff and opens the returned URL via
// @capacitor/browser -- never the app's own router/WebView. Mirrors the
// api.js mocking convention already used by booking.store.test.js.
// ---------------------------------------------------------------------------
vi.mock('../services/api.js', () => ({
  default: {
    post: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

vi.mock('@capacitor/browser', () => ({
  Browser: { open: vi.fn().mockResolvedValue(undefined) },
}))

import { set, remove, getCached } from '../services/storage.js'
import api from '../services/api.js'
import { Browser } from '@capacitor/browser'
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

  // ── Persistence failure handling ────────────────────────────────────────
  // storage.js's set()/remove() are backed by the native Preferences bridge,
  // which can reject (e.g. platform storage error). Unlike a fetch failure
  // in booking.js/products.js, a rejected persist here is triggered from
  // fire-and-forget call sites (CartItemRow.vue does not await/.catch these
  // actions), so a rejection that escapes _persist() would surface as an
  // unhandled promise rejection instead of a caught, recorded error.

  it('addItem catches a rejected storage.js set() call and records it on persistError instead of throwing', async () => {
    const store = useCartStore()
    set.mockRejectedValueOnce(new Error('Preferences native bridge failure'))

    await expect(store.addItem(product())).resolves.toBeUndefined()

    expect(store.items).toHaveLength(1) // in-memory state still updated
    expect(store.persistError).toBe('No se pudo guardar el carrito. Tus cambios podrían perderse.')
  })

  it('removeItem catches a rejected storage.js remove() call and records it on persistError instead of throwing', async () => {
    const store = useCartStore()
    await store.addItem(product())
    remove.mockRejectedValueOnce(new Error('Preferences native bridge failure'))

    await expect(store.removeItem(1)).resolves.toBeUndefined()

    expect(store.items).toHaveLength(0) // in-memory state still updated
    expect(store.persistError).toBe('No se pudo guardar el carrito. Tus cambios podrían perderse.')
  })

  it('updateQuantity catches a rejected storage.js set() call and records it on persistError instead of throwing', async () => {
    const store = useCartStore()
    await store.addItem(product({ stock_qty: 5 }))
    set.mockRejectedValueOnce(new Error('Preferences native bridge failure'))

    await expect(store.updateQuantity(1, 2)).resolves.toBeUndefined()

    expect(store.items[0].quantity).toBe(2) // in-memory state still updated
    expect(store.persistError).toBe('No se pudo guardar el carrito. Tus cambios podrían perderse.')
  })

  it('clears persistError on the next successful persist', async () => {
    const store = useCartStore()
    set.mockRejectedValueOnce(new Error('Preferences native bridge failure'))
    await store.addItem(product())
    expect(store.persistError).not.toBeNull()

    await store.addItem(product({ id: 2, slug: 'otro' }))

    expect(store.persistError).toBeNull()
  })

  // ── pay() — checkout-handoff action (tasks 8.3-8.5) ─────────────────────
  // The app never renders payment UI inside its own WebView (spec's Mobile
  // App Boundaries). pay() instead POSTs a snapshot to
  // POST /api/checkout/handoff and opens the returned URL via
  // @capacitor/browser's Browser.open() -- never the app's own router.

  describe('pay', () => {
    it('blocks the handoff call and sets a "cart is empty" error when the cart has no items [Spec: cart empty at checkout tap]', async () => {
      const store = useCartStore()
      expect(store.isEmpty).toBe(true)

      await store.pay()

      expect(api.post).not.toHaveBeenCalled()
      expect(Browser.open).not.toHaveBeenCalled()
      expect(store.payError).toBe('El carrito está vacío.')
    })

    it('POSTs /checkout/handoff with a product_cart snapshot and opens the returned URL via @capacitor/browser, never the app WebView [Spec: handoff opens browser pre-loaded]', async () => {
      const store = useCartStore()
      await store.addItem(product({ id: 1, quantity: 1 }))
      await store.addItem(product({ id: 2, slug: 'otro' }))
      await store.updateQuantity(2, 3)

      api.post.mockResolvedValueOnce({
        data: {
          data: {
            url: 'https://app.ikena.com/checkout/resume#token=abc123',
            expires_at: '2026-07-25T15:10:00Z',
          },
        },
      })

      await store.pay()

      expect(api.post).toHaveBeenCalledWith('/checkout/handoff', {
        type: 'product_cart',
        items: [
          { product_id: 1, quantity: 1 },
          { product_id: 2, quantity: 3 },
        ],
      })
      expect(Browser.open).toHaveBeenCalledWith({
        url: 'https://app.ikena.com/checkout/resume#token=abc123',
      })
      expect(store.payError).toBeNull()
    })

    it('sets payError and never opens the browser when the handoff call fails', async () => {
      const store = useCartStore()
      await store.addItem(product())

      const error = new Error('Request failed')
      error.response = { data: { message: 'Uno o más productos no están disponibles.' } }
      api.post.mockRejectedValueOnce(error)

      await store.pay()

      expect(Browser.open).not.toHaveBeenCalled()
      expect(store.payError).toBe('Uno o más productos no están disponibles.')
    })

    it('falls back to a generic Spanish error message when the handoff failure has no response body', async () => {
      const store = useCartStore()
      await store.addItem(product())
      api.post.mockRejectedValueOnce(new Error('Network Error'))

      await store.pay()

      expect(Browser.open).not.toHaveBeenCalled()
      expect(store.payError).toBe('No se pudo iniciar el pago. Inténtalo de nuevo.')
    })

    it('sets payError and never opens the browser when the handoff response is missing a valid url', async () => {
      const store = useCartStore()
      await store.addItem(product())

      api.post.mockResolvedValueOnce({
        data: { data: { expires_at: '2026-07-25T15:10:00Z' } },
      })

      await store.pay()

      expect(Browser.open).not.toHaveBeenCalled()
      expect(store.payError).toBe('No se pudo iniciar el pago. Inténtalo de nuevo.')
    })

    it('clears a previous payError on the next pay() attempt before re-checking the cart', async () => {
      const store = useCartStore()
      await store.addItem(product())
      api.post.mockRejectedValueOnce(new Error('Network Error'))
      await store.pay()
      expect(store.payError).not.toBeNull()

      api.post.mockResolvedValueOnce({
        data: { data: { url: 'https://app.ikena.com/checkout/resume#token=xyz', expires_at: '' } },
      })
      await store.pay()

      expect(store.payError).toBeNull()
    })
  })
})
