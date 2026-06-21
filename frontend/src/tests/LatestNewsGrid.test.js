/**
 * Tests for LatestNewsGrid.vue
 * Section 2 of the portal Home. Shows 3 latest posts.
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
import LatestNewsGrid from '../components/home/LatestNewsGrid.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/noticias', component: { template: '<div/>' }, name: 'News' },
    { path: '/noticias/:slug', component: { template: '<div/>' }, name: 'NewsDetail' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

const fakePosts = [
  {
    id: 1,
    title: 'Novedad Uno',
    slug: 'novedad-uno',
    excerpt: 'Extracto de la primera novedad.',
    cover_image_url: null,
    type: 'noticia',
    cta_label: null,
    cta_url: null,
  },
  {
    id: 2,
    title: 'Novedad Dos',
    slug: 'novedad-dos',
    excerpt: 'Extracto de la segunda novedad.',
    cover_image_url: null,
    type: 'oferta',
    cta_label: 'Ver Oferta',
    cta_url: 'https://ikena.com/oferta',
  },
  {
    id: 3,
    title: 'Novedad Tres',
    slug: 'novedad-tres',
    excerpt: null,
    cover_image_url: null,
    type: 'evento',
    cta_label: null,
    cta_url: null,
  },
]

function mountGrid() {
  return mount(LatestNewsGrid, {
    global: { plugins: [router] },
  })
}

describe('LatestNewsGrid.vue — latest news section', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders section heading "Actualidad & Novedades"', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts } })

    const wrapper = mountGrid()
    await flushPromises()

    expect(wrapper.text()).toContain('Actualidad')
  })

  it('renders a card for each post in the list', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts } })

    const wrapper = mountGrid()
    await flushPromises()

    const cards = wrapper.findAll('[data-news-card]')
    expect(cards).toHaveLength(3)
  })

  it('renders post titles', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts } })

    const wrapper = mountGrid()
    await flushPromises()

    expect(wrapper.text()).toContain('Novedad Uno')
    expect(wrapper.text()).toContain('Novedad Dos')
    expect(wrapper.text()).toContain('Novedad Tres')
  })

  it('renders type badges on each card', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts } })

    const wrapper = mountGrid()
    await flushPromises()

    const badges = wrapper.findAll('[data-type-badge]')
    expect(badges.length).toBeGreaterThanOrEqual(3)
  })

  it('renders a "Ver más noticias" link pointing to /noticias', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts } })

    const wrapper = mountGrid()
    await flushPromises()

    const links = wrapper.findAll('a')
    const seeMore = links.find((l) => l.text().includes('Ver más noticias') || l.attributes('href') === '/noticias')
    expect(seeMore).toBeDefined()
  })

  it('renders empty state when no posts', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mountGrid()
    await flushPromises()

    expect(wrapper.find('[data-news-card]').exists()).toBe(false)
  })
})
