import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PurchaseRow from '../components/profile/PurchaseRow.vue'

const baseOrder = {
  id: 1,
  status: 'paid',
  amount_cents: 4999,
  currency: 'USD',
  paid_at: '2026-01-01T00:00:00Z',
  created_at: '2026-01-01T00:00:00Z',
  course: { id: 1, title: 'Curso de Makeup Pro', slug: 'makeup-pro', thumbnail: null },
}

describe('PurchaseRow.vue', () => {
  it('renders the course title', () => {
    const wrapper = mount(PurchaseRow, { props: { order: baseOrder } })
    expect(wrapper.text()).toContain('Curso de Makeup Pro')
  })

  it('renders the formatted amount', () => {
    const wrapper = mount(PurchaseRow, { props: { order: baseOrder } })
    expect(wrapper.text()).toContain('$49.99')
  })

  it('renders "Pagado" badge for paid status', () => {
    const wrapper = mount(PurchaseRow, { props: { order: baseOrder } })
    expect(wrapper.text()).toContain('Pagado')
  })

  it('renders "Pendiente" badge for pending status', () => {
    const wrapper = mount(PurchaseRow, {
      props: { order: { ...baseOrder, status: 'pending' } },
    })
    expect(wrapper.text()).toContain('Pendiente')
  })
})
