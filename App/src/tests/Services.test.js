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
import Services from '../views/Services.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/services', component: Services, name: 'services' },
      { path: '/services/:slug', component: { template: '<div/>' }, name: 'service-detail' },
      { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
    ],
  })
}

const fakeServices = [
  { id: 1, slug: 'maquillaje-novia', title: 'Maquillaje de Novia', price: '80.00', thumbnail: null, duration_hours: 2, availability_type: 'by_appointment', category: null, description: 'x' },
]

describe('Services.vue (App) — catalog browse + connectivity [Spec: catalog browses / catalog unreachable]', () => {
  let router

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    router = makeRouter()
    await router.push('/services')
  })

  it('renders services from the API when connectivity is present [Spec: catalog browses successfully]', async () => {
    api.get.mockResolvedValue({ data: { data: fakeServices, meta: { current_page: 1, last_page: 1 } } })

    const wrapper = mount(Services, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-service-card]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Maquillaje de Novia')
    expect(wrapper.find('[data-catalog-error]').exists()).toBe(false)
  })

  it('shows an explicit connectivity error instead of stale/cached data when offline [Spec: catalog unreachable]', async () => {
    const networkError = new Error('Network Error')
    api.get.mockRejectedValue(networkError)

    const wrapper = mount(Services, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-catalog-error]').exists()).toBe(true)
    expect(wrapper.find('[data-catalog-retry]').exists()).toBe(true)
    expect(wrapper.find('[data-service-card]').exists()).toBe(false)
  })

  it('shows a loading indicator while the connectivity probe is pending (no blank screen)', async () => {
    let resolveProbe
    api.get.mockImplementationOnce(
      () => new Promise((resolve) => { resolveProbe = resolve }),
    )
    // Default for the calls made AFTER the probe resolves (fetchCategories,
    // fetchServices) — the probe itself only consumes the `Once` queue entry.
    api.get.mockResolvedValue({ data: { data: fakeServices, meta: {} } })

    const wrapper = mount(Services, { global: { plugins: [router] } })
    await Promise.resolve()
    await Promise.resolve()

    expect(wrapper.find('[data-catalog-checking]').exists()).toBe(true)
    expect(wrapper.find('[data-service-card]').exists()).toBe(false)

    resolveProbe({ data: { data: fakeServices, meta: {} } })
    await flushPromises()

    expect(wrapper.find('[data-catalog-checking]').exists()).toBe(false)
    expect(wrapper.find('[data-service-card]').exists()).toBe(true)
  })

  // [DEFECT 1 fix] ServiceFilters.vue now hides price/availability/sort
  // behind a "Filtros" toggle. Collapsing is purely visual -- a filter set
  // while the panel is open must still reach the store/API.
  it('a filter hidden behind the "Filtros" toggle still reaches the API once the panel is opened and changed', async () => {
    api.get.mockResolvedValue({ data: { data: fakeServices, meta: { current_page: 1, last_page: 1 } } })

    const wrapper = mount(Services, { global: { plugins: [router] } })
    await flushPromises()

    await wrapper.find('[data-filters-toggle]').trigger('click')
    await wrapper.find('[data-availability]').setValue('immediate')
    await flushPromises()

    const lastCall = api.get.mock.calls.at(-1)
    expect(lastCall[0]).toBe('/services')
    expect(lastCall[1].params).toMatchObject({ availability_type: 'immediate' })
  })
})
