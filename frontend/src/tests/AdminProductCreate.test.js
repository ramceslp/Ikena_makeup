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
import { useProductsStore } from '../stores/products.js'
import AdminProductCreate from '../views/admin/AdminProductCreate.vue'

// ---------------------------------------------------------------------------
// Router
// ---------------------------------------------------------------------------
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/admin/products/new', component: AdminProductCreate, name: 'AdminProductCreate' },
    { path: '/admin/products', component: { template: '<div/>' }, name: 'AdminProducts' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('AdminProductCreate.vue — create form + createProductWithImages', () => {
  let pinia

  beforeEach(async () => {
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
    await router.push('/admin/products/new')
  })

  it('renders the create form with title and price inputs', async () => {
    // fetchCategories
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(AdminProductCreate, {
      global: { plugins: [pinia, router] },
    })
    await flushPromises()

    expect(wrapper.find('input[name="title"]').exists()).toBe(true)
    expect(wrapper.find('input[name="price"]').exists()).toBe(true)
  })

  it('renders stock_qty and is_published fields', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(AdminProductCreate, {
      global: { plugins: [pinia, router] },
    })
    await flushPromises()

    expect(wrapper.find('input[name="stock_qty"]').exists()).toBe(true)
    expect(wrapper.find('input[name="is_published"]').exists()).toBe(true)
  })

  it('renders a file input for image upload', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(AdminProductCreate, {
      global: { plugins: [pinia, router] },
    })
    await flushPromises()

    expect(wrapper.find('input[type="file"]').exists()).toBe(true)
  })

  it('calls createProductWithImages(formData, files) on submit with files', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(AdminProductCreate, {
      global: { plugins: [pinia, router] },
    })
    await flushPromises()

    const store = useProductsStore()
    const createWithImagesSpy = vi
      .spyOn(store, 'createProductWithImages')
      .mockResolvedValue({ id: 42, slug: 'labial-rojo' })

    await wrapper.find('input[name="title"]').setValue('Labial Rojo')
    await wrapper.find('input[name="price"]').setValue('25.00')
    await wrapper.find('input[name="stock_qty"]').setValue('10')

    const fakeFile = new File(['img'], 'product.jpg', { type: 'image/jpeg' })
    const fileInput = wrapper.find('input[type="file"]')
    Object.defineProperty(fileInput.element, 'files', {
      value: [fakeFile],
      configurable: true,
    })
    await fileInput.trigger('change')

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(createWithImagesSpy).toHaveBeenCalledTimes(1)
    const [calledFormData, calledFiles] = createWithImagesSpy.mock.calls[0]
    expect(calledFormData).toBeInstanceOf(FormData)
    expect(Array.isArray(calledFiles)).toBe(true)
    expect(calledFiles).toHaveLength(1)
    expect(calledFiles[0].name).toBe('product.jpg')
  })

  it('calls createProductWithImages with empty files array when no files chosen', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(AdminProductCreate, {
      global: { plugins: [pinia, router] },
    })
    await flushPromises()

    const store = useProductsStore()
    const createWithImagesSpy = vi
      .spyOn(store, 'createProductWithImages')
      .mockResolvedValue({ id: 43 })

    await wrapper.find('input[name="title"]').setValue('Sin Imágenes')
    await wrapper.find('input[name="price"]').setValue('10.00')
    await wrapper.find('input[name="stock_qty"]').setValue('5')

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(createWithImagesSpy).toHaveBeenCalledTimes(1)
    const [, calledFiles] = createWithImagesSpy.mock.calls[0]
    expect(Array.isArray(calledFiles)).toBe(true)
    expect(calledFiles).toHaveLength(0)
  })

  it('does NOT call createProduct directly during submit', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(AdminProductCreate, {
      global: { plugins: [pinia, router] },
    })
    await flushPromises()

    const store = useProductsStore()
    vi.spyOn(store, 'createProductWithImages').mockResolvedValue({ id: 44 })
    const createProductSpy = vi.spyOn(store, 'createProduct')

    await wrapper.find('input[name="title"]').setValue('Direct Test')
    await wrapper.find('input[name="price"]').setValue('10.00')
    await wrapper.find('input[name="stock_qty"]').setValue('1')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(createProductSpy).not.toHaveBeenCalled()
  })

  it('does NOT call createProductWithImages a second time when submit fires while in-flight', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(AdminProductCreate, {
      global: { plugins: [pinia, router] },
    })
    await flushPromises()

    const store = useProductsStore()
    let resolveFlight
    const pendingPromise = new Promise((resolve) => { resolveFlight = resolve })
    const createWithImagesSpy = vi
      .spyOn(store, 'createProductWithImages')
      .mockReturnValue(pendingPromise)

    await wrapper.find('input[name="title"]').setValue('Doble Submit')
    await wrapper.find('input[name="price"]').setValue('10.00')
    await wrapper.find('input[name="stock_qty"]').setValue('1')

    // First submit — starts the in-flight request
    await wrapper.find('form').trigger('submit.prevent')
    // Second submit while first is still pending
    await wrapper.find('form').trigger('submit.prevent')

    // Resolve the first (and only) call
    resolveFlight({ id: 99 })
    await flushPromises()

    expect(createWithImagesSpy).toHaveBeenCalledTimes(1)
  })

  it('does NOT call createProductWithImages and shows error when more than 10 files are selected', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(AdminProductCreate, {
      global: { plugins: [pinia, router] },
    })
    await flushPromises()

    const store = useProductsStore()
    const createWithImagesSpy = vi
      .spyOn(store, 'createProductWithImages')
      .mockResolvedValue({ id: 50 })

    await wrapper.find('input[name="title"]').setValue('Too Many Images')
    await wrapper.find('input[name="price"]').setValue('10.00')
    await wrapper.find('input[name="stock_qty"]').setValue('5')

    // Simulate 11 files selected
    const elevenFiles = Array.from({ length: 11 }, (_, i) =>
      new File(['img'], `img${i}.jpg`, { type: 'image/jpeg' })
    )
    const fileInput = wrapper.find('input[type="file"]')
    Object.defineProperty(fileInput.element, 'files', {
      value: elevenFiles,
      configurable: true,
    })
    await fileInput.trigger('change')

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(createWithImagesSpy).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('No se permiten más de 10 imágenes por producto.')
  })

  it('redirects to /admin/products after successful create', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(AdminProductCreate, {
      global: { plugins: [pinia, router] },
    })
    await flushPromises()

    const store = useProductsStore()
    vi.spyOn(store, 'createProductWithImages').mockResolvedValue({ id: 45 })

    await wrapper.find('input[name="title"]').setValue('Redirect Test')
    await wrapper.find('input[name="price"]').setValue('10.00')
    await wrapper.find('input[name="stock_qty"]').setValue('1')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/admin/products')
  })
})
