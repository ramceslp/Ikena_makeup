import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

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
import AdminServices from '../views/admin/AdminServices.vue'

// ---------------------------------------------------------------------------
// Router stub
// ---------------------------------------------------------------------------
const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div/>' } }],
})

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const fakeServices = [
  { id: 1, title: 'Maquillaje Social', slug: 'maquillaje-social', price: '120.00', is_published: true, thumbnail: null, category: null },
  { id: 2, title: 'Masterclass Novia', slug: 'masterclass-novia', price: '250.00', is_published: false, thumbnail: null, category: null },
]

function mountAdminServices() {
  return mount(AdminServices, {
    global: { plugins: [router, createPinia()] },
  })
}

// ---------------------------------------------------------------------------
// C-3: AdminServices uses store action (fetchAdminServices)
// ---------------------------------------------------------------------------

describe('AdminServices.vue — smoke tests (store-backed)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('calls fetchAdminServices on mount (store action, not direct api)', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakeServices, meta: { current_page: 1, last_page: 1, total: 2 } },
    })

    const wrapper = mountAdminServices()
    await flushPromises()

    // fetchAdminServices should have called GET /admin/services via store
    expect(api.get).toHaveBeenCalledWith('/admin/services')
  })

  it('renders service titles from store after load', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakeServices, meta: { current_page: 1, last_page: 1, total: 2 } },
    })

    const wrapper = mountAdminServices()
    await flushPromises()

    expect(wrapper.text()).toContain('Maquillaje Social')
    expect(wrapper.text()).toContain('Masterclass Novia')
  })

  it('renders Publicado badge for published service', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakeServices, meta: {} },
    })

    const wrapper = mountAdminServices()
    await flushPromises()

    expect(wrapper.text()).toContain('Publicado')
  })

  it('renders Borrador badge for unpublished service', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakeServices, meta: {} },
    })

    const wrapper = mountAdminServices()
    await flushPromises()

    expect(wrapper.text()).toContain('Borrador')
  })

  it('each service row has a Horarios link/button targeting the slots route for that service', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakeServices, meta: {} },
    })

    const wrapper = mountAdminServices()
    await flushPromises()

    const slotsLinks = wrapper.findAll('[data-slots-link]')
    expect(slotsLinks).toHaveLength(fakeServices.length)

    // Each link must target the correct service slots path
    fakeServices.forEach((svc, i) => {
      const link = slotsLinks[i]
      const href = link.attributes('href') || link.attributes('to') || ''
      expect(href).toContain(`/admin/services/${svc.id}/slots`)
    })
  })
})
