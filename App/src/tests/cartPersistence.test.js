import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// ---------------------------------------------------------------------------
// Integration test for task 8.2 [Spec: cart persists across sessions]. Per
// session401.test.js's "Integration" layer convention (PR6): mocks only the
// lowest-level native bridge (@capacitor/preferences), then exercises the
// REAL storage.js + REAL cart.js store together, proving an actual persisted
// cart survives a simulated "app restart" (a fresh Pinia + fresh store
// instance created only after hydrate() has re-warmed the cache from
// Preferences) — not just a mocked storage.js module reporting that it
// "would" persist.
// ---------------------------------------------------------------------------
vi.mock('@capacitor/preferences', () => {
  let store = {}
  return {
    Preferences: {
      get: vi.fn(({ key }) => Promise.resolve({ value: store[key] ?? null })),
      set: vi.fn(({ key, value }) => {
        store[key] = value
        return Promise.resolve()
      }),
      remove: vi.fn(({ key }) => {
        delete store[key]
        return Promise.resolve()
      }),
      __reset: () => {
        store = {}
      },
    },
  }
})

import { Preferences } from '@capacitor/preferences'
import { hydrate, CART_KEY } from '../services/storage.js'
import { useCartStore } from '../stores/cart.js'

const product = {
  id: 1,
  slug: 'paleta-editorial',
  title: 'Paleta Editorial',
  price: '19.99',
  thumbnail: 'thumb.jpg',
  stock_qty: 5,
}

describe('cart persistence across app restarts (integration) [Spec: cart persists across sessions]', () => {
  beforeEach(async () => {
    Preferences.__reset()
    vi.clearAllMocks()
    setActivePinia(createPinia())
    // Simulate main.js's bootstrap(): hydrate the cache before any store is
    // created, same as a real cold app boot with an empty Preferences store.
    await hydrate()
  })

  it('cart contents survive a simulated app restart', async () => {
    // Arrange: a real, live cart — persisted through the real storage.js
    // module (backed by the fake Preferences plugin), not a mock of cart.js.
    const store = useCartStore()
    await store.addItem(product)
    await store.updateQuantity(1, 3)

    expect(Preferences.set).toHaveBeenCalledWith({
      key: CART_KEY,
      value: expect.any(String),
    })

    // Act: simulate the app being closed and reopened — a brand-new Pinia
    // instance, and crucially, hydrate() re-runs against the SAME (fake)
    // native Preferences store BEFORE the new cart store is created, exactly
    // as main.js's bootstrap() does on a real cold start.
    setActivePinia(createPinia())
    await hydrate()
    const restartedStore = useCartStore()

    // Assert: the cart contents remain intact.
    expect(restartedStore.items).toHaveLength(1)
    expect(restartedStore.items[0]).toMatchObject({
      product_id: 1,
      title: 'Paleta Editorial',
      quantity: 3,
    })
    expect(restartedStore.count).toBe(3)
  })

  it('an empty cart stays empty after a simulated app restart (no phantom items)', async () => {
    const store = useCartStore()
    await store.addItem(product)
    await store.clear()

    setActivePinia(createPinia())
    await hydrate()
    const restartedStore = useCartStore()

    expect(restartedStore.items).toEqual([])
    expect(restartedStore.isEmpty).toBe(true)
  })
})
