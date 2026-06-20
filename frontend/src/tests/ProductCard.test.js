import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { setActivePinia, createPinia } from 'pinia'
import ProductCard from '../components/catalog/ProductCard.vue'

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

// ---------------------------------------------------------------------------
// Minimal router — ProductCard uses RouterLink to product detail
// ---------------------------------------------------------------------------
const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div/>' } }],
})

let pinia

function mountCard(product) {
  return mount(ProductCard, {
    props: { product },
    global: { plugins: [pinia, router] },
  })
}

const baseProduct = {
  id: 1,
  title: 'Master Palette',
  slug: 'master-palette',
  description: 'Una paleta profesional de sombras.',
  price: '120.00',
  stock_qty: 10,
  stock_state: 'En Stock',
  thumbnail: 'https://example.com/thumb.jpg',
  category: { id: 1, name: 'Paletas', slug: 'paletas' },
}

describe('ProductCard.vue', () => {
  beforeEach(() => {
    localStorage.clear()
    pinia = createPinia()
    setActivePinia(pinia)
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('renders the product title', () => {
    const wrapper = mountCard(baseProduct)
    expect(wrapper.text()).toContain('Master Palette')
  })

  it('renders the formatted price', () => {
    const wrapper = mountCard(baseProduct)
    expect(wrapper.text()).toContain('$120.00')
  })

  it('renders the stock_state badge', () => {
    const wrapper = mountCard(baseProduct)
    expect(wrapper.text()).toContain('En Stock')
  })

  it('renders "Agotado" stock badge for out-of-stock products', () => {
    const wrapper = mountCard({ ...baseProduct, stock_qty: 0, stock_state: 'Agotado' })
    expect(wrapper.text()).toContain('Agotado')
  })

  it('renders "Últimas unidades" for low-stock products', () => {
    const wrapper = mountCard({ ...baseProduct, stock_qty: 3, stock_state: 'Últimas unidades' })
    expect(wrapper.text()).toContain('Últimas unidades')
  })

  it('renders the category name when category is present', () => {
    const wrapper = mountCard(baseProduct)
    expect(wrapper.text()).toContain('Paletas')
  })

  it('does not render category badge when category is null', () => {
    const wrapper = mountCard({ ...baseProduct, category: null })
    const categoryPills = wrapper.findAll('[data-category-pill]')
    expect(categoryPills).toHaveLength(0)
  })

  it('renders thumbnail image with correct src and alt', () => {
    const wrapper = mountCard(baseProduct)
    const img = wrapper.find('img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://example.com/thumb.jpg')
    expect(img.attributes('alt')).toBe('Master Palette')
  })

  it('renders SVG fallback when thumbnail is null', () => {
    const wrapper = mountCard({ ...baseProduct, thumbnail: null })
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('svg').exists()).toBe(true)
  })

  it('contains a link to /products/{slug}', () => {
    const wrapper = mountCard(baseProduct)
    const link = wrapper.find('a[href*="master-palette"]')
    expect(link.exists()).toBe(true)
  })

  it('renders "Ver Detalles" CTA text', () => {
    const wrapper = mountCard(baseProduct)
    expect(wrapper.text()).toContain('Ver Detalles')
  })

  it('renders description text directly from backend data', () => {
    const wrapper = mountCard(baseProduct)
    expect(wrapper.text()).toContain('Una paleta profesional de sombras.')
  })

  it('renders an add-to-cart button for in-stock products', () => {
    const wrapper = mountCard(baseProduct)
    expect(wrapper.find('[data-add-to-cart]').exists()).toBe(true)
  })

  it('add-to-cart button is disabled for out-of-stock products', () => {
    const wrapper = mountCard({ ...baseProduct, stock_qty: 0, stock_state: 'Agotado' })
    const btn = wrapper.find('[data-add-to-cart]')
    expect(btn.exists()).toBe(true)
    expect(btn.attributes('disabled')).toBeDefined()
  })

  it('add-to-cart button adds item to cart on click', async () => {
    const { useCartStore } = await import('../stores/cart.js')
    const store = useCartStore()
    const wrapper = mountCard(baseProduct)
    await wrapper.find('[data-add-to-cart]').trigger('click')
    expect(store.count).toBe(1)
  })
})
