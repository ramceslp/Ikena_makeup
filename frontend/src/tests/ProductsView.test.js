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
import Products from '../views/Products.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div/>' } }],
})

function mountProducts() {
  return mount(Products, {
    global: {
      plugins: [router],
    },
  })
}

describe('Products.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    // Default: return empty list
    api.get.mockResolvedValue({ data: { data: [], meta: {} } })
  })

  it('renders the page heading in Spanish', async () => {
    const wrapper = mountProducts()
    expect(wrapper.text()).toMatch(/producto|catálogo/i)
  })

  it('renders ProductFilters component (contains stock filter)', async () => {
    const wrapper = mountProducts()
    // ProductFilters renders [data-stock-filter] and category pills
    expect(wrapper.find('[data-stock-filter]').exists()).toBe(true)
  })

  it('calls fetchProducts and fetchCategories on mount', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: {} } })
    const wrapper = mountProducts()
    await wrapper.vm.$nextTick()
    // fetchCategories and fetchProducts both GET
    expect(api.get).toHaveBeenCalled()
  })

  it('passes products from store to ProductCatalog', async () => {
    const fakeProducts = [
      {
        id: 1,
        title: 'Master Palette',
        slug: 'master-palette',
        price: '120.00',
        stock_qty: 10,
        stock_state: 'En Stock',
        thumbnail: null,
        category: null,
      },
    ]
    // fetchCategories is called first (GET /categories) → returns empty
    // fetchProducts is called second (GET /products) → returns fakeProducts
    api.get.mockResolvedValueOnce({ data: { data: [] } }) // categories
    api.get.mockResolvedValueOnce({ data: { data: fakeProducts, meta: { current_page: 1, last_page: 1, total: 1 } } }) // products

    const wrapper = mountProducts()
    // Wait for all pending promises to settle
    await new Promise(resolve => setTimeout(resolve, 50))
    await wrapper.vm.$nextTick()

    // After data loads, product title should appear
    expect(wrapper.text()).toContain('Master Palette')
  })
})
