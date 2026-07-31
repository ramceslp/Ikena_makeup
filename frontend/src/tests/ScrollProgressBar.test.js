/**
 * Tests for the "barra de labios" reading-progress bar in NavBar.vue.
 *
 * It rides the bottom edge of the sticky navbar and fills as the page scrolls,
 * painted with the brand gradient. Pure CSS — `animation-timeline: scroll(root)`
 * — so there is nothing here to trigger and no listener to leak.
 *
 * jsdom has no scroll timelines, so what is verifiable is where the bar lives,
 * that it stays out of the a11y tree, and that its host is not the kind of box
 * that would freeze the timeline against it.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
}))

import NavBar from '../components/NavBar.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', component: { template: '<div/>' }, name: 'Home' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

let wrapper

beforeEach(async () => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  await router.push('/')
  wrapper = mount(NavBar, { global: { plugins: [router] } })
})

describe('NavBar.vue — reading progress bar', () => {
  it('renders the bar inside the navbar', () => {
    const bar = wrapper.find('[data-scroll-progress]')

    expect(bar.exists()).toBe(true)
    expect(bar.classes()).toContain('barra')
  })

  /**
   * It duplicates information the scrollbar already carries, and it has no
   * text, so announcing it would only add noise.
   */
  it('keeps the bar out of the accessibility tree', () => {
    expect(wrapper.find('[data-scroll-progress]').attributes('aria-hidden')).toBe('true')
  })

  /**
   * The bar is absolutely positioned against the navbar. `position: sticky`
   * already establishes that containing block, and adding `relative` would
   * cancel the stickiness outright — so the navbar must stay sticky and must
   * NOT gain `relative`.
   */
  it('anchors the bar to a navbar that is still sticky', () => {
    const nav = wrapper.find('nav')

    expect(nav.classes()).toContain('sticky')
    expect(nav.classes()).not.toContain('relative')
  })

  /**
   * `overflow: hidden` on the host would make it a scroll container, and
   * `scroll(root)` resolves against the nearest scrollport — pinning the bar at
   * a constant width. Same trap as everywhere else on this page.
   */
  it('keeps the host clear of the overflow that would freeze the timeline', () => {
    const nav = wrapper.find('nav')

    expect(nav.classes()).not.toContain('overflow-hidden')
    expect(nav.classes()).not.toContain('overflow-auto')
  })
})
