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
import { useServicesStore } from '../stores/services.js'

// ---------------------------------------------------------------------------
// fetchServices
// ---------------------------------------------------------------------------

describe('services store — fetchServices', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchServices populates services and serviceMeta from API response', async () => {
    const fakeServices = [
      { id: 1, title: 'Maquillaje Social', slug: 'maquillaje-social', price: '120.00' },
      { id: 2, title: 'Masterclass Novia', slug: 'masterclass-novia', price: '250.00' },
    ]
    api.get.mockResolvedValueOnce({
      data: {
        data: fakeServices,
        meta: { current_page: 1, last_page: 1, total: 2 },
      },
    })

    const store = useServicesStore()
    await store.fetchServices()

    expect(store.services).toEqual(fakeServices)
    expect(store.serviceMeta.total).toBe(2)
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  it('fetchServices does NOT persist filters across calls (per_page contamination guard)', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: {} } })

    const store = useServicesStore()
    await store.fetchServices({ page: 1, per_page: 3, sort: 'newest' })
    await store.fetchServices()

    const lastCall = api.get.mock.calls[api.get.mock.calls.length - 1]
    expect(lastCall[1].params).not.toHaveProperty('per_page')
    expect(store.filters).not.toHaveProperty('per_page')
  })

  it('fetchServices sets error state when API call fails', async () => {
    api.get.mockRejectedValueOnce({
      response: { data: { message: 'Error del servidor' } },
    })

    const store = useServicesStore()
    await store.fetchServices()

    expect(store.services).toEqual([])
    expect(store.error).toBe('Error del servidor')
    expect(store.loading).toBe(false)
  })

  it('fetchServices passes merged filters as query params', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = useServicesStore()
    await store.fetchServices({ search: 'novia', sort: 'price_asc' })

    expect(api.get).toHaveBeenCalledWith('/services', {
      params: expect.objectContaining({ search: 'novia', sort: 'price_asc' }),
    })
  })

  it('fetchServices strips empty string and null filter values', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = useServicesStore()
    await store.fetchServices({ category: '', min_price: '', availability_type: null })

    const callArgs = api.get.mock.calls[0]
    expect(callArgs[1].params).not.toHaveProperty('category')
    expect(callArgs[1].params).not.toHaveProperty('min_price')
    expect(callArgs[1].params).not.toHaveProperty('availability_type')
  })

  // C-1: availability_type must be sent as availability_type (backend reads ?availability_type=)
  it('fetchServices sends availability_type (not availability) in request params', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = useServicesStore()
    await store.fetchServices({ availability_type: 'immediate' })

    const callArgs = api.get.mock.calls[0]
    expect(callArgs[1].params).toHaveProperty('availability_type', 'immediate')
    expect(callArgs[1].params).not.toHaveProperty('availability')
  })

  it('fetchServices loading toggles true then false', async () => {
    let loadingDuringCall = false
    api.get.mockImplementationOnce(async () => {
      loadingDuringCall = true
      return { data: { data: [], meta: {} } }
    })

    const store = useServicesStore()
    await store.fetchServices()

    expect(loadingDuringCall).toBe(true)
    expect(store.loading).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// fetchService (detail)
// ---------------------------------------------------------------------------

describe('services store — fetchService', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchService populates currentService with detail and images', async () => {
    const fakeDetail = {
      id: 1,
      title: 'Maquillaje Social',
      slug: 'maquillaje-social',
      price: '120.00',
      images: [
        { id: 1, url: 'http://example.com/img1.jpg', sort_order: 0 },
        { id: 2, url: 'http://example.com/img2.jpg', sort_order: 1 },
      ],
    }
    api.get.mockResolvedValueOnce({ data: { data: fakeDetail } })

    const store = useServicesStore()
    await store.fetchService('maquillaje-social')

    expect(store.currentService).toEqual(fakeDetail)
    expect(store.currentService.images).toHaveLength(2)
    expect(store.loading).toBe(false)
  })

  it('fetchService calls GET /services/{slug}', async () => {
    api.get.mockResolvedValueOnce({ data: { data: { id: 1, slug: 'test-slug', images: [] } } })

    const store = useServicesStore()
    await store.fetchService('test-slug')

    expect(api.get).toHaveBeenCalledWith('/services/test-slug')
  })

  it('fetchService sets error and rethrows on failure', async () => {
    api.get.mockRejectedValueOnce({
      response: { data: { message: 'Servicio no encontrado' } },
    })

    const store = useServicesStore()
    await expect(store.fetchService('no-existe')).rejects.toBeDefined()
    expect(store.error).toBe('Servicio no encontrado')
    expect(store.loading).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// fetchCategories
// ---------------------------------------------------------------------------

describe('services store — fetchCategories', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchCategories GETs /categories and populates from data.data', async () => {
    const fakeCategories = [
      { id: 1, name: 'Social', slug: 'social' },
      { id: 2, name: 'Novias', slug: 'novias' },
    ]
    api.get.mockResolvedValueOnce({ data: { data: fakeCategories } })

    const store = useServicesStore()
    await store.fetchCategories()

    expect(api.get).toHaveBeenCalledWith('/categories')
    expect(store.categories).toEqual(fakeCategories)
  })

  it('fetchCategories handles flat response without nested data key', async () => {
    const fakeCategories = [{ id: 1, name: 'Noche', slug: 'noche' }]
    api.get.mockResolvedValueOnce({ data: fakeCategories })

    const store = useServicesStore()
    await store.fetchCategories()

    expect(store.categories).toEqual(fakeCategories)
  })

  it('fetchCategories leaves categories empty on error (no throw)', async () => {
    api.get.mockRejectedValueOnce(new Error('Network error'))

    const store = useServicesStore()
    await expect(store.fetchCategories()).resolves.toBeUndefined()
    expect(store.categories).toEqual([])
  })
})

// ---------------------------------------------------------------------------
// Admin actions
// ---------------------------------------------------------------------------

describe('services store — admin actions', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('createService POSTs FormData to /admin/services', async () => {
    const created = { id: 5, title: 'Nuevo Servicio', slug: 'nuevo-servicio' }
    api.post.mockResolvedValueOnce({ data: { data: created } })

    const store = useServicesStore()
    const fd = new FormData()
    fd.append('title', 'Nuevo Servicio')

    const result = await store.createService(fd)

    expect(api.post).toHaveBeenCalledWith(
      '/admin/services',
      fd,
      expect.objectContaining({ headers: expect.objectContaining({ 'Content-Type': 'multipart/form-data' }) }),
    )
    expect(result).toEqual(created)
  })

  it('updateService POSTs FormData to /admin/services/{id}', async () => {
    const updated = { id: 3, title: 'Servicio Actualizado' }
    api.post.mockResolvedValueOnce({ data: { data: updated } })

    const store = useServicesStore()
    const fd = new FormData()
    fd.append('title', 'Servicio Actualizado')

    const result = await store.updateService(3, fd)

    expect(api.post).toHaveBeenCalledWith(
      '/admin/services/3',
      fd,
      expect.objectContaining({ headers: expect.objectContaining({ 'Content-Type': 'multipart/form-data' }) }),
    )
    expect(result).toEqual(updated)
  })

  it('deleteService DELETEs /admin/services/{id}', async () => {
    api.delete.mockResolvedValueOnce({})

    const store = useServicesStore()
    await store.deleteService(7)

    expect(api.delete).toHaveBeenCalledWith('/admin/services/7')
  })

  it('uploadImages POSTs multipart to /admin/services/{id}/images', async () => {
    const fakeImages = [{ id: 10, url: 'http://example.com/img.jpg', sort_order: 0 }]
    api.post.mockResolvedValueOnce({ data: { data: fakeImages } })

    const store = useServicesStore()
    const file = new File(['img'], 'photo.jpg', { type: 'image/jpeg' })

    const result = await store.uploadImages(2, [file])

    expect(api.post).toHaveBeenCalledWith(
      '/admin/services/2/images',
      expect.any(FormData),
      expect.objectContaining({ headers: expect.objectContaining({ 'Content-Type': 'multipart/form-data' }) }),
    )
    expect(result).toEqual(fakeImages)
  })

  it('deleteImage DELETEs /admin/services/{id}/images/{imageId}', async () => {
    api.delete.mockResolvedValueOnce({})

    const store = useServicesStore()
    await store.deleteImage(2, 10)

    expect(api.delete).toHaveBeenCalledWith('/admin/services/2/images/10')
  })

  it('reorderImages PATCHes /admin/services/{id}/images/reorder', async () => {
    const fakeImages = [
      { id: 3, url: 'http://example.com/3.jpg', sort_order: 0 },
      { id: 1, url: 'http://example.com/1.jpg', sort_order: 1 },
    ]
    api.patch.mockResolvedValueOnce({ data: { data: fakeImages } })

    const store = useServicesStore()
    const result = await store.reorderImages(2, [3, 1])

    expect(api.patch).toHaveBeenCalledWith(
      '/admin/services/2/images/reorder',
      { order: [3, 1] },
    )
    expect(result).toEqual(fakeImages)
  })

  // C-2: two-step create — createService then uploadImages when files present
  it('createServiceWithImages calls createService then uploadImages with new id and files', async () => {
    const created = { id: 42, title: 'Test', slug: 'test' }
    api.post.mockResolvedValueOnce({ data: { data: created } }) // createService
    api.post.mockResolvedValueOnce({ data: { data: [] } })      // uploadImages

    const store = useServicesStore()
    const fd = new FormData()
    fd.append('title', 'Test')
    const file = new File(['img'], 'photo.jpg', { type: 'image/jpeg' })

    const result = await store.createServiceWithImages(fd, [file])

    // First call: POST /admin/services (no images in FormData)
    const firstCall = api.post.mock.calls[0]
    expect(firstCall[0]).toBe('/admin/services')
    // Second call: POST /admin/services/42/images
    const secondCall = api.post.mock.calls[1]
    expect(secondCall[0]).toBe('/admin/services/42/images')
    expect(result).toEqual(created)
  })

  it('createServiceWithImages skips uploadImages when no files provided', async () => {
    const created = { id: 7, title: 'No Images', slug: 'no-images' }
    api.post.mockResolvedValueOnce({ data: { data: created } })

    const store = useServicesStore()
    const fd = new FormData()
    fd.append('title', 'No Images')

    const result = await store.createServiceWithImages(fd, [])

    expect(api.post).toHaveBeenCalledTimes(1)
    expect(result).toEqual(created)
  })

  // C-3: fetchAdminServices — GET /admin/services, stores into state
  it('fetchAdminServices GETs /admin/services and sets services + serviceMeta', async () => {
    const fakeServices = [
      { id: 1, title: 'Svc A', is_published: true },
      { id: 2, title: 'Svc B', is_published: false },
    ]
    api.get.mockResolvedValueOnce({
      data: { data: fakeServices, meta: { current_page: 1, last_page: 1, total: 2 } },
    })

    const store = useServicesStore()
    await store.fetchAdminServices()

    expect(api.get).toHaveBeenCalledWith('/admin/services')
    expect(store.services).toEqual(fakeServices)
    expect(store.serviceMeta.total).toBe(2)
  })

  // C-4: fetchAdminService — GET /admin/services/{id}, stores into currentService
  it('fetchAdminService GETs /admin/services/{id} and sets currentService', async () => {
    const fakeService = { id: 5, title: 'Svc Detail', is_published: true, images: [] }
    api.get.mockResolvedValueOnce({ data: { data: fakeService } })

    const store = useServicesStore()
    await store.fetchAdminService(5)

    expect(api.get).toHaveBeenCalledWith('/admin/services/5')
    expect(store.currentService).toEqual(fakeService)
  })

  // S-1: fetchCategories cache guard
  it('fetchCategories early-returns and skips API call if categories already loaded', async () => {
    const store = useServicesStore()
    store.categories = [{ id: 1, name: 'Social', slug: 'social' }]

    await store.fetchCategories()

    expect(api.get).not.toHaveBeenCalled()
    expect(store.categories).toHaveLength(1)
  })
})
