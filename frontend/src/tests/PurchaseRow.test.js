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

const appointmentOrder = {
  id: 2,
  status: 'pending',
  amount_cents: 3000,
  currency: 'USD',
  paid_at: null,
  created_at: '2026-07-01T00:00:00Z',
  appointment: {
    service: { title: 'Maquillaje Social' },
    scheduled_date: '2026-07-04',
    scheduled_time: '10:00',
    deposit_amount_cents: 3000,
    status: 'pending',
  },
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

// ---------------------------------------------------------------------------
// Appointment variant (Phase 14)
// ---------------------------------------------------------------------------

describe('PurchaseRow.vue — appointment variant', () => {
  it('renders service title from appointment', () => {
    const wrapper = mount(PurchaseRow, { props: { order: appointmentOrder } })
    expect(wrapper.text()).toContain('Maquillaje Social')
  })

  it('renders scheduled_date in some visible form', () => {
    const wrapper = mount(PurchaseRow, { props: { order: appointmentOrder } })
    // Date may be formatted — check that it includes the year and day
    expect(wrapper.text()).toMatch(/2026/)
  })

  it('renders scheduled_time', () => {
    const wrapper = mount(PurchaseRow, { props: { order: appointmentOrder } })
    expect(wrapper.text()).toContain('10:00')
  })

  it('renders deposit amount formatted', () => {
    const wrapper = mount(PurchaseRow, { props: { order: appointmentOrder } })
    // deposit_amount_cents=3000 → $30.00
    expect(wrapper.text()).toContain('$30.00')
  })

  it('renders appointment status badge', () => {
    const wrapper = mount(PurchaseRow, { props: { order: appointmentOrder } })
    // status=pending → Pendiente
    expect(wrapper.text()).toContain('Pendiente')
  })

  it('course variant is unchanged — does not show appointment fields', () => {
    const wrapper = mount(PurchaseRow, { props: { order: baseOrder } })
    expect(wrapper.text()).toContain('Curso de Makeup Pro')
    // No appointment-specific content
    expect(wrapper.text()).not.toContain('Maquillaje Social')
    expect(wrapper.text()).not.toContain('10:00')
  })
})
