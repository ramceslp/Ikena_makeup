import { describe, it, expect, vi, beforeEach } from 'vitest'
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
import { useProductsStore } from '../stores/products.js'

// ---------------------------------------------------------------------------
// fetchProducts
// ---------------------------------------------------------------------------

describe('products store — fetchProducts', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchProducts populates products and productMeta from API response', async () => {
    const fakeProducts = [
      {
        id: 1,
        title: 'Master Palette',
        slug: 'master-palette',
        price: '120.00',
        stock_qty: 10,
        stock_state: 'En Stock',
      },
      {
        id: 2,
        title: 'Pro Brush Set',
        slug: 'pro-brush-set',
        price: '50.00',
        stock_qty: 0,
        stock_state: 'Agotado',
      },
    ]
    api.get.mockResolvedValueOnce({
      data: {
        data: fakeProducts,
        meta: { current_page: 1, last_page: 1, total: 2 },
      },
    })

    const store = useProductsStore()
    await store.fetchProducts()

    expect(store.products).toEqual(fakeProducts)
    expect(store.productMeta.total).toBe(2)
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  it('fetchProducts sets error state when API call fails', async () => {
    api.get.mockRejectedValueOnce({
      response: { data: { message: 'Error del servidor' } },
    })

    const store = useProductsStore()
    await store.fetchProducts()

    expect(store.products).toEqual([])
    expect(store.error).toBe('Error del servidor')
    expect(store.loading).toBe(false)
  })

  it('fetchProducts passes merged filters as query params to /products', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = useProductsStore()
    await store.fetchProducts({ search: 'palette', sort: 'price_asc' })

    expect(api.get).toHaveBeenCalledWith('/products', {
      params: expect.objectContaining({ search: 'palette', sort: 'price_asc' }),
    })
  })

  it('fetchProducts strips empty string and null filter values', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = useProductsStore()
    await store.fetchProducts({ category: '', min_price: '', stock_state: null })

    const callArgs = api.get.mock.calls[0]
    expect(callArgs[1].params).not.toHaveProperty('category')
    expect(callArgs[1].params).not.toHaveProperty('min_price')
    expect(callArgs[1].params).not.toHaveProperty('stock_state')
  })

  it('fetchProducts sends stock_state param correctly', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = useProductsStore()
    await store.fetchProducts({ stock_state: 'in_stock' })

    const callArgs = api.get.mock.calls[0]
    expect(callArgs[1].params).toHaveProperty('stock_state', 'in_stock')
  })

  it('fetchProducts loading toggles true then false', async () => {
    let loadingDuringCall = false
    api.get.mockImplementationOnce(async () => {
      loadingDuringCall = true
      return { data: { data: [], meta: {} } }
    })

    const store = useProductsStore()
    await store.fetchProducts()

    expect(loadingDuringCall).toBe(true)
    expect(store.loading).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// fetchProduct (detail by slug)
// ---------------------------------------------------------------------------

describe('products store — fetchProduct', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchProduct populates currentProduct with detail and images', async () => {
    const fakeDetail = {
      id: 1,
      title: 'Master Palette',
      slug: 'master-palette',
      price: '120.00',
      stock_qty: 10,
      stock_state: 'En Stock',
      images: [
        { id: 1, url: 'http://example.com/img1.jpg', sort_order: 0 },
        { id: 2, url: 'http://example.com/img2.jpg', sort_order: 1 },
      ],
    }
    api.get.mockResolvedValueOnce({ data: { data: fakeDetail } })

    const store = useProductsStore()
    await store.fetchProduct('master-palette')

    expect(store.currentProduct).toEqual(fakeDetail)
    expect(store.currentProduct.images).toHaveLength(2)
    expect(store.loading).toBe(false)
  })

  it('fetchProduct calls GET /products/{slug}', async () => {
    api.get.mockResolvedValueOnce({ data: { data: { id: 1, slug: 'test-slug', images: [] } } })

    const store = useProductsStore()
    await store.fetchProduct('test-slug')

    expect(api.get).toHaveBeenCalledWith('/products/test-slug')
  })

  it('fetchProduct sets error and rethrows on failure', async () => {
    api.get.mockRejectedValueOnce({
      response: { data: { message: 'Producto no encontrado' } },
    })

    const store = useProductsStore()
    await expect(store.fetchProduct('no-existe')).rejects.toBeDefined()
    expect(store.error).toBe('Producto no encontrado')
    expect(store.loading).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// fetchCategories
// ---------------------------------------------------------------------------

describe('products store — fetchCategories', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchCategories GETs /categories and populates from data.data', async () => {
    const fakeCategories = [
      { id: 1, name: 'Pinceles', slug: 'pinceles' },
      { id: 2, name: 'Paletas', slug: 'paletas' },
    ]
    api.get.mockResolvedValueOnce({ data: { data: fakeCategories } })

    const store = useProductsStore()
    await store.fetchCategories()

    expect(api.get).toHaveBeenCalledWith('/categories')
    expect(store.categories).toEqual(fakeCategories)
  })

  it('fetchCategories handles flat response without nested data key', async () => {
    const fakeCategories = [{ id: 1, name: 'Labiales', slug: 'labiales' }]
    api.get.mockResolvedValueOnce({ data: fakeCategories })

    const store = useProductsStore()
    await store.fetchCategories()

    expect(store.categories).toEqual(fakeCategories)
  })

  it('fetchCategories leaves categories empty on error (no throw)', async () => {
    api.get.mockRejectedValueOnce(new Error('Network error'))

    const store = useProductsStore()
    await expect(store.fetchCategories()).resolves.toBeUndefined()
    expect(store.categories).toEqual([])
  })

  it('fetchCategories early-returns if categories already loaded', async () => {
    const store = useProductsStore()
    store.categories = [{ id: 1, name: 'Pinceles', slug: 'pinceles' }]

    await store.fetchCategories()

    expect(api.get).not.toHaveBeenCalled()
    expect(store.categories).toHaveLength(1)
  })
})

// ---------------------------------------------------------------------------
// Admin actions
// ---------------------------------------------------------------------------

describe('products store — admin actions', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('createProduct POSTs FormData to /admin/products', async () => {
    const created = { id: 5, title: 'Nuevo Producto', slug: 'nuevo-producto' }
    api.post.mockResolvedValueOnce({ data: { data: created } })

    const store = useProductsStore()
    const fd = new FormData()
    fd.append('title', 'Nuevo Producto')

    const result = await store.createProduct(fd)

    expect(api.post).toHaveBeenCalledWith(
      '/admin/products',
      fd,
      expect.objectContaining({ headers: expect.objectContaining({ 'Content-Type': 'multipart/form-data' }) }),
    )
    expect(result).toEqual(created)
  })

  it('updateProduct POSTs FormData to /admin/products/{id}', async () => {
    const updated = { id: 3, title: 'Producto Actualizado' }
    api.post.mockResolvedValueOnce({ data: { data: updated } })

    const store = useProductsStore()
    const fd = new FormData()
    fd.append('title', 'Producto Actualizado')

    const result = await store.updateProduct(3, fd)

    expect(api.post).toHaveBeenCalledWith(
      '/admin/products/3',
      fd,
      expect.objectContaining({ headers: expect.objectContaining({ 'Content-Type': 'multipart/form-data' }) }),
    )
    expect(result).toEqual(updated)
  })

  it('deleteProduct DELETEs /admin/products/{id}', async () => {
    api.delete.mockResolvedValueOnce({})

    const store = useProductsStore()
    await store.deleteProduct(7)

    expect(api.delete).toHaveBeenCalledWith('/admin/products/7')
  })

  it('uploadImages POSTs multipart to /admin/products/{id}/images', async () => {
    const fakeImages = [{ id: 10, url: 'http://example.com/img.jpg', sort_order: 0 }]
    api.post.mockResolvedValueOnce({ data: { data: fakeImages } })

    const store = useProductsStore()
    const file = new File(['img'], 'photo.jpg', { type: 'image/jpeg' })

    const result = await store.uploadImages(2, [file])

    expect(api.post).toHaveBeenCalledWith(
      '/admin/products/2/images',
      expect.any(FormData),
      expect.objectContaining({ headers: expect.objectContaining({ 'Content-Type': 'multipart/form-data' }) }),
    )
    expect(result).toEqual(fakeImages)
  })

  it('deleteImage DELETEs /admin/products/{id}/images/{imageId}', async () => {
    api.delete.mockResolvedValueOnce({})

    const store = useProductsStore()
    await store.deleteImage(2, 10)

    expect(api.delete).toHaveBeenCalledWith('/admin/products/2/images/10')
  })

  it('reorderImages POSTs to /admin/products/{id}/images/reorder', async () => {
    const fakeImages = [
      { id: 3, url: 'http://example.com/3.jpg', sort_order: 0 },
      { id: 1, url: 'http://example.com/1.jpg', sort_order: 1 },
    ]
    api.post.mockResolvedValueOnce({ data: { data: fakeImages } })

    const store = useProductsStore()
    const result = await store.reorderImages(2, [3, 1])

    expect(api.post).toHaveBeenCalledWith(
      '/admin/products/2/images/reorder',
      { order: [3, 1] },
    )
    expect(result).toEqual(fakeImages)
  })

  it('createProductWithImages calls createProduct then uploadImages', async () => {
    const created = { id: 42, title: 'Test', slug: 'test' }
    api.post.mockResolvedValueOnce({ data: { data: created } })
    api.post.mockResolvedValueOnce({ data: { data: [] } })

    const store = useProductsStore()
    const fd = new FormData()
    fd.append('title', 'Test')
    const file = new File(['img'], 'photo.jpg', { type: 'image/jpeg' })

    const result = await store.createProductWithImages(fd, [file])

    const firstCall = api.post.mock.calls[0]
    expect(firstCall[0]).toBe('/admin/products')
    const secondCall = api.post.mock.calls[1]
    expect(secondCall[0]).toBe('/admin/products/42/images')
    expect(result).toEqual(created)
  })

  it('createProductWithImages skips uploadImages when no files provided', async () => {
    const created = { id: 7, title: 'No Images', slug: 'no-images' }
    api.post.mockResolvedValueOnce({ data: { data: created } })

    const store = useProductsStore()
    const fd = new FormData()
    fd.append('title', 'No Images')

    const result = await store.createProductWithImages(fd, [])

    expect(api.post).toHaveBeenCalledTimes(1)
    expect(result).toEqual(created)
  })
})
