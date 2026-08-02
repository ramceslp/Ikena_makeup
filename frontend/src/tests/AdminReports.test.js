import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
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
import AdminReports from '../views/admin/AdminReports.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/admin/reportes', name: 'AdminReports', component: { template: '<div/>' } },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

function summaryResponse(overrides = {}) {
  return {
    data: {
      data: {
        from: '2026-07-01',
        to: '2026-07-31',
        requested_granularity: 'day',
        effective_granularity: 'day',
        degraded: false,
        by_stream: {},
        confirmed_revenue_cents: 9900,
        retained_deposits_cents: 2000,
        orders_count: 3,
        free_enrollments_count: 1,
        ...overrides,
      },
    },
  }
}

function timelineResponse(overrides = {}) {
  return {
    data: {
      data: {
        from: '2026-07-01',
        to: '2026-07-31',
        requested_granularity: 'day',
        effective_granularity: 'day',
        degraded: false,
        periods: [{ label: '2026-07-01', from: '2026-07-01T00:00:00-05:00', to: '2026-07-02T00:00:00-05:00', total_cents: 1000, by_stream: {} }],
        ...overrides,
      },
    },
  }
}

function compositionResponse(overrides = {}) {
  return {
    data: {
      data: {
        from: '2026-07-01',
        to: '2026-07-31',
        requested_granularity: 'day',
        effective_granularity: 'day',
        degraded: false,
        by_type: { course: 5000, product: 2900, service: 8000 },
        retained_deposits_cents: 2000,
        total_cents: 17900,
        ...overrides,
      },
    },
  }
}

async function mountAdminReports() {
  await router.push('/admin/reportes')
  await router.isReady()
  return mount(AdminReports, { global: { plugins: [router] } })
}

describe('AdminReports.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches summary, timeline, and composition on mount', async () => {
    api.get.mockResolvedValueOnce(summaryResponse())
    api.get.mockResolvedValueOnce(timelineResponse())
    api.get.mockResolvedValueOnce(compositionResponse())

    await mountAdminReports()
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/reports/summary', { params: expect.any(Object) })
    expect(api.get).toHaveBeenCalledWith('/admin/reports/timeline', { params: expect.any(Object) })
    expect(api.get).toHaveBeenCalledWith('/admin/reports/composition', { params: expect.any(Object) })
  })

  it('renders confirmed revenue and retained deposits as separate KPI lines', async () => {
    api.get.mockResolvedValueOnce(summaryResponse())
    api.get.mockResolvedValueOnce(timelineResponse())
    api.get.mockResolvedValueOnce(compositionResponse())

    const wrapper = await mountAdminReports()
    await flushPromises()

    expect(wrapper.text()).toContain('$99.00')
    expect(wrapper.text()).toContain('$20.00')
    expect(wrapper.text()).toContain('retenid')
  })

  it('surfaces the degradation notice when the timeline response is degraded', async () => {
    api.get.mockResolvedValueOnce(summaryResponse())
    api.get.mockResolvedValueOnce(
      timelineResponse({ requested_granularity: 'day', effective_granularity: 'week', degraded: true })
    )
    api.get.mockResolvedValueOnce(compositionResponse())

    const wrapper = await mountAdminReports()
    await flushPromises()

    expect(wrapper.find('[data-degradation-notice]').exists()).toBe(true)
  })

  it('re-fetches with new params when the granularity filter changes', async () => {
    api.get.mockResolvedValueOnce(summaryResponse())
    api.get.mockResolvedValueOnce(timelineResponse())
    api.get.mockResolvedValueOnce(compositionResponse())

    const wrapper = await mountAdminReports()
    await flushPromises()

    api.get.mockResolvedValueOnce(summaryResponse())
    api.get.mockResolvedValueOnce(timelineResponse())
    api.get.mockResolvedValueOnce(compositionResponse())

    await wrapper.find('[data-filter-granularity]').setValue('week')
    await flushPromises()

    expect(api.get).toHaveBeenLastCalledWith('/admin/reports/composition', {
      params: expect.objectContaining({ granularity: 'week' }),
    })
  })
})
