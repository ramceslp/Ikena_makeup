/**
 * Tests for FeaturedCourses.vue, FeaturedServices.vue, FeaturedProducts.vue
 * Sections 3, 4, 5 of the portal Home.
 * Each shows 3 most-recent items from their respective store.
 */
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
import FeaturedCourses from '../components/home/FeaturedCourses.vue'
import FeaturedServices from '../components/home/FeaturedServices.vue'
import FeaturedProducts from '../components/home/FeaturedProducts.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/cursos', component: { template: '<div/>' }, name: 'Cursos' },
    { path: '/services', component: { template: '<div/>' }, name: 'Services' },
    { path: '/products', component: { template: '<div/>' }, name: 'Products' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

// ─── FeaturedCourses ────────────────────────────────────────────────────────

const fakeCourses = [
  { id: 1, title: 'Maquillaje Nupcial', slug: 'maquillaje-nupcial', price: '250.00', thumbnail: null, category: null },
  { id: 2, title: 'Técnicas Editoriales', slug: 'tecnicas-editoriales', price: '180.00', thumbnail: null, category: null },
  { id: 3, title: 'Corrección de Color', slug: 'correccion-color', price: '120.00', thumbnail: null, category: null },
]

describe('FeaturedCourses.vue — featured courses section', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders a heading mentioning cursos', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeCourses, meta: { current_page: 1, last_page: 1, total: 3 } } })

    const wrapper = mount(FeaturedCourses, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toMatch(/curso/i)
  })

  it('renders course cards with [data-course-card]', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeCourses, meta: {} } })

    const wrapper = mount(FeaturedCourses, { global: { plugins: [router] } })
    await flushPromises()

    const cards = wrapper.findAll('[data-course-card]')
    expect(cards.length).toBeGreaterThan(0)
  })

  it('renders course titles', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeCourses, meta: {} } })

    const wrapper = mount(FeaturedCourses, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toContain('Maquillaje Nupcial')
  })

  it('renders a "Ver todos" link pointing to /cursos', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeCourses, meta: {} } })

    const wrapper = mount(FeaturedCourses, { global: { plugins: [router] } })
    await flushPromises()

    const links = wrapper.findAll('a')
    const verTodos = links.find(
      (l) => l.text().includes('Ver todos') || l.attributes('href') === '/cursos'
    )
    expect(verTodos).toBeDefined()
  })
})

// ─── FeaturedServices ───────────────────────────────────────────────────────

const fakeServices = [
  { id: 1, title: 'Maquillaje de Novia', slug: 'maquillaje-novia', price: '300.00', thumbnail: null },
  { id: 2, title: 'Maquillaje Artístico', slug: 'maquillaje-artistico', price: '200.00', thumbnail: null },
  { id: 3, title: 'Asesoría de Imagen', slug: 'asesoria-imagen', price: '150.00', thumbnail: null },
]

describe('FeaturedServices.vue — featured services section', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders a heading mentioning servicios', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeServices, meta: {} } })

    const wrapper = mount(FeaturedServices, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toMatch(/servicio/i)
  })

  it('renders service cards with [data-service-card]', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeServices, meta: {} } })

    const wrapper = mount(FeaturedServices, { global: { plugins: [router] } })
    await flushPromises()

    const cards = wrapper.findAll('[data-service-card]')
    expect(cards.length).toBeGreaterThan(0)
  })

  it('renders service titles', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeServices, meta: {} } })

    const wrapper = mount(FeaturedServices, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toContain('Maquillaje de Novia')
  })

  it('renders a link pointing to /services', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeServices, meta: {} } })

    const wrapper = mount(FeaturedServices, { global: { plugins: [router] } })
    await flushPromises()

    const links = wrapper.findAll('a')
    const verTodos = links.find(
      (l) => l.text().includes('Ver todos') || l.attributes('href') === '/services'
    )
    expect(verTodos).toBeDefined()
  })
})

// ─── FeaturedProducts ───────────────────────────────────────────────────────

const fakeProducts = [
  { id: 1, title: 'Paleta Editorial', slug: 'paleta-editorial', price: '120.00', thumbnail: null, stock_state: 'En Stock' },
  { id: 2, title: 'Base Cobertura Total', slug: 'base-cobertura', price: '85.00', thumbnail: null, stock_state: 'En Stock' },
  { id: 3, title: 'Corrector Profesional', slug: 'corrector-pro', price: '65.00', thumbnail: null, stock_state: 'En Stock' },
]

describe('FeaturedProducts.vue — featured products section', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders a heading mentioning productos', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProducts, meta: {} } })

    const wrapper = mount(FeaturedProducts, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toMatch(/producto/i)
  })

  it('renders product cards with [data-product-card]', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProducts, meta: {} } })

    const wrapper = mount(FeaturedProducts, { global: { plugins: [router] } })
    await flushPromises()

    const cards = wrapper.findAll('[data-product-card]')
    expect(cards.length).toBeGreaterThan(0)
  })

  it('renders product titles', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProducts, meta: {} } })

    const wrapper = mount(FeaturedProducts, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toContain('Paleta Editorial')
  })

  it('renders a link pointing to /products', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProducts, meta: {} } })

    const wrapper = mount(FeaturedProducts, { global: { plugins: [router] } })
    await flushPromises()

    const links = wrapper.findAll('a')
    const verTodos = links.find(
      (l) => l.text().includes('Ver todos') || l.attributes('href') === '/products'
    )
    expect(verTodos).toBeDefined()
  })
})
