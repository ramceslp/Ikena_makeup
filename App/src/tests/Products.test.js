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
import Products from '../views/Products.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/products', component: Products, name: 'products' },
      { path: '/products/:slug', component: { template: '<div/>' }, name: 'product-detail' },
      { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
    ],
  })
}

const fakeProducts = [
  { id: 1, slug: 'paleta-editorial', title: 'Paleta Editorial', price: '45.00', thumbnail: null, stock_state: 'En Stock', category: null, description: 'x' },
]

describe('Products.vue (App) — catalog browse + connectivity [Spec: catalog browses / catalog unreachable]', () => {
  let router

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    router = makeRouter()
    await router.push('/products')
  })

  it('renders products from the API when connectivity is present [Spec: catalog browses successfully]', async () => {
    api.get.mockResolvedValue({ data: { data: fakeProducts, meta: { current_page: 1, last_page: 1 } } })

    const wrapper = mount(Products, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-product-card]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Paleta Editorial')
    expect(wrapper.find('[data-catalog-error]').exists()).toBe(false)
  })

  it('shows an explicit connectivity error instead of stale/cached data when offline [Spec: catalog unreachable]', async () => {
    const networkError = new Error('Network Error')
    api.get.mockRejectedValue(networkError)

    const wrapper = mount(Products, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-catalog-error]').exists()).toBe(true)
    expect(wrapper.find('[data-catalog-retry]').exists()).toBe(true)
    expect(wrapper.find('[data-product-card]').exists()).toBe(false)
  })

  it('shows a loading indicator while the connectivity probe is pending (no blank screen)', async () => {
    let resolveProbe
    api.get.mockImplementationOnce(
      () => new Promise((resolve) => { resolveProbe = resolve }),
    )
    // Default for the calls made AFTER the probe resolves (fetchCategories,
    // fetchProducts) — the probe itself only consumes the `Once` queue entry.
    api.get.mockResolvedValue({ data: { data: fakeProducts, meta: {} } })

    const wrapper = mount(Products, { global: { plugins: [router] } })
    await Promise.resolve()
    await Promise.resolve()

    expect(wrapper.find('[data-catalog-checking]').exists()).toBe(true)
    expect(wrapper.find('[data-catalog-error]').exists()).toBe(false)
    expect(wrapper.find('[data-product-card]').exists()).toBe(false)

    resolveProbe({ data: { data: fakeProducts, meta: {} } })
    await flushPromises()

    expect(wrapper.find('[data-catalog-checking]').exists()).toBe(false)
    expect(wrapper.find('[data-product-card]').exists()).toBe(true)
  })

  it('does NOT show the connectivity error for a real server error response (server reachable)', async () => {
    const serverError = new Error('Internal Server Error')
    serverError.response = { status: 500 }
    api.get.mockRejectedValue(serverError)

    const wrapper = mount(Products, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-catalog-error]').exists()).toBe(false)
  })

  it('tapping retry re-checks connectivity and renders the catalog on success', async () => {
    api.get.mockRejectedValueOnce(new Error('Network Error'))

    const wrapper = mount(Products, { global: { plugins: [router] } })
    await flushPromises()
    expect(wrapper.find('[data-catalog-error]').exists()).toBe(true)

    api.get.mockResolvedValue({ data: { data: fakeProducts, meta: {} } })
    await wrapper.find('[data-catalog-retry]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-catalog-error]').exists()).toBe(false)
    expect(wrapper.find('[data-product-card]').exists()).toBe(true)
  })
})
