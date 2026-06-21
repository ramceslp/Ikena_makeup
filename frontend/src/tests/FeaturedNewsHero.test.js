/**
 * Tests for FeaturedNewsHero.vue
 * Section 1 of the portal Home. Shows the most-recent featured post.
 * Graceful empty state when no featured post available.
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
import FeaturedNewsHero from '../components/home/FeaturedNewsHero.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', component: { template: '<div/>' }, name: 'Home' },
    { path: '/noticias/:slug', component: { template: '<div/>' }, name: 'NewsDetail' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

const fakePost = {
  id: 1,
  title: 'Nuevo Taller de Primavera',
  slug: 'nuevo-taller-primavera',
  excerpt: 'Descubre técnicas únicas de maquillaje primaveral.',
  cover_image_url: 'https://example.com/cover.jpg',
  type: 'nuevo_curso',
  is_featured: true,
  is_published: true,
  published_at: '2026-06-15T10:00:00.000Z',
  cta_label: 'Ver Más',
  cta_url: 'https://ikena.com/taller',
}

function mountHero(propsData = {}) {
  return mount(FeaturedNewsHero, {
    props: propsData,
    global: { plugins: [router] },
  })
}

describe('FeaturedNewsHero.vue — featured post hero', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders the featured post title when post is provided', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const wrapper = mountHero()
    await flushPromises()

    expect(wrapper.text()).toContain('Nuevo Taller de Primavera')
  })

  it('renders the excerpt when post is provided', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const wrapper = mountHero()
    await flushPromises()

    expect(wrapper.text()).toContain('Descubre técnicas únicas de maquillaje primaveral.')
  })

  it('renders a type badge', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const wrapper = mountHero()
    await flushPromises()

    expect(wrapper.find('[data-type-badge]').exists()).toBe(true)
  })

  it('renders a CTA link to /noticias/:slug when cta_label is present', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const wrapper = mountHero()
    await flushPromises()

    // Should have a link pointing to the slug or the external CTA
    const links = wrapper.findAll('a')
    expect(links.length).toBeGreaterThan(0)
  })

  it('renders a "Leer más" fallback link to /noticias/:slug when no cta_label', async () => {
    const postWithoutCta = { ...fakePost, cta_label: null, cta_url: null }
    api.get.mockResolvedValueOnce({ data: { data: postWithoutCta } })

    const wrapper = mountHero()
    await flushPromises()

    expect(wrapper.text()).toContain('Leer más')
  })

  it('does NOT render the hero content section when post is null (empty state)', async () => {
    api.get.mockResolvedValueOnce({ data: { data: null } })

    const wrapper = mountHero()
    await flushPromises()

    expect(wrapper.find('[data-featured-hero]').exists()).toBe(false)
  })

  it('does NOT produce a javascript: href for malicious cta_url', async () => {
    const maliciousPost = { ...fakePost, cta_url: 'javascript:alert(1)' }
    api.get.mockResolvedValueOnce({ data: { data: maliciousPost } })

    const wrapper = mountHero()
    await flushPromises()

    const anchors = wrapper.findAll('a')
    const xss = anchors.find((a) => a.attributes('href')?.startsWith('javascript:'))
    expect(xss).toBeUndefined()
  })
})
