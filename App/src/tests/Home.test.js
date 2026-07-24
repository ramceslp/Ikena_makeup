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
import Home from '../views/Home.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: Home, name: 'home' },
      { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
    ],
  })
}

describe('Home.vue (App) — live sections + offline retry/error state', () => {
  let router

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    router = makeRouter()
    await router.push('/')
  })

  it('renders all live dashboard sections when the API is reachable [Spec: Home loads successfully]', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: {} } })

    const wrapper = mount(Home, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-featured-news-hero]').exists()).toBe(true)
    expect(wrapper.find('[data-latest-news-grid]').exists()).toBe(true)
    expect(wrapper.find('[data-featured-courses]').exists()).toBe(true)
    expect(wrapper.find('[data-featured-services]').exists()).toBe(true)
    expect(wrapper.find('[data-featured-products]').exists()).toBe(true)
    expect(wrapper.find('[data-home-error]').exists()).toBe(false)
  })

  it('shows a retry/error state instead of a blank/crashed screen when there is no connectivity [Spec: Home load fails offline]', async () => {
    const networkError = new Error('Network Error')
    // No `.response` -- axios's shape for a request that never reached a
    // server (DNS failure, connection refused, airplane mode).
    api.get.mockRejectedValue(networkError)

    const wrapper = mount(Home, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-home-error]').exists()).toBe(true)
    expect(wrapper.find('[data-home-retry]').exists()).toBe(true)
    expect(wrapper.find('[data-featured-news-hero]').exists()).toBe(false)
  })

  it('tapping retry re-attempts the connectivity check and renders the dashboard on success', async () => {
    const networkError = new Error('Network Error')
    api.get.mockRejectedValueOnce(networkError)

    const wrapper = mount(Home, { global: { plugins: [router] } })
    await flushPromises()
    expect(wrapper.find('[data-home-error]').exists()).toBe(true)

    api.get.mockResolvedValue({ data: { data: [], meta: {} } })
    await wrapper.find('[data-home-retry]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-home-error]').exists()).toBe(false)
    expect(wrapper.find('[data-featured-news-hero]').exists()).toBe(true)
  })

  it('does NOT show the offline error state for a real server error response (server reachable, just erroring)', async () => {
    const serverError = new Error('Internal Server Error')
    serverError.response = { status: 500 }
    api.get.mockRejectedValue(serverError)

    const wrapper = mount(Home, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-home-error]').exists()).toBe(false)
    expect(wrapper.find('[data-featured-news-hero]').exists()).toBe(true)
  })
})
