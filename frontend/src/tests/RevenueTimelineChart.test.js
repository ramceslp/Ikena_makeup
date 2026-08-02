import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import RevenueTimelineChart from '../components/admin/reports/RevenueTimelineChart.vue'

function periodsOf(n, { totalCents = () => 1000 } = {}) {
  return Array.from({ length: n }, (_, i) => ({
    label: `2026-08-${String(i + 1).padStart(2, '0')}`,
    total_cents: totalCents(i),
  }))
}

describe('RevenueTimelineChart.vue', () => {
  it('renders a single bar for N=1 without error', () => {
    const wrapper = mount(RevenueTimelineChart, {
      props: { periods: periodsOf(1), effectiveGranularity: 'day' },
    })

    expect(wrapper.findAll('rect')).toHaveLength(1)
  })

  it('renders 92 bars for N=92 without error', () => {
    const wrapper = mount(RevenueTimelineChart, {
      props: { periods: periodsOf(92), effectiveGranularity: 'day' },
    })

    expect(wrapper.findAll('rect')).toHaveLength(92)
  })

  it('renders an all-zero series as flat baseline bars, not an error', () => {
    const wrapper = mount(RevenueTimelineChart, {
      props: { periods: periodsOf(6, { totalCents: () => 0 }), effectiveGranularity: 'week' },
    })

    expect(wrapper.findAll('rect')).toHaveLength(6)
    expect(wrapper.text()).not.toContain('NaN')
  })

  it('shows an empty-state message when there are no periods', () => {
    const wrapper = mount(RevenueTimelineChart, {
      props: { periods: [], effectiveGranularity: 'day' },
    })

    expect(wrapper.findAll('rect')).toHaveLength(0)
    expect(wrapper.text()).toContain('Sin datos')
  })

  it('exposes an accessible label mentioning the effective granularity', () => {
    const wrapper = mount(RevenueTimelineChart, {
      props: { periods: periodsOf(3), effectiveGranularity: 'month' },
    })

    const svg = wrapper.find('svg')
    expect(svg.attributes('aria-label')).toContain('mes')
  })
})
