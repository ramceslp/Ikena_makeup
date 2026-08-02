import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ReceivableBuckets from '../components/admin/reports/ReceivableBuckets.vue'

function buckets(overrides = {}) {
  return {
    bucketA: { count: 1, outstanding_cents: 8000 },
    bucketB: { count: 1, outstanding_cents: 6000 },
    bucketC: { count: 1, outstanding_cents: 6000 },
    totalReceivableCents: 20000,
    projectionCents: 12000,
    ...overrides,
  }
}

describe('ReceivableBuckets.vue', () => {
  it('renders the A/B/C bucket totals with their outstanding amounts', () => {
    const wrapper = mount(ReceivableBuckets, { props: buckets() })

    expect(wrapper.find('[data-receivable-bucket-a]').text()).toContain('$80.00')
    expect(wrapper.find('[data-receivable-bucket-b]').text()).toContain('$60.00')
    expect(wrapper.find('[data-receivable-bucket-c]').text()).toContain('$60.00')
  })

  it('shows Total (A+B+C) and Projection (B+C) as two distinct figures with an explanation', () => {
    const wrapper = mount(ReceivableBuckets, { props: buckets() })

    const total = wrapper.find('[data-receivable-total]')
    const projection = wrapper.find('[data-receivable-projection]')

    expect(total.text()).toContain('$200.00')
    expect(projection.text()).toContain('$120.00')
    // Bucket A (8000) is excluded from the projection — the two figures must
    // never be interchangeable renderings of the same number.
    expect(total.text()).not.toContain('$120.00')
    expect(projection.text()).not.toContain('$200.00')
    expect(projection.text().toLowerCase()).toMatch(/sin confirmar|no incluye|anticipo/)
  })

  it('shows an empty state when there are no outstanding receivables', () => {
    const wrapper = mount(ReceivableBuckets, {
      props: buckets({
        bucketA: { count: 0, outstanding_cents: 0 },
        bucketB: { count: 0, outstanding_cents: 0 },
        bucketC: { count: 0, outstanding_cents: 0 },
        totalReceivableCents: 0,
        projectionCents: 0,
      }),
    })

    expect(wrapper.text()).toContain('No hay cuentas por cobrar')
    expect(wrapper.find('[data-receivable-total]').exists()).toBe(false)
  })
})
