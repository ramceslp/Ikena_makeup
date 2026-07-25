import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

import api from '../services/api.js'
import { useProfileStore } from '../stores/profile.js'

// mobile-capacitor-setup Phase 8, tasks 8.9-8.10 [Spec: history loads / no
// history yet]. New store — GET /api/profile/orders is the existing endpoint
// frontend/src/stores/auth.js's fetchOrders() already calls; split into its
// own store here (not folded into auth.js) to match this codebase's
// established one-concern-per-store convention (products.js/services.js/
// booking.js/cart.js/push.js are all separate from auth.js).
describe('profile store — fetchOrders [Spec: history loads / no history yet]', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchOrders GETs /profile/orders and populates orders/ordersMeta', async () => {
    const fakeOrders = [
      { id: 2, type: 'product_cart', created_at: '2026-07-20T10:00:00Z' },
      { id: 1, type: 'appointment', created_at: '2026-07-01T10:00:00Z' },
    ]
    api.get.mockResolvedValueOnce({ data: { data: fakeOrders, meta: { current_page: 1, last_page: 1 } } })

    const store = useProfileStore()
    await store.fetchOrders()

    expect(api.get).toHaveBeenCalledWith('/profile/orders', { params: { page: 1 } })
    expect(store.orders).toEqual(fakeOrders)
    expect(store.ordersMeta).toEqual({ current_page: 1, last_page: 1 })
  })

  it('fetchOrders does NOT re-sort the API response — ordering is authoritative from the backend\'s ->latest() query', async () => {
    // Deliberately reverse-chronological (newest first) input, matching what
    // ProfileController::orders() already guarantees server-side. If this
    // store re-sorted client-side, a scrambled input here would still come
    // out sorted; asserting toEqual (not toContainEqual/unordered) proves the
    // store trusts the backend's order instead of silently re-deriving it.
    const fakeOrders = [
      { id: 5, created_at: '2026-07-24T00:00:00Z' },
      { id: 3, created_at: '2026-07-10T00:00:00Z' },
      { id: 4, created_at: '2026-07-15T00:00:00Z' },
    ]
    api.get.mockResolvedValueOnce({ data: { data: fakeOrders, meta: {} } })

    const store = useProfileStore()
    await store.fetchOrders()

    expect(store.orders).toEqual(fakeOrders)
  })

  it('fetchOrders defaults orders/ordersMeta to an empty state until a fetch resolves', () => {
    const store = useProfileStore()
    expect(store.orders).toEqual([])
    expect(store.ordersMeta).toBeNull()
  })

  it('fetchOrders sets loading true during the request and false after it resolves', async () => {
    let resolveGet
    api.get.mockReturnValueOnce(new Promise((resolve) => { resolveGet = resolve }))

    const store = useProfileStore()
    const promise = store.fetchOrders()

    expect(store.loading).toBe(true)
    resolveGet({ data: { data: [], meta: {} } })
    await promise

    expect(store.loading).toBe(false)
  })

  it('fetchOrders sets a Spanish error message and leaves orders untouched when the request fails', async () => {
    api.get.mockRejectedValueOnce(new Error('Network Error'))

    const store = useProfileStore()
    await store.fetchOrders()

    expect(store.error).toBe('Error al cargar tu historial')
    expect(store.orders).toEqual([])
    expect(store.loading).toBe(false)
  })

  // [Judgment Day fix, PR8d Round 1]: catch{} previously discarded the real
  // error entirely, always showing the same generic fallback string. Every
  // sibling store (products.js/services.js/booking.js) instead surfaces
  // err.response?.data?.message when the backend provides one.
  it('fetchOrders surfaces the backend\'s specific error message when present, instead of the generic fallback', async () => {
    api.get.mockRejectedValueOnce({ response: { data: { message: 'Sesión expirada' } } })

    const store = useProfileStore()
    await store.fetchOrders()

    expect(store.error).toBe('Sesión expirada')
  })

  it('fetchOrders clears a previous error on a subsequent successful call', async () => {
    api.get.mockRejectedValueOnce(new Error('Network Error'))
    const store = useProfileStore()
    await store.fetchOrders()
    expect(store.error).toBe('Error al cargar tu historial')

    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })
    await store.fetchOrders()

    expect(store.error).toBeNull()
  })

  it('fetchOrders forwards an explicit page number as a query param', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: { current_page: 2 } } })

    const store = useProfileStore()
    await store.fetchOrders(2)

    expect(api.get).toHaveBeenCalledWith('/profile/orders', { params: { page: 2 } })
  })
})
