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
import LedgerTable from '../components/admin/reports/LedgerTable.vue'

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

function ledgerResponse(overrides = {}) {
  return {
    data: {
      data: [
        { occurred_at: '2026-07-05T10:00:00-05:00', amount_cents: 9900, stream: 'course_sale', label: 'Venta de curso', counterparty: 'Ana Buyer' },
      ],
      meta: { current_page: 1, last_page: 1, total: 1 },
      ...overrides,
    },
  }
}

function mockReportResponses() {
  api.get.mockResolvedValueOnce(summaryResponse())
  api.get.mockResolvedValueOnce(timelineResponse())
  api.get.mockResolvedValueOnce(compositionResponse())
  api.get.mockResolvedValueOnce(ledgerResponse())
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

  it('fetches summary, timeline, composition, and the ledger on mount', async () => {
    mockReportResponses()

    await mountAdminReports()
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/reports/summary', { params: expect.any(Object) })
    expect(api.get).toHaveBeenCalledWith('/admin/reports/timeline', { params: expect.any(Object) })
    expect(api.get).toHaveBeenCalledWith('/admin/reports/composition', { params: expect.any(Object) })
    expect(api.get).toHaveBeenCalledWith('/admin/reports/ledger', { params: expect.any(Object) })
  })

  it('renders confirmed revenue and retained deposits as separate KPI lines', async () => {
    mockReportResponses()

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
    api.get.mockResolvedValueOnce(ledgerResponse())

    const wrapper = await mountAdminReports()
    await flushPromises()

    expect(wrapper.find('[data-degradation-notice]').exists()).toBe(true)
  })

  it('re-fetches with new params when the granularity filter changes', async () => {
    mockReportResponses()

    const wrapper = await mountAdminReports()
    await flushPromises()

    mockReportResponses()

    await wrapper.find('[data-filter-granularity]').setValue('week')
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/reports/composition', {
      params: expect.objectContaining({ granularity: 'week' }),
    })
  })

  it('re-fetches only the ledger when its page changes, not the three aggregates', async () => {
    mockReportResponses()

    const wrapper = await mountAdminReports()
    await flushPromises()

    api.get.mockClear()
    api.get.mockResolvedValueOnce(ledgerResponse({ meta: { current_page: 2, last_page: 2, total: 20 } }))

    wrapper.findComponent(LedgerTable).vm.$emit('update:page', 2)
    await flushPromises()

    // Paging is a ledger-only concern — the aggregates depend on the date
    // range alone, so re-running them here would be three wasted round trips
    // per page click on the one screen built for browsing.
    expect(api.get).toHaveBeenCalledTimes(1)
    expect(api.get).toHaveBeenCalledWith('/admin/reports/ledger', {
      params: expect.objectContaining({ page: 2 }),
    })
  })

  it('renders the sales ledger with rows from the ledger endpoint', async () => {
    mockReportResponses()

    const wrapper = await mountAdminReports()
    await flushPromises()

    expect(wrapper.text()).toContain('Venta de curso')
    expect(wrapper.text()).toContain('Ana Buyer')
  })
})
