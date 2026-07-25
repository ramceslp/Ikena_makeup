import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

import api from '../services/api.js'
import ProductDetail from '../views/ProductDetail.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/products', component: { template: '<div/>' }, name: 'products' },
      { path: '/products/:slug', component: ProductDetail, name: 'product-detail' },
      { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
    ],
  })
}

const fakeProduct = {
  id: 1,
  slug: 'paleta-editorial',
  title: 'Paleta Editorial',
  description: 'Una paleta profesional.',
  price: '45.00',
  stock_qty: 5,
  stock_state: 'En Stock',
  images: [],
  category: null,
}

describe('ProductDetail.vue (App) — renders from the API [Spec: catalog browses successfully]', () => {
  let router

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    router = makeRouter()
  })

  it('renders the product fetched from GET /products/:slug', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProduct } })
    await router.push('/products/paleta-editorial')

    const wrapper = mount(ProductDetail, { global: { plugins: [router] } })
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/products/paleta-editorial')
    expect(wrapper.text()).toContain('Paleta Editorial')
    expect(wrapper.text()).toContain('$45.00')
  })

  it('renders an error state (not a crash) when the product cannot be loaded', async () => {
    const notFound = new Error('Not Found')
    notFound.response = { status: 404, data: { message: 'Producto no encontrado' } }
    api.get.mockRejectedValueOnce(notFound)
    await router.push('/products/missing')

    const wrapper = mount(ProductDetail, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toContain('Producto no encontrado')
  })
})
