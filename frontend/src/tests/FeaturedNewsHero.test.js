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

    // The outer <section> is always present; the inner content block must be absent.
    expect(wrapper.find('[data-featured-news-hero]').exists()).toBe(true)
    expect(wrapper.find('[data-hero-content]').exists()).toBe(false)
  })

  it('renders the "Leer más" fallback when cta_url is valid but cta_label is null', async () => {
    const postNoLabel = { ...fakePost, cta_label: null, cta_url: 'https://ikena.com/taller' }
    api.get.mockResolvedValueOnce({ data: { data: postNoLabel } })

    const wrapper = mountHero()
    await flushPromises()

    // No empty external anchor; the "Leer más" fallback renders instead.
    expect(wrapper.text()).toContain('Leer más')
    const external = wrapper.findAll('a').find(
      (a) => a.attributes('href') === 'https://ikena.com/taller',
    )
    expect(external).toBeUndefined()
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

  /**
   * The v-else gradient-mesh fallback only tested whether cover_image_url
   * existed, so a URL that failed to LOAD left the hero with neither an
   * image nor its backdrop — just the browser's broken-image glyph across
   * the whole section.
   */
  it('falls back to the gradient when the cover image fails to load', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: { ...fakePost, cover_image_url: 'http://broken.test/x.jpg' } },
    })

    const wrapper = mountHero()
    await flushPromises()

    expect(wrapper.find('[data-hero-cover]').exists()).toBe(true)

    await wrapper.find('[data-hero-cover]').trigger('error')

    expect(wrapper.find('[data-hero-cover]').exists()).toBe(false)
    // The hero still renders its content on the gradient backdrop.
    expect(wrapper.text()).toContain(fakePost.title)
  })

  /**
   * "Velo" scroll-driven parallax (Phase 4).
   *
   * These guard the wiring, not the visual result — jsdom has no layout and no
   * scroll timelines, so the animation itself can only be checked in a browser.
   * What they DO catch is the failure mode that silently kills it.
   */
  describe('velo — scroll-driven hero parallax', () => {
    /**
     * `overflow: hidden` makes an element a scroll container, and
     * `animation-timeline: scroll()/view()` resolves against the NEAREST scroll
     * container. A hidden ancestor therefore freezes the timeline at a constant
     * progress: the animation is running, but never advances. `overflow: clip`
     * clips identically without creating a scroll container.
     *
     * This is easy to reintroduce — `overflow-hidden` is the reflex utility.
     */
    it('clips the hero with overflow-clip so the scroll timeline stays alive', async () => {
      api.get.mockResolvedValueOnce({ data: { data: fakePost } })

      const wrapper = mountHero()
      await flushPromises()

      const section = wrapper.find('[data-featured-news-hero]')
      expect(section.classes()).toContain('overflow-clip')
      expect(section.classes()).not.toContain('overflow-hidden')
    })

    it('clips the cover wrapper with overflow-clip too', async () => {
      api.get.mockResolvedValueOnce({ data: { data: fakePost } })

      const wrapper = mountHero()
      await flushPromises()

      const media = wrapper.find('[data-hero-media]')
      expect(media.exists()).toBe(true)
      expect(media.classes()).toContain('overflow-clip')
      expect(media.classes()).not.toContain('overflow-hidden')
    })

    it('marks the cover image as the parallax subject', async () => {
      api.get.mockResolvedValueOnce({ data: { data: fakePost } })

      const wrapper = mountHero()
      await flushPromises()

      expect(wrapper.find('[data-hero-cover]').classes()).toContain('velo-media')
    })

    it('marks the legibility veil so it can lift on scroll', async () => {
      api.get.mockResolvedValueOnce({ data: { data: fakePost } })

      const wrapper = mountHero()
      await flushPromises()

      expect(wrapper.find('[data-hero-veil]').classes()).toContain('velo-veil')
    })

    /**
     * The gradient fallback already animates via `.makeup-mesh`'s own drift
     * keyframes. Adding velo-media there would put two animations on the same
     * `transform` property and the later one would win outright.
     */
    it('does not apply the parallax to the gradient fallback', async () => {
      api.get.mockResolvedValueOnce({ data: { data: { ...fakePost, cover_image_url: null } } })

      const wrapper = mountHero()
      await flushPromises()

      expect(wrapper.find('.velo-media').exists()).toBe(false)
    })
  })
})
