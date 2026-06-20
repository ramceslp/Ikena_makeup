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
import AdminProductEdit from '../views/admin/AdminProductEdit.vue'

// ---------------------------------------------------------------------------
// Router
// ---------------------------------------------------------------------------
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/admin/products/:id/edit', component: AdminProductEdit, name: 'AdminProductEdit' },
    { path: '/admin/products', component: { template: '<div/>' }, name: 'AdminProducts' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

// ---------------------------------------------------------------------------
// Fixture
// ---------------------------------------------------------------------------
const fakeProduct = {
  id: 1,
  title: 'Labial Rojo',
  slug: 'labial-rojo',
  description: 'Un labial rojo intenso.',
  price: '25.00',
  stock_qty: 8,
  is_published: false,
  category: { id: 3, name: 'Labiales' },
  images: [
    { id: 10, url: 'http://example.com/1.jpg', sort_order: 0 },
    { id: 11, url: 'http://example.com/2.jpg', sort_order: 1 },
  ],
}

// URL-based GET mock so Promise.all ordering does not matter.
function mockGet() {
  api.get.mockImplementation((url) => {
    if (url === '/admin/products/1') {
      return Promise.resolve({ data: { data: fakeProduct } })
    }
    if (url === '/categories') {
      return Promise.resolve({ data: { data: [{ id: 3, name: 'Labiales' }] } })
    }
    return Promise.resolve({ data: { data: [] } })
  })
}

async function mountEdit() {
  await router.push('/admin/products/1/edit')
  return mount(AdminProductEdit, {
    global: { plugins: [pinia, router] },
  })
}

let pinia

describe('AdminProductEdit.vue — load + update + image ops', () => {
  beforeEach(() => {
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
    mockGet()
  })

  it('loads the product by id (admin endpoint) on mount', async () => {
    await mountEdit()
    await flushPromises()
    expect(api.get).toHaveBeenCalledWith('/admin/products/1')
  })

  it('prefills the title input with the existing product title', async () => {
    const wrapper = await mountEdit()
    await flushPromises()
    expect(wrapper.find('input[name="title"]').element.value).toBe('Labial Rojo')
  })

  it('renders the existing images', async () => {
    const wrapper = await mountEdit()
    await flushPromises()
    expect(wrapper.findAll('[data-existing-image]')).toHaveLength(2)
  })

  it('calls updateProduct with id and a FormData containing _method=PATCH', async () => {
    const wrapper = await mountEdit()
    await flushPromises()

    const store = useProductsStore()
    const updateSpy = vi.spyOn(store, 'updateProduct').mockResolvedValue({ id: 1 })

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(updateSpy).toHaveBeenCalledTimes(1)
    const [calledId, calledFormData] = updateSpy.mock.calls[0]
    expect(calledId).toBe('1')
    expect(calledFormData).toBeInstanceOf(FormData)
    expect(calledFormData.get('_method')).toBe('PATCH')
    expect(calledFormData.get('title')).toBe('Labial Rojo')
  })

  it('uploads newly selected files via uploadImages after update', async () => {
    const wrapper = await mountEdit()
    await flushPromises()

    const store = useProductsStore()
    vi.spyOn(store, 'updateProduct').mockResolvedValue({ id: 1 })
    const uploadSpy = vi.spyOn(store, 'uploadImages').mockResolvedValue([])

    const fakeFile = new File(['img'], 'nueva.jpg', { type: 'image/jpeg' })
    const fileInput = wrapper.find('input[type="file"]')
    Object.defineProperty(fileInput.element, 'files', {
      value: [fakeFile],
      configurable: true,
    })
    await fileInput.trigger('change')

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(uploadSpy).toHaveBeenCalledTimes(1)
    const [calledId, calledFiles] = uploadSpy.mock.calls[0]
    expect(calledId).toBe('1')
    expect(calledFiles).toHaveLength(1)
    expect(calledFiles[0].name).toBe('nueva.jpg')
  })

  it('does NOT call uploadImages when no new files are selected', async () => {
    const wrapper = await mountEdit()
    await flushPromises()

    const store = useProductsStore()
    vi.spyOn(store, 'updateProduct').mockResolvedValue({ id: 1 })
    const uploadSpy = vi.spyOn(store, 'uploadImages').mockResolvedValue([])

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(uploadSpy).not.toHaveBeenCalled()
  })

  it('calls deleteImage when an existing image delete button is clicked', async () => {
    const wrapper = await mountEdit()
    await flushPromises()

    const store = useProductsStore()
    const deleteImageSpy = vi.spyOn(store, 'deleteImage').mockResolvedValue()

    await wrapper.findAll('[data-delete-image]')[0].trigger('click')
    await flushPromises()

    expect(deleteImageSpy).toHaveBeenCalledWith('1', 10)
  })

  it('redirects to /admin/products after a successful update', async () => {
    const wrapper = await mountEdit()
    await flushPromises()

    const store = useProductsStore()
    vi.spyOn(store, 'updateProduct').mockResolvedValue({ id: 1 })

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/admin/products')
  })
})
