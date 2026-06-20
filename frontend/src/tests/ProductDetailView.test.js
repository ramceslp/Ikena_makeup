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
import ProductDetail from '../views/ProductDetail.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/products/:slug', name: 'ProductDetail', component: ProductDetail },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

async function mountDetail(slug = 'master-palette') {
  await router.push(`/products/${slug}`)
  await router.isReady()
  return mount(ProductDetail, {
    global: { plugins: [router] },
  })
}

const fakeProduct = {
  id: 1,
  title: 'Master Palette',
  slug: 'master-palette',
  description: 'Una paleta profesional completa con 24 sombras.',
  price: '120.00',
  stock_qty: 10,
  stock_state: 'En Stock',
  thumbnail: null,
  images: [
    { id: 1, url: 'https://example.com/img1.jpg', sort_order: 0 },
  ],
  category: { id: 1, name: 'Paletas', slug: 'paletas' },
}

describe('ProductDetail.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('shows a loading indicator while fetching', async () => {
    // Promise that never resolves → loading stays true
    api.get.mockReturnValueOnce(new Promise(() => {}))
    const wrapper = await mountDetail()
    // The [data-loading] element must be present while the request is pending
    expect(wrapper.find('[data-loading]').exists()).toBe(true)
  })

  it('shows product title after successful fetch', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProduct } })
    const wrapper = await mountDetail('master-palette')
    await flushPromises()
    expect(wrapper.text()).toContain('Master Palette')
  })

  it('shows product price after successful fetch', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProduct } })
    const wrapper = await mountDetail('master-palette')
    await flushPromises()
    expect(wrapper.text()).toContain('$120.00')
  })

  it('shows stock_state label', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProduct } })
    const wrapper = await mountDetail('master-palette')
    await flushPromises()
    expect(wrapper.text()).toContain('En Stock')
  })

  it('shows error state when fetch fails', async () => {
    api.get.mockRejectedValueOnce({
      response: { data: { message: 'Producto no encontrado' } },
    })
    const wrapper = await mountDetail('no-existe')
    await flushPromises()
    expect(wrapper.text()).toContain('Producto no encontrado')
  })

  it('shows "Agotado" stock badge when product is out of stock', async () => {
    const outOfStock = { ...fakeProduct, stock_qty: 0, stock_state: 'Agotado' }
    api.get.mockResolvedValueOnce({ data: { data: outOfStock } })
    const wrapper = await mountDetail('master-palette')
    await flushPromises()
    expect(wrapper.find('[data-add-to-cart]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Agotado')
  })

  it('shows category name when present', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProduct } })
    const wrapper = await mountDetail('master-palette')
    await flushPromises()
    expect(wrapper.text()).toContain('Paletas')
  })

  it('shows a RouterLink back to /products', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProduct } })
    const wrapper = await mountDetail('master-palette')
    await flushPromises()
    const backLink = wrapper.find('[data-back-to-catalog]')
    expect(backLink.exists()).toBe(true)
    expect(wrapper.html()).toContain('/products')
  })
})
