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
import AdminProducts from '../views/admin/AdminProducts.vue'

// ---------------------------------------------------------------------------
// Router stub
// ---------------------------------------------------------------------------
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/admin/products', component: AdminProducts, name: 'AdminProducts' },
    { path: '/admin/products/new', component: { template: '<div/>' }, name: 'AdminProductCreate' },
    { path: '/admin/products/:id/edit', component: { template: '<div/>' }, name: 'AdminProductEdit' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------
const fakeProducts = [
  {
    id: 1,
    title: 'Labial Rojo',
    slug: 'labial-rojo',
    price: '25.00',
    stock_qty: 10,
    stock_state: 'En Stock',
    is_published: true,
    thumbnail: null,
    category: { id: 1, name: 'Labiales' },
  },
  {
    id: 2,
    title: 'Base Beige',
    slug: 'base-beige',
    price: '45.00',
    stock_qty: 2,
    stock_state: 'Últimas unidades',
    is_published: false,
    thumbnail: null,
    category: null,
  },
  {
    id: 3,
    title: 'Sombra Nude',
    slug: 'sombra-nude',
    price: '18.00',
    stock_qty: 0,
    stock_state: 'Agotado',
    is_published: true,
    thumbnail: null,
    category: null,
  },
]

function mountAdminProducts(pinia) {
  return mount(AdminProducts, {
    global: { plugins: [pinia, router] },
  })
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('AdminProducts.vue — list renders + basic interactions', () => {
  let pinia

  beforeEach(async () => {
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
    await router.push('/admin/products')
  })

  it('calls fetchProducts (admin variant) on mount', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakeProducts, meta: { current_page: 1, last_page: 1, total: 3 } },
    })

    const wrapper = mountAdminProducts(pinia)
    await flushPromises()

    // The store calls GET /products for public; admin uses GET /admin/products
    expect(api.get).toHaveBeenCalled()
  })

  it('renders product titles after load', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakeProducts, meta: {} },
    })

    const wrapper = mountAdminProducts(pinia)
    await flushPromises()

    expect(wrapper.text()).toContain('Labial Rojo')
    expect(wrapper.text()).toContain('Base Beige')
    expect(wrapper.text()).toContain('Sombra Nude')
  })

  it('renders Publicado badge for published product', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakeProducts, meta: {} },
    })

    const wrapper = mountAdminProducts(pinia)
    await flushPromises()

    expect(wrapper.text()).toContain('Publicado')
  })

  it('renders Borrador badge for unpublished product', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakeProducts, meta: {} },
    })

    const wrapper = mountAdminProducts(pinia)
    await flushPromises()

    expect(wrapper.text()).toContain('Borrador')
  })

  it('renders stock_state indicator for each product', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakeProducts, meta: {} },
    })

    const wrapper = mountAdminProducts(pinia)
    await flushPromises()

    expect(wrapper.text()).toContain('En Stock')
    expect(wrapper.text()).toContain('Últimas unidades')
    expect(wrapper.text()).toContain('Agotado')
  })

  it('each product row has an edit button', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakeProducts, meta: {} },
    })

    const wrapper = mountAdminProducts(pinia)
    await flushPromises()

    const editButtons = wrapper.findAll('[data-edit-btn]')
    expect(editButtons).toHaveLength(fakeProducts.length)
  })

  it('each product row has a delete button', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakeProducts, meta: {} },
    })

    const wrapper = mountAdminProducts(pinia)
    await flushPromises()

    const deleteButtons = wrapper.findAll('[data-delete-btn]')
    expect(deleteButtons).toHaveLength(fakeProducts.length)
  })

  it('calls deleteProduct from store when delete is confirmed', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakeProducts, meta: {} },
    })
    // After delete, re-fetch returns shortened list
    api.get.mockResolvedValueOnce({
      data: { data: [fakeProducts[1], fakeProducts[2]], meta: {} },
    })
    api.delete.mockResolvedValueOnce({})

    vi.spyOn(window, 'confirm').mockReturnValue(true)

    const wrapper = mountAdminProducts(pinia)
    await flushPromises()

    const store = useProductsStore()
    const deleteSpy = vi.spyOn(store, 'deleteProduct').mockResolvedValue()

    const deleteButtons = wrapper.findAll('[data-delete-btn]')
    await deleteButtons[0].trigger('click')
    await flushPromises()

    expect(deleteSpy).toHaveBeenCalledWith(fakeProducts[0].id)
  })

  it('does NOT call deleteProduct when confirm is cancelled', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakeProducts, meta: {} },
    })

    vi.spyOn(window, 'confirm').mockReturnValue(false)

    const wrapper = mountAdminProducts(pinia)
    await flushPromises()

    const store = useProductsStore()
    const deleteSpy = vi.spyOn(store, 'deleteProduct')

    const deleteButtons = wrapper.findAll('[data-delete-btn]')
    await deleteButtons[0].trigger('click')
    await flushPromises()

    expect(deleteSpy).not.toHaveBeenCalled()
  })

  it('shows empty state when no products', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: [], meta: {} },
    })

    const wrapper = mountAdminProducts(pinia)
    await flushPromises()

    // Should show some empty-state message
    expect(wrapper.find('[data-empty-state]').exists()).toBe(true)
  })
})
