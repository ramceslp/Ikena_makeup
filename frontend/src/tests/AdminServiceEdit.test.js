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
import AdminServiceEdit from '../views/admin/AdminServiceEdit.vue'

// ---------------------------------------------------------------------------
// Router with param :id
// ---------------------------------------------------------------------------
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/admin/services/:id/edit', component: AdminServiceEdit, name: 'AdminServiceEdit' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

// ---------------------------------------------------------------------------
// C-4: AdminServiceEdit uses store action (fetchAdminService)
// ---------------------------------------------------------------------------

const fakeService = {
  id: 3,
  title: 'Servicio Editable',
  description: 'Descripción de prueba',
  price: '180.00',
  duration_hours: 2,
  availability_type: 'by_appointment',
  category_id: null,
  is_published: true,
  images: [],
}

describe('AdminServiceEdit.vue — smoke tests (store-backed)', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    // Navigate to the edit route with id=3
    await router.push('/admin/services/3/edit')
  })

  it('calls fetchAdminService on mount (store action, not direct api)', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeService } })      // fetchAdminService
    api.get.mockResolvedValueOnce({ data: { data: [] } })               // fetchCategories

    const wrapper = mount(AdminServiceEdit, {
      global: { plugins: [router, createPinia()] },
    })
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/services/3')
  })

  it('renders AdminServiceForm with service data loaded via store', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeService } })
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(AdminServiceEdit, {
      global: { plugins: [router, createPinia()] },
    })
    await flushPromises()

    // The form should be visible and pre-populated with the service title
    expect(wrapper.find('input[name="title"]').element.value).toBe('Servicio Editable')
  })
})
