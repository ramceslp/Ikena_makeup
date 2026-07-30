/**
 * Tests for the "trazo" section headings.
 *
 * The stroke is scroll-scrubbed CSS (`animation-timeline: view()`), so there is
 * no directive and no JavaScript involved — the class is static in the
 * template. These components are mounted WITHOUT registering any directive on
 * purpose: if the class only showed up through `v-reveal.trazo`, every
 * assertion here would fail.
 *
 * jsdom has no layout and no scroll timelines, so what is verifiable is the
 * contract the CSS depends on: the class is present, and nothing between the
 * heading and the section root turns into a scroll container.
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
import LatestNewsGrid from '../components/home/LatestNewsGrid.vue'
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

/**
 * Every ancestor between the heading and the section root that would become a
 * scroll container. `overflow: hidden` is the one that matters: it makes the
 * element a scroll container, and `view()` resolves against the NEAREST one, so
 * progress freezes at a constant and the heading stays half painted for the
 * life of the page. `overflow: clip` clips identically without that.
 */
function scrollContainerAncestors(heading, root) {
  const offenders = []
  let node = heading.parentElement

  while (node) {
    if (node.classList.contains('overflow-hidden')) {
      offenders.push(node.getAttribute('class'))
    }
    if (node === root) break
    node = node.parentElement
  }

  return offenders
}

const SECTIONS = [
  { name: 'FeaturedCourses', component: FeaturedCourses },
  { name: 'FeaturedServices', component: FeaturedServices },
  { name: 'FeaturedProducts', component: FeaturedProducts },
  { name: 'LatestNewsGrid', component: LatestNewsGrid },
  { name: 'NewsletterCta', component: NewsletterCta },
]

async function mountSection(component) {
  const wrapper = mount(component, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  api.get.mockResolvedValue({ data: { data: [] } })
})

describe('trazo — scroll-scrubbed section headings', () => {
  SECTIONS.forEach(({ name, component }) => {
    it(`${name} carries the trazo class without any directive registered`, async () => {
      const wrapper = await mountSection(component)

      expect(wrapper.find('h2').classes()).toContain('trazo')
    })

    it(`${name} keeps the heading clear of any overflow-hidden ancestor`, async () => {
      const wrapper = await mountSection(component)
      const heading = wrapper.find('h2').element

      expect(scrollContainerAncestors(heading, wrapper.element)).toEqual([])
    })
  })

  /**
   * `.trazo` sets `width: fit-content` so the stroke travels the width of the
   * text rather than of the block. Unlike the old directive, the class is now
   * permanent, so a centred heading needs `mx-auto` or it sits left for good.
   */
  it('centres the NewsletterCta heading, whose shrink-to-fit box is permanent', async () => {
    const wrapper = await mountSection(NewsletterCta)

    expect(wrapper.find('h2').classes()).toContain('mx-auto')
  })
})
