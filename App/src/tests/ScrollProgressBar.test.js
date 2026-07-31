/**
 * Port of frontend/src/tests/ScrollProgressBar.test.js for App/'s TopAppBar.
 *
 * The bar rides the bottom edge of the sticky top bar and fills as the page
 * scrolls. Pure CSS — `animation-timeline: scroll(root)` — so there is nothing
 * to trigger and no listener to leak.
 *
 * Anchoring it to the bar rather than to the viewport is what keeps it clear of
 * the notch: index.html carries `viewport-fit=cover`, so a bar fixed at top 0
 * would render underneath the status bar.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

import TopAppBar from '../components/layout/TopAppBar.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', component: { template: '<div/>' }, name: 'home' },
    { path: '/cart', component: { template: '<div/>' }, name: 'cart' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

let wrapper

beforeEach(async () => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  await router.push('/')
  wrapper = mount(TopAppBar, { global: { plugins: [router] } })
})

describe('TopAppBar.vue — reading progress bar', () => {
  it('renders the bar inside the top bar', () => {
    const bar = wrapper.find('[data-scroll-progress]')

    expect(bar.exists()).toBe(true)
    expect(bar.classes()).toContain('barra')
  })

  it('keeps the bar out of the accessibility tree', () => {
    expect(wrapper.find('[data-scroll-progress]').attributes('aria-hidden')).toBe('true')
  })

  /**
   * `position: sticky` already establishes the containing block the bar is
   * absolutely positioned against, and it is also what supplies the safe-area
   * padding it hangs below. Swapping it for `relative` would cancel the
   * stickiness and drop the bar under the notch.
   */
  it('anchors the bar to a top bar that is still sticky and still padded', () => {
    const header = wrapper.find('[data-app-topbar]')

    expect(header.classes()).toContain('sticky')
    expect(header.classes()).toContain('app-topbar')
    expect(header.classes()).not.toContain('relative')
  })

  it('keeps the host clear of the overflow that would freeze the timeline', () => {
    const header = wrapper.find('[data-app-topbar]')

    expect(header.classes()).not.toContain('overflow-hidden')
    expect(header.classes()).not.toContain('overflow-auto')
  })
})
