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

// ---------------------------------------------------------------------------
// C-2 (edit path): submitting with files calls updateService THEN uploadImages
// ---------------------------------------------------------------------------

describe('AdminServiceEdit.vue — two-step image update on submit with files', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    await router.push('/admin/services/3/edit')
  })

  it('calls updateService then uploadImages when files are submitted', async () => {
    // Initial data load: fetchAdminService + fetchCategories
    api.get.mockResolvedValueOnce({ data: { data: fakeService } })
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(AdminServiceEdit, {
      global: { plugins: [router, createPinia()] },
    })
    await flushPromises()

    const store = useServicesStore()
    const updateSpy = vi.spyOn(store, 'updateService').mockResolvedValue(fakeService)
    const uploadSpy = vi.spyOn(store, 'uploadImages').mockResolvedValue([])
    // Also mock fetchAdminService for the post-save refresh
    vi.spyOn(store, 'fetchAdminService').mockResolvedValue(fakeService)

    // Simulate file selection inside the form
    const fakeFile = new File(['img'], 'edit.jpg', { type: 'image/jpeg' })
    const fileInput = wrapper.find('input[type="file"]')
    Object.defineProperty(fileInput.element, 'files', {
      value: [fakeFile],
      configurable: true,
    })
    await fileInput.trigger('change')

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(updateSpy).toHaveBeenCalledTimes(1)
    // updateService called with (id, formData)
    expect(updateSpy.mock.calls[0][0]).toBe('3')
    expect(updateSpy.mock.calls[0][1]).toBeInstanceOf(FormData)

    // uploadImages called with (id, files)
    expect(uploadSpy).toHaveBeenCalledTimes(1)
    expect(uploadSpy.mock.calls[0][0]).toBe('3')
    const uploadedFiles = uploadSpy.mock.calls[0][1]
    expect(Array.isArray(uploadedFiles)).toBe(true)
    expect(uploadedFiles).toHaveLength(1)
    expect(uploadedFiles[0].name).toBe('edit.jpg')
  })

  it('does NOT call uploadImages when no files are chosen on submit', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeService } })
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(AdminServiceEdit, {
      global: { plugins: [router, createPinia()] },
    })
    await flushPromises()

    const store = useServicesStore()
    vi.spyOn(store, 'updateService').mockResolvedValue(fakeService)
    const uploadSpy = vi.spyOn(store, 'uploadImages').mockResolvedValue([])
    vi.spyOn(store, 'fetchAdminService').mockResolvedValue(fakeService)

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(uploadSpy).not.toHaveBeenCalled()
  })
})
