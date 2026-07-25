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
