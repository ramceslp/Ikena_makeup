import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import ProductCatalog from '../components/catalog/ProductCatalog.vue'

// ---------------------------------------------------------------------------
// Minimal router — ProductCatalog renders ProductCard which uses RouterLink
// ---------------------------------------------------------------------------
const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div/>' } }],
})

function mountCatalog(props = {}) {
  return mount(ProductCatalog, {
    props,
    global: { plugins: [router] },
  })
}

const fakeProducts = [
  {
    id: 1,
    title: 'Master Palette',
    slug: 'master-palette',
    price: '120.00',
    stock_qty: 10,
    stock_state: 'En Stock',
    thumbnail: null,
    category: { id: 1, name: 'Paletas', slug: 'paletas' },
  },
  {
    id: 2,
    title: 'Pro Brush Set',
    slug: 'pro-brush-set',
    price: '50.00',
    stock_qty: 0,
    stock_state: 'Agotado',
    thumbnail: 'https://example.com/brush.jpg',
    category: null,
  },
]

describe('ProductCatalog.vue', () => {
  it('renders product cards when products are provided', () => {
    const wrapper = mountCatalog({ products: fakeProducts })
    expect(wrapper.text()).toContain('Master Palette')
    expect(wrapper.text()).toContain('Pro Brush Set')
  })

  it('renders loading skeleton when loading is true', () => {
    const wrapper = mountCatalog({ products: [], loading: true })
    // Should render skeleton placeholders, not product content
    expect(wrapper.text()).not.toContain('Master Palette')
    // Should have skeleton elements
    const skeletons = wrapper.findAll('[data-skeleton]')
    expect(skeletons.length).toBeGreaterThan(0)
  })

  it('renders error state when error is provided', () => {
    const wrapper = mountCatalog({ products: [], error: 'Error al cargar' })
    expect(wrapper.text()).toContain('Error al cargar')
  })

  it('emits retry when error retry button is clicked', async () => {
    const wrapper = mountCatalog({ products: [], error: 'Error al cargar' })
    const retryBtn = wrapper.find('[data-retry]')
    expect(retryBtn.exists()).toBe(true)
    await retryBtn.trigger('click')
    expect(wrapper.emitted('retry')).toBeTruthy()
  })

  it('renders empty state when no products and not loading', () => {
    const wrapper = mountCatalog({ products: [] })
    expect(wrapper.text()).toContain('No se encontraron productos')
  })

  it('renders pagination when meta has more than 1 page', () => {
    const meta = { current_page: 1, last_page: 3, total: 36 }
    const wrapper = mountCatalog({ products: fakeProducts, meta })
    // Should show page info
    expect(wrapper.text()).toContain('1')
    expect(wrapper.text()).toContain('3')
  })

  it('does not render pagination when meta has only 1 page', () => {
    const meta = { current_page: 1, last_page: 1, total: 2 }
    const wrapper = mountCatalog({ products: fakeProducts, meta })
    const prevBtn = wrapper.find('[data-page-prev]')
    expect(prevBtn.exists()).toBe(false)
  })

  it('emits page-change with next page on next button click', async () => {
    const meta = { current_page: 1, last_page: 3, total: 36 }
    const wrapper = mountCatalog({ products: fakeProducts, meta })
    const nextBtn = wrapper.find('[data-page-next]')
    expect(nextBtn.exists()).toBe(true)
    await nextBtn.trigger('click')
    const emitted = wrapper.emitted('page-change')
    expect(emitted).toBeTruthy()
    expect(emitted[0]).toEqual([2])
  })

  it('emits page-change with previous page on prev button click', async () => {
    const meta = { current_page: 2, last_page: 3, total: 36 }
    const wrapper = mountCatalog({ products: fakeProducts, meta })
    const prevBtn = wrapper.find('[data-page-prev]')
    expect(prevBtn.exists()).toBe(true)
    await prevBtn.trigger('click')
    const emitted = wrapper.emitted('page-change')
    expect(emitted).toBeTruthy()
    expect(emitted[0]).toEqual([1])
  })

  it('prev button is disabled on the first page', () => {
    const meta = { current_page: 1, last_page: 3, total: 36 }
    const wrapper = mountCatalog({ products: fakeProducts, meta })
    const prevBtn = wrapper.find('[data-page-prev]')
    expect(prevBtn.attributes('disabled')).toBeDefined()
  })

  it('next button is disabled on the last page', () => {
    const meta = { current_page: 3, last_page: 3, total: 36 }
    const wrapper = mountCatalog({ products: fakeProducts, meta })
    const nextBtn = wrapper.find('[data-page-next]')
    expect(nextBtn.attributes('disabled')).toBeDefined()
  })
})
