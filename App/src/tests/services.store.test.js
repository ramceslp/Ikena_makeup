import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

import api from '../services/api.js'
import { useServicesStore } from '../stores/services.js'

// Trimmed store: only fetchServices(), needed by FeaturedServices.vue for
// Home's "3 most-recent services" section. fetchService (detail) lands in
// Phase 7 alongside ServiceDetail.vue/booking (see stores/services.js).
describe('services store (trimmed: fetchServices only, see Home.vue)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchServices GETs /services with the given filters as query params and populates services/serviceMeta', async () => {
    const fakeServices = [{ id: 1, title: 'Maquillaje de Novia' }]
    api.get.mockResolvedValueOnce({ data: { data: fakeServices, meta: { total: 1 } } })

    const store = useServicesStore()
    await store.fetchServices({ page: 1, per_page: 3, sort: 'newest' })

    expect(api.get).toHaveBeenCalledWith('/services', { params: { page: 1, per_page: 3, sort: 'newest' } })
    expect(store.services).toEqual(fakeServices)
    expect(store.serviceMeta).toEqual({ total: 1 })
  })

  it('fetchServices strips empty/null/undefined filter values from the query params', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = useServicesStore()
    await store.fetchServices({ page: 1, category: '', availability_type: undefined })

    expect(api.get).toHaveBeenCalledWith('/services', { params: { page: 1 } })
  })

  it('sets a Spanish error message and leaves services untouched when the request fails', async () => {
    api.get.mockRejectedValueOnce(new Error('Network Error'))

    const store = useServicesStore()
    await store.fetchServices({})

    expect(store.error).toBe('Error al cargar los servicios')
    expect(store.services).toEqual([])
  })
})

// Phase 7: fetchService (detail) + fetchCategories, needed by
// ServiceDetail.vue and Services.vue's category filter. Admin CRUD/upload
// actions are intentionally NOT ported -- no admin surface in this app (see
// services.js's file-level comment and the spec's Mobile App Boundaries).
describe('services store — fetchService + fetchCategories (Phase 7)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchService GETs /services/:slug and populates currentService', async () => {
    const fakeService = { id: 1, slug: 'maquillaje-novia', title: 'Maquillaje de Novia' }
    api.get.mockResolvedValueOnce({ data: { data: fakeService } })

    const store = useServicesStore()
    const result = await store.fetchService('maquillaje-novia')

    expect(api.get).toHaveBeenCalledWith('/services/maquillaje-novia')
    expect(store.currentService).toEqual(fakeService)
    expect(result).toEqual(fakeService)
  })

  it('fetchService sets a Spanish error message and re-throws on failure', async () => {
    const notFound = new Error('Not Found')
    notFound.response = { status: 404 }
    api.get.mockRejectedValueOnce(notFound)

    const store = useServicesStore()
    await expect(store.fetchService('missing')).rejects.toThrow('Not Found')

    expect(store.error).toBe('Error al cargar el servicio')
  })

  it('fetchCategories GETs /categories and populates categories once, skipping subsequent calls', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [{ id: 1, name: 'Novias', slug: 'novias' }] } })

    const store = useServicesStore()
    await store.fetchCategories()
    await store.fetchCategories()

    expect(api.get).toHaveBeenCalledTimes(1)
    expect(store.categories).toEqual([{ id: 1, name: 'Novias', slug: 'novias' }])
  })
})
