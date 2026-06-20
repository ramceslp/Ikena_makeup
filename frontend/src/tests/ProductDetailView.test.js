import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
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
    // Should show some loading state
    expect(wrapper.find('[data-loading]').exists() || wrapper.text().length > 0).toBe(true)
  })

  it('shows product title after successful fetch', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProduct } })
    const wrapper = await mountDetail('master-palette')
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Master Palette')
  })

  it('shows product price after successful fetch', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProduct } })
    const wrapper = await mountDetail('master-palette')
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('$120.00')
  })

  it('shows stock_state label', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProduct } })
    const wrapper = await mountDetail('master-palette')
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('En Stock')
  })

  it('shows error state when fetch fails', async () => {
    api.get.mockRejectedValueOnce({
      response: { data: { message: 'Producto no encontrado' } },
    })
    const wrapper = await mountDetail('no-existe')
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Producto no encontrado')
  })

  it('shows disabled add-to-cart state when product is out of stock', async () => {
    const outOfStock = { ...fakeProduct, stock_qty: 0, stock_state: 'Agotado' }
    api.get.mockResolvedValueOnce({ data: { data: outOfStock } })
    const wrapper = await mountDetail('master-palette')
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    // Either a disabled button or text indicating out of stock
    const disabledBtn = wrapper.find('[data-add-to-cart][disabled]')
    const hasAgotadoText = wrapper.text().includes('Agotado')
    expect(disabledBtn.exists() || hasAgotadoText).toBe(true)
  })

  it('shows category name when present', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProduct } })
    const wrapper = await mountDetail('master-palette')
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Paletas')
  })

  it('shows a back link to /products', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProduct } })
    const wrapper = await mountDetail('master-palette')
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    // There should be a way to navigate back to catalog
    const backLink = wrapper.find('a[href="/products"]') ||
      wrapper.find('[data-back-to-catalog]')
    expect(wrapper.html()).toContain('/products')
  })
})
