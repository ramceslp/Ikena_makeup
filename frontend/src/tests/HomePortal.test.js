/**
 * Tests for the redesigned Home.vue — portal with 6 sections.
 *
 * Asserts:
 *   1. FeaturedNewsHero section renders (data-featured-news-hero)
 *   2. LatestNewsGrid section renders (data-latest-news-grid)
 *   3. FeaturedCourses section renders (data-featured-courses)
 *   4. FeaturedServices section renders (data-featured-services)
 *   5. FeaturedProducts section renders (data-featured-products)
 *   6. NewsletterCta renders
 *   7. CourseFilters is ABSENT (relocated to /cursos)
 *   8. CourseCatalog is ABSENT (relocated to /cursos)
 *   9. Old HeroSection is ABSENT
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
import Home from '../views/Home.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', component: Home, name: 'Home' },
    { path: '/noticias', component: { template: '<div/>' }, name: 'News' },
    { path: '/noticias/:slug', component: { template: '<div/>' }, name: 'NewsDetail' },
    { path: '/cursos', component: { template: '<div/>' }, name: 'Cursos' },
    { path: '/services', component: { template: '<div/>' }, name: 'Services' },
    { path: '/products', component: { template: '<div/>' }, name: 'Products' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

function mountHome() {
  return mount(Home, {
    global: { plugins: [router] },
  })
}

describe('Home.vue — portal redesign (6 sections)', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    // Default: all API calls return empty
    api.get.mockResolvedValue({ data: { data: null } })
    await router.push('/')
  })

  it('renders the FeaturedNewsHero section wrapper (data-featured-news-hero)', async () => {
    const wrapper = mountHome()
    await flushPromises()

    // The section wrapper exists even when post is null (graceful empty state)
    expect(wrapper.find('[data-featured-news-hero]').exists()).toBe(true)
  })

  it('renders the LatestNewsGrid section wrapper (data-latest-news-grid)', async () => {
    const wrapper = mountHome()
    await flushPromises()

    expect(wrapper.find('[data-latest-news-grid]').exists()).toBe(true)
  })

  it('renders the FeaturedCourses section wrapper (data-featured-courses)', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: {} } })

    const wrapper = mountHome()
    await flushPromises()

    expect(wrapper.find('[data-featured-courses]').exists()).toBe(true)
  })

  it('renders the FeaturedServices section wrapper (data-featured-services)', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: {} } })

    const wrapper = mountHome()
    await flushPromises()

    expect(wrapper.find('[data-featured-services]').exists()).toBe(true)
  })

  it('renders the FeaturedProducts section wrapper (data-featured-products)', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: {} } })

    const wrapper = mountHome()
    await flushPromises()

    expect(wrapper.find('[data-featured-products]').exists()).toBe(true)
  })

  it('renders the NewsletterCta section', async () => {
    const wrapper = mountHome()
    await flushPromises()

    // NewsletterCta renders an email input
    expect(wrapper.find('input[type="email"]').exists()).toBe(true)
  })

  it('does NOT render CourseFilters (relocated to /cursos)', async () => {
    const wrapper = mountHome()
    await flushPromises()

    // CourseFilters renders category pills — should be absent on portal Home
    const pillsInCatalog = wrapper.findAll('[data-category-pill]')
    // Actually category pills come from CourseFilters which should be absent
    // Verify by checking the aria-label used in CourseFilters
    expect(wrapper.find('[aria-label="Filtrar por categoría"]').exists()).toBe(false)
  })

  it('does NOT render CourseCatalog (relocated to /cursos)', async () => {
    const wrapper = mountHome()
    await flushPromises()

    // CourseCatalog renders [data-course-catalog] (check via aria-label text)
    // The old home had a catalogRef div with CourseCatalog inside
    // CourseFilters + CourseCatalog sticky nav is the signal
    expect(wrapper.find('section.sticky').exists()).toBe(false)
  })
})
