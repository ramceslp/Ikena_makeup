/**
 * Trimmed/adapted port of frontend/src/tests/FeaturedNewsHero.test.js +
 * FeaturedSections.test.js for App/'s ported Home dashboard sections.
 */
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
import FeaturedNewsHero from '../components/home/FeaturedNewsHero.vue'
import LatestNewsGrid from '../components/home/LatestNewsGrid.vue'
import FeaturedCourses from '../components/home/FeaturedCourses.vue'
import FeaturedServices from '../components/home/FeaturedServices.vue'
import FeaturedProducts from '../components/home/FeaturedProducts.vue'
import NewsletterCta from '../components/home/NewsletterCta.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/cursos', component: { template: '<div/>' }, name: 'Cursos' },
    { path: '/services', component: { template: '<div/>' }, name: 'Services' },
    { path: '/products', component: { template: '<div/>' }, name: 'Products' },
    { path: '/noticias', component: { template: '<div/>' }, name: 'News' },
    { path: '/noticias/:slug', component: { template: '<div/>' }, name: 'NewsDetail' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

function mountWithRouter(component) {
  return mount(component, { global: { plugins: [router] } })
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('FeaturedNewsHero.vue (App)', () => {
  it('renders the featured post title fetched from GET /posts/featured', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: { id: 3, title: 'Nueva colección de verano', slug: 'nueva-coleccion', type: 'news', excerpt: null } },
    })

    const wrapper = mountWithRouter(FeaturedNewsHero)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/posts/featured')
    expect(wrapper.find('[data-featured-news-hero]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Nueva colección de verano')
  })

  it('renders the section wrapper (no crash) even when there is no featured post', async () => {
    api.get.mockResolvedValueOnce({ data: { data: null } })

    const wrapper = mountWithRouter(FeaturedNewsHero)
    await flushPromises()

    expect(wrapper.find('[data-featured-news-hero]').exists()).toBe(true)
  })
})

describe('LatestNewsGrid.vue (App)', () => {
  it('renders the latest posts fetched from GET /posts/latest', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: [{ id: 1, title: 'Post Uno', slug: 'post-uno', type: 'news', excerpt: null, cta_url: null, cta_label: null }] },
    })

    const wrapper = mountWithRouter(LatestNewsGrid)
    await flushPromises()

    expect(wrapper.find('[data-news-card]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Post Uno')
  })

  it('shows the empty state when there are no latest posts', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mountWithRouter(LatestNewsGrid)
    await flushPromises()

    expect(wrapper.find('[data-news-empty]').exists()).toBe(true)
  })
})

const fakeCourses = [
  { id: 1, title: 'Maquillaje Nupcial', slug: 'maquillaje-nupcial', price: '250.00', thumbnail: null, category: null },
]

describe('FeaturedCourses.vue (App)', () => {
  it('renders course cards fetched from GET /courses', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeCourses, meta: {} } })

    const wrapper = mountWithRouter(FeaturedCourses)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/courses', { params: { page: 1, per_page: 3, sort: 'newest' } })
    expect(wrapper.find('[data-course-card]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Maquillaje Nupcial')
  })

  it('links each course card to /cursos/:slug and "ver todos" to /cursos [Phase 3 fix: cards previously pointed at the unregistered /courses/:slug]', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeCourses, meta: {} } })

    const wrapper = mountWithRouter(FeaturedCourses)
    await flushPromises()

    expect(wrapper.find('[data-course-card]').attributes('href')).toBe('/cursos/maquillaje-nupcial')
    expect(wrapper.find('a[href="/cursos"]').exists()).toBe(true)
  })
})

const fakeServices = [
  { id: 1, title: 'Maquillaje de Novia', slug: 'maquillaje-novia', price: '300.00', thumbnail: null },
]

describe('FeaturedServices.vue (App)', () => {
  it('renders service cards fetched from GET /services', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeServices, meta: {} } })

    const wrapper = mountWithRouter(FeaturedServices)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/services', { params: { page: 1, per_page: 3, sort: 'newest' } })
    expect(wrapper.find('[data-service-card]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Maquillaje de Novia')
  })
})

const fakeProducts = [
  { id: 1, title: 'Paleta Editorial', slug: 'paleta-editorial', price: '120.00', thumbnail: null, stock_state: 'En Stock' },
]

describe('FeaturedProducts.vue (App)', () => {
  it('renders product cards fetched from GET /products', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeProducts, meta: {} } })

    const wrapper = mountWithRouter(FeaturedProducts)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/products', { params: { page: 1, per_page: 3, sort: 'newest' } })
    expect(wrapper.find('[data-product-card]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Paleta Editorial')
  })

  it('shows a neutral fallback (not a false "$0.00") for a malformed/non-numeric price [shared formatPrice policy]', async () => {
    const malformedPriceProduct = [
      { id: 2, title: 'Base Líquida', slug: 'base-liquida', price: 'not-a-number', thumbnail: null, stock_state: null },
    ]
    api.get.mockResolvedValueOnce({ data: { data: malformedPriceProduct, meta: {} } })

    const wrapper = mountWithRouter(FeaturedProducts)
    await flushPromises()

    expect(wrapper.text()).toContain('Consultar precio')
    expect(wrapper.text()).not.toContain('$0.00')
  })
})

/**
 * A cover_image_url that is present but fails to LOAD is a different case
 * from one that is absent, and both components only handled the second.
 * Seen for real on the emulator: a seeded placeholder host returned 500 for
 * one post while returning 200 for its siblings.
 */
describe('broken cover images (App home)', () => {
  function post(overrides = {}) {
    return {
      id: 1,
      title: 'Nueva colección',
      slug: 'nueva-coleccion',
      excerpt: 'Resumen',
      type: 'noticia',
      cover_image_url: 'http://broken.test/x.jpg',
      cta_label: null,
      cta_url: null,
      published_at: '2026-07-20T10:00:00-05:00',
      ...overrides,
    }
  }

  it('FeaturedNewsHero falls back to the gradient when its cover fails', async () => {
    api.get.mockResolvedValue({ data: { data: post() } })

    const wrapper = mountWithRouter(FeaturedNewsHero)
    await flushPromises()

    expect(wrapper.find('[data-hero-cover]').exists()).toBe(true)
    expect(wrapper.find('[data-hero-gradient]').exists()).toBe(false)

    await wrapper.find('[data-hero-cover]').trigger('error')

    // The v-else gradient only tested whether the URL existed, so before this
    // fix a failed load left the hero with neither an image nor its backdrop.
    expect(wrapper.find('[data-hero-cover]').exists()).toBe(false)
    expect(wrapper.find('[data-hero-gradient]').exists()).toBe(true)
  })

  it('LatestNewsGrid falls back to the placeholder for a card whose cover fails', async () => {
    api.get.mockResolvedValue({ data: { data: [post()] } })

    const wrapper = mountWithRouter(LatestNewsGrid)
    await flushPromises()

    expect(wrapper.find('[data-card-cover]').exists()).toBe(true)

    await wrapper.find('[data-card-cover]').trigger('error')

    expect(wrapper.find('[data-card-cover]').exists()).toBe(false)
    expect(wrapper.find('[data-card-cover-placeholder]').exists()).toBe(true)
  })

  /**
   * The reason failure is tracked per post id rather than with one flag:
   * exactly the situation observed on the emulator, where one placeholder
   * URL 500'd and the others did not.
   */
  it('LatestNewsGrid does not let one broken cover hide the others', async () => {
    api.get.mockResolvedValue({
      data: {
        data: [
          post({ id: 1, slug: 'a', cover_image_url: 'http://broken.test/a.jpg' }),
          post({ id: 2, slug: 'b', cover_image_url: 'http://ok.test/b.jpg' }),
          post({ id: 3, slug: 'c', cover_image_url: 'http://ok.test/c.jpg' }),
        ],
      },
    })

    const wrapper = mountWithRouter(LatestNewsGrid)
    await flushPromises()

    expect(wrapper.findAll('[data-card-cover]')).toHaveLength(3)

    await wrapper.findAll('[data-card-cover]')[0].trigger('error')

    expect(wrapper.findAll('[data-card-cover]')).toHaveLength(2)
    expect(wrapper.findAll('[data-card-cover-placeholder]')).toHaveLength(1)
  })
})

describe('NewsletterCta.vue (App)', () => {
  it('renders an email input and emits subscribe on submit', async () => {
    const wrapper = mount(NewsletterCta)

    await wrapper.find('input[type="email"]').setValue('user@example.com')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('subscribe')).toBeTruthy()
    expect(wrapper.emitted('subscribe')[0]).toEqual(['user@example.com'])
  })
})
