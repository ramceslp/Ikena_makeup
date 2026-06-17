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
import AdminServiceCreate from '../views/admin/AdminServiceCreate.vue'

// ---------------------------------------------------------------------------
// Router
// ---------------------------------------------------------------------------
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/admin/services/create', component: AdminServiceCreate, name: 'AdminServiceCreate' },
    { path: '/admin/services', component: { template: '<div/>' }, name: 'AdminServices' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

// ---------------------------------------------------------------------------
// C-2: AdminServiceCreate calls createServiceWithImages (two-step) not createService
// ---------------------------------------------------------------------------

describe('AdminServiceCreate.vue — two-step image create (C-2 integration)', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    await router.push('/admin/services/create')
  })

  it('calls createServiceWithImages(formData, files) when files are submitted', async () => {
    // fetchCategories
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(AdminServiceCreate, {
      global: { plugins: [router, createPinia()] },
    })
    await flushPromises()

    const store = useServicesStore()
    const createWithImagesSpy = vi.spyOn(store, 'createServiceWithImages').mockResolvedValue({ id: 99 })
    vi.spyOn(store, 'createService').mockResolvedValue({ id: 99 })

    // Fill required fields
    await wrapper.find('input[name="title"]').setValue('Nuevo Servicio')
    await wrapper.find('input[name="price"]').setValue('150')
    await wrapper.find('input[name="duration_hours"]').setValue('2')

    // Simulate file selection
    const fakeFile = new File(['img'], 'test.jpg', { type: 'image/jpeg' })
    const fileInput = wrapper.find('input[type="file"]')
    Object.defineProperty(fileInput.element, 'files', {
      value: [fakeFile],
      configurable: true,
    })
    await fileInput.trigger('change')

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    // Must call createServiceWithImages, NOT plain createService
    expect(createWithImagesSpy).toHaveBeenCalledTimes(1)
    const [calledFormData, calledFiles] = createWithImagesSpy.mock.calls[0]
    expect(calledFormData).toBeInstanceOf(FormData)
    expect(Array.isArray(calledFiles)).toBe(true)
    expect(calledFiles).toHaveLength(1)
    expect(calledFiles[0].name).toBe('test.jpg')
  })

  it('calls createServiceWithImages with empty files array when no files chosen', async () => {
    // fetchCategories
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(AdminServiceCreate, {
      global: { plugins: [router, createPinia()] },
    })
    await flushPromises()

    const store = useServicesStore()
    const createWithImagesSpy = vi.spyOn(store, 'createServiceWithImages').mockResolvedValue({ id: 100 })

    await wrapper.find('input[name="title"]').setValue('Sin Imágenes')
    await wrapper.find('input[name="price"]').setValue('200')
    await wrapper.find('input[name="duration_hours"]').setValue('1')

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(createWithImagesSpy).toHaveBeenCalledTimes(1)
    const [, calledFiles] = createWithImagesSpy.mock.calls[0]
    expect(Array.isArray(calledFiles)).toBe(true)
    expect(calledFiles).toHaveLength(0)
  })

  it('does NOT call plain createService directly during submit', async () => {
    // fetchCategories
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(AdminServiceCreate, {
      global: { plugins: [router, createPinia()] },
    })
    await flushPromises()

    const store = useServicesStore()
    vi.spyOn(store, 'createServiceWithImages').mockResolvedValue({ id: 101 })
    const createServiceSpy = vi.spyOn(store, 'createService')

    await wrapper.find('input[name="title"]').setValue('Direct Test')
    await wrapper.find('input[name="price"]').setValue('100')
    await wrapper.find('input[name="duration_hours"]').setValue('1')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    // createService must NOT be called directly from the view
    expect(createServiceSpy).not.toHaveBeenCalled()
  })
})
