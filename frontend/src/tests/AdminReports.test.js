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
import RankingTable from '../components/admin/reports/RankingTable.vue'
import FunnelChart from '../components/admin/reports/FunnelChart.vue'
import ReceivableBuckets from '../components/admin/reports/ReceivableBuckets.vue'

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

const rankingMeta = { from: '2026-07-01', to: '2026-07-31', requested_granularity: 'day', effective_granularity: 'day', degraded: false }

function topProductsResponse(overrides = {}) {
  return {
    data: {
      data: [
        { product_id: 1, title: 'Labial Rosa', quantity: 10, revenue_cents: 50000, known_cost_revenue_cents: 30000, margin_cents: 12000 },
      ],
      meta: rankingMeta,
      ...overrides,
    },
  }
}

function topServicesResponse(overrides = {}) {
  return {
    data: {
      data: [
        { service_id: 1, title: 'Maquillaje de novia', revenue_cents: 80000, duration_hours: 2, revenue_per_hour_cents: 40000 },
      ],
      meta: rankingMeta,
      ...overrides,
    },
  }
}

function topCoursesResponse(overrides = {}) {
  return {
    data: {
      data: [
        { course_id: 1, title: 'Curso de maquillaje', revenue_cents: 60000, paid_enrollment_count: 6, free_enrollment_count: 2 },
      ],
      meta: rankingMeta,
      ...overrides,
    },
  }
}

function funnelResponse(overrides = {}) {
  return {
    data: {
      data: {
        orders: { pending: 1, paid: 3, failed: 0, canceled: 1 },
        appointments: { pending: 1, confirmed: 2, paid: 2, cancelled: 0 },
      },
      meta: rankingMeta,
      ...overrides,
    },
  }
}

function receivablesResponse(overrides = {}) {
  return {
    data: {
      data: {
        bucket_a: { count: 1, outstanding_cents: 8000 },
        bucket_b: { count: 1, outstanding_cents: 6000 },
        bucket_c: { count: 0, outstanding_cents: 0 },
        total_receivable_cents: 14000,
        projection_cents: 6000,
        ...overrides,
      },
    },
  }
}

// Fixed call order fired by AdminReports.vue's onMounted `run([loadAggregates,
// loadLedger, loadReceivables])`: loadAggregates issues its 7 requests
// synchronously inside one Promise.all (summary..funnel), then loadLedger,
// then loadReceivables — see the split-watcher comment in AdminReports.vue.
function mockReportResponses() {
  api.get.mockResolvedValueOnce(summaryResponse())
  api.get.mockResolvedValueOnce(timelineResponse())
  api.get.mockResolvedValueOnce(compositionResponse())
  api.get.mockResolvedValueOnce(topProductsResponse())
  api.get.mockResolvedValueOnce(topServicesResponse())
  api.get.mockResolvedValueOnce(topCoursesResponse())
  api.get.mockResolvedValueOnce(funnelResponse())
  api.get.mockResolvedValueOnce(ledgerResponse())
  api.get.mockResolvedValueOnce(receivablesResponse())
}

// A range/granularity change only re-runs loadAggregates + loadLedger (the
// split-watcher convention) — receivables is deliberately absent, so a
// range-change re-fetch drains 8 queued mocks, not 9. Queuing a 9th
// (unconsumed) response here would leak into the NEXT test, since neither
// `vi.clearAllMocks()` nor `mockClear()` purges queued `mockResolvedValueOnce`
// values — only `mockReset()` does.
function mockRangeChangeResponses() {
  api.get.mockResolvedValueOnce(summaryResponse())
  api.get.mockResolvedValueOnce(timelineResponse())
  api.get.mockResolvedValueOnce(compositionResponse())
  api.get.mockResolvedValueOnce(topProductsResponse())
  api.get.mockResolvedValueOnce(topServicesResponse())
  api.get.mockResolvedValueOnce(topCoursesResponse())
  api.get.mockResolvedValueOnce(funnelResponse())
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
    api.get.mockResolvedValueOnce(topProductsResponse())
    api.get.mockResolvedValueOnce(topServicesResponse())
    api.get.mockResolvedValueOnce(topCoursesResponse())
    api.get.mockResolvedValueOnce(funnelResponse())
    api.get.mockResolvedValueOnce(ledgerResponse())
    api.get.mockResolvedValueOnce(receivablesResponse())

    const wrapper = await mountAdminReports()
    await flushPromises()

    expect(wrapper.find('[data-degradation-notice]').exists()).toBe(true)
  })

  it('re-fetches with new params when the granularity filter changes', async () => {
    mockReportResponses()

    const wrapper = await mountAdminReports()
    await flushPromises()

    mockRangeChangeResponses()

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

  it('fetches rankings, the funnel, and receivables on mount', async () => {
    mockReportResponses()

    await mountAdminReports()
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/reports/rankings/products', { params: expect.any(Object) })
    expect(api.get).toHaveBeenCalledWith('/admin/reports/rankings/services', { params: expect.any(Object) })
    expect(api.get).toHaveBeenCalledWith('/admin/reports/rankings/courses', { params: expect.any(Object) })
    expect(api.get).toHaveBeenCalledWith('/admin/reports/funnel', { params: expect.any(Object) })
    expect(api.get).toHaveBeenCalledWith('/admin/reports/receivables')
  })

  it('renders top products, the status funnel, and receivable buckets', async () => {
    mockReportResponses()

    const wrapper = await mountAdminReports()
    await flushPromises()

    expect(wrapper.text()).toContain('Labial Rosa')
    expect(wrapper.text()).toContain('Embudo de estados')
    expect(wrapper.text()).toContain('Cuentas por cobrar')
  })

  it('does not re-fetch receivables when the date range changes — it is a snapshot of now, not range-scoped', async () => {
    mockReportResponses()

    const wrapper = await mountAdminReports()
    await flushPromises()

    mockRangeChangeResponses()
    await wrapper.find('[data-filter-granularity]').setValue('week')
    await flushPromises()

    const receivablesCalls = api.get.mock.calls.filter(([url]) => url === '/admin/reports/receivables')
    expect(receivablesCalls).toHaveLength(1)
  })

  it('marks the rankings, funnel, and receivables sections as no-print', async () => {
    mockReportResponses()

    const wrapper = await mountAdminReports()
    await flushPromises()

    expect(wrapper.findComponent(RankingTable).classes()).toContain('no-print')
    expect(wrapper.findComponent(FunnelChart).classes()).toContain('no-print')
    expect(wrapper.findComponent(ReceivableBuckets).classes()).toContain('no-print')
  })
})
