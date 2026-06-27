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

  it('calls updateProduct with id and a plain POST FormData (no _method spoof)', async () => {
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
    // The update route is POST, not PATCH — spoofing _method=PATCH made the
    // router reject it with 405. No spoof: a plain POST matches the route.
    expect(calledFormData.get('_method')).toBeNull()
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

  it('does NOT call updateProduct a second time when submit fires while in-flight (double-submit guard)', async () => {
    const wrapper = await mountEdit()
    await flushPromises()

    const store = useProductsStore()
    let resolveFlight
    const pendingPromise = new Promise((resolve) => { resolveFlight = resolve })
    const updateSpy = vi.spyOn(store, 'updateProduct').mockReturnValue(pendingPromise)

    // First submit — starts in-flight
    await wrapper.find('form').trigger('submit.prevent')
    // Second submit while first is still pending
    await wrapper.find('form').trigger('submit.prevent')

    resolveFlight({ id: 1 })
    await flushPromises()

    expect(updateSpy).toHaveBeenCalledTimes(1)
  })

  it('does NOT call deleteImage a second time on rapid double-click of the same image delete button', async () => {
    const wrapper = await mountEdit()
    await flushPromises()

    const store = useProductsStore()
    let resolveDelete
    const pendingDeletePromise = new Promise((resolve) => { resolveDelete = resolve })
    const deleteImageSpy = vi.spyOn(store, 'deleteImage').mockReturnValue(pendingDeletePromise)

    const deleteButtons = wrapper.findAll('[data-delete-image]')
    // First click — in-flight
    await deleteButtons[0].trigger('click')
    // Second click on SAME button while first is pending
    await deleteButtons[0].trigger('click')

    resolveDelete()
    await flushPromises()

    expect(deleteImageSpy).toHaveBeenCalledTimes(1)
  })

  it('shows error and does NOT call updateProduct when existing images + new files exceed 10', async () => {
    // Override fakeProduct with 10 images
    const tenImages = Array.from({ length: 10 }, (_, i) => ({
      id: 100 + i,
      url: `http://example.com/${i}.jpg`,
      sort_order: i,
    }))
    api.get.mockImplementation((url) => {
      if (url === '/admin/products/1') {
        return Promise.resolve({
          data: { data: { ...fakeProduct, images: tenImages } },
        })
      }
      if (url === '/categories') {
        return Promise.resolve({ data: { data: [{ id: 3, name: 'Labiales' }] } })
      }
      return Promise.resolve({ data: { data: [] } })
    })

    const wrapper = await mountEdit()
    await flushPromises()

    const store = useProductsStore()
    const updateSpy = vi.spyOn(store, 'updateProduct').mockResolvedValue({ id: 1 })

    // Add 1 new file — total would be 10 + 1 = 11 > 10
    const fakeFile = new File(['img'], 'extra.jpg', { type: 'image/jpeg' })
    const fileInput = wrapper.find('input[type="file"]')
    Object.defineProperty(fileInput.element, 'files', {
      value: [fakeFile],
      configurable: true,
    })
    await fileInput.trigger('change')

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(updateSpy).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('No se permiten más de 10 imágenes por producto.')
  })

  it('includes category_id="" in FormData when "Sin categoría" is selected', async () => {
    const wrapper = await mountEdit()
    await flushPromises()

    const store = useProductsStore()
    const updateSpy = vi.spyOn(store, 'updateProduct').mockResolvedValue({ id: 1 })

    // Select "Sin categoría" (value="")
    await wrapper.find('select[name="category_id"]').setValue('')

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(updateSpy).toHaveBeenCalledTimes(1)
    const [, calledFormData] = updateSpy.mock.calls[0]
    expect(calledFormData.get('category_id')).toBe('')
  })

  it('includes description="" in FormData when the description input is cleared', async () => {
    const wrapper = await mountEdit()
    await flushPromises()

    const store = useProductsStore()
    const updateSpy = vi.spyOn(store, 'updateProduct').mockResolvedValue({ id: 1 })

    // Clear the description field
    await wrapper.find('textarea[name="description"]').setValue('')

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(updateSpy).toHaveBeenCalledTimes(1)
    const [, calledFormData] = updateSpy.mock.calls[0]
    expect(calledFormData.get('description')).toBe('')
  })
})
