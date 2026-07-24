import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

import api from '../services/api.js'
import { useProductsStore } from '../stores/products.js'

// Trimmed store: only fetchProducts(), needed by FeaturedProducts.vue for
// Home's "3 most-recent products" section. fetchProduct (detail) lands in
// Phase 7 alongside ProductDetail.vue (see stores/products.js).
describe('products store (trimmed: fetchProducts only, see Home.vue)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchProducts GETs /products with the given filters as query params and populates products/productMeta', async () => {
    const fakeProducts = [{ id: 1, title: 'Paleta Editorial' }]
    api.get.mockResolvedValueOnce({ data: { data: fakeProducts, meta: { total: 1 } } })

    const store = useProductsStore()
    await store.fetchProducts({ page: 1, per_page: 3, sort: 'newest' })

    expect(api.get).toHaveBeenCalledWith('/products', { params: { page: 1, per_page: 3, sort: 'newest' } })
    expect(store.products).toEqual(fakeProducts)
    expect(store.productMeta).toEqual({ total: 1 })
  })

  it('fetchProducts strips empty/null/undefined filter values from the query params', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = useProductsStore()
    await store.fetchProducts({ page: 1, stock_state: '', category: undefined })

    expect(api.get).toHaveBeenCalledWith('/products', { params: { page: 1 } })
  })

  it('sets a Spanish error message and leaves products untouched when the request fails', async () => {
    api.get.mockRejectedValueOnce(new Error('Network Error'))

    const store = useProductsStore()
    await store.fetchProducts({})

    expect(store.error).toBe('Error al cargar los productos')
    expect(store.products).toEqual([])
  })
})

// Phase 7: fetchProduct (detail) + fetchCategories, needed by ProductDetail.vue
// and Products.vue's category filter. Admin CRUD/upload actions are
// intentionally NOT ported -- no admin surface in this app (see
// products.js's file-level comment and the spec's Mobile App Boundaries).
describe('products store — fetchProduct + fetchCategories (Phase 7)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchProduct GETs /products/:slug and populates currentProduct', async () => {
    const fakeProduct = { id: 1, slug: 'paleta-editorial', title: 'Paleta Editorial' }
    api.get.mockResolvedValueOnce({ data: { data: fakeProduct } })

    const store = useProductsStore()
    const result = await store.fetchProduct('paleta-editorial')

    expect(api.get).toHaveBeenCalledWith('/products/paleta-editorial')
    expect(store.currentProduct).toEqual(fakeProduct)
    expect(result).toEqual(fakeProduct)
  })

  it('fetchProduct sets a Spanish error message and re-throws on failure', async () => {
    const notFound = new Error('Not Found')
    notFound.response = { status: 404 }
    api.get.mockRejectedValueOnce(notFound)

    const store = useProductsStore()
    await expect(store.fetchProduct('missing')).rejects.toThrow('Not Found')

    expect(store.error).toBe('Error al cargar el producto')
  })

  it('fetchCategories GETs /categories and populates categories once, skipping subsequent calls', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [{ id: 1, name: 'Labios', slug: 'labios' }] } })

    const store = useProductsStore()
    await store.fetchCategories()
    await store.fetchCategories()

    expect(api.get).toHaveBeenCalledTimes(1)
    expect(store.categories).toEqual([{ id: 1, name: 'Labios', slug: 'labios' }])
  })
})
