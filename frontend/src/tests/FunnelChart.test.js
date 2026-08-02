import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FunnelChart from '../components/admin/reports/FunnelChart.vue'

describe('FunnelChart.vue', () => {
  it('renders zero-filled status counts for both the order and appointment funnels', () => {
    const wrapper = mount(FunnelChart, {
      props: {
        orders: { pending: 2, paid: 5, failed: 0, canceled: 1 },
        appointments: { pending: 1, confirmed: 3, paid: 4, cancelled: 0 },
      },
    })

    const orderBars = wrapper.findAll('[data-funnel-order-bar]')
    const appointmentBars = wrapper.findAll('[data-funnel-appointment-bar]')
    expect(orderBars).toHaveLength(4)
    expect(appointmentBars).toHaveLength(4)
    expect(wrapper.text()).toContain('5')
    expect(wrapper.text()).toContain('Pendiente')
  })

  it('renders an all-zero order series without dividing by zero', () => {
    const wrapper = mount(FunnelChart, {
      props: { orders: { pending: 0, paid: 0, failed: 0, canceled: 0 }, appointments: {} },
    })

    const orderBars = wrapper.findAll('[data-funnel-order-bar]')
    expect(orderBars).toHaveLength(4)
    for (const bar of orderBars) {
      expect(bar.find('[data-funnel-bar-fill]').attributes('style')).toContain('width: 0%')
    }
  })

  it('shows an empty state for a funnel with no status data at all', () => {
    const wrapper = mount(FunnelChart, {
      props: { orders: { paid: 10 }, appointments: {} },
    })

    expect(wrapper.findAll('[data-funnel-appointment-bar]')).toHaveLength(0)
    expect(wrapper.text()).toContain('Sin datos')
  })

  it('renders a single-status series at full width', () => {
    const wrapper = mount(FunnelChart, {
      props: { orders: { paid: 10 }, appointments: { confirmed: 3 } },
    })

    const orderBars = wrapper.findAll('[data-funnel-order-bar]')
    expect(orderBars).toHaveLength(1)
    expect(wrapper.text()).toContain('10')
    expect(orderBars[0].find('[data-funnel-bar-fill]').attributes('style')).toContain('width: 100%')
  })
})
