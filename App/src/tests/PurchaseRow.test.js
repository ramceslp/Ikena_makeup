import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PurchaseRow from '../components/profile/PurchaseRow.vue'

// [Judgment Day fix, PR8d Round 1]: formatDate() previously appended a bare
// 'T00:00:00' suffix to EVERY date it received, assuming a bare 'YYYY-MM-DD'
// input. That assumption only holds for order.appointment?.scheduled_date
// (a bare Y-m-d date-only field). order.paid_at/order.created_at (used by
// product_cart/course rows) are Eloquent 'datetime'-cast attributes,
// serialized as FULL ISO datetime strings by OrderResource.php. Appending
// the suffix to an already-full ISO string produced Invalid Date, formatting
// threw, and the row silently fell back to showing the raw unformatted ISO
// string. This asserts both the regression fix (full ISO datetime formats
// correctly) and the pre-existing bare-date behavior stays intact.
describe('PurchaseRow.vue (App) — formatDate handles both full ISO datetimes and bare dates', () => {
  it('formats a full ISO datetime string (paid_at/created_at) without throwing or falling back to the raw string', () => {
    const productOrder = {
      id: 10,
      type: 'product_cart',
      status: 'paid',
      amount_cents: 5000,
      currency: 'USD',
      paid_at: '2026-07-20T10:00:00.000000Z',
      created_at: '2026-07-20T10:00:00.000000Z',
      items: [{ product_title: 'Paleta', quantity: 1, line_total_cents: 5000 }],
    }
    const wrapper = mount(PurchaseRow, { props: { order: productOrder } })

    // Must NOT show the raw unformatted ISO string (the pre-fix fallback).
    expect(wrapper.text()).not.toContain('2026-07-20T10:00:00.000000Z')
    // Must show a real formatted date (Spanish medium format includes the year).
    expect(wrapper.text()).toMatch(/2026/)
  })

  it('still correctly formats a bare YYYY-MM-DD scheduled_date for appointments (regression guard)', () => {
    const appointmentOrder = {
      id: 1,
      type: 'appointment',
      status: 'confirmed',
      currency: 'USD',
      appointment: {
        service_title: 'Corte',
        scheduled_date: '2026-07-05',
        scheduled_time: '10:00',
        deposit_amount_cents: 1000,
      },
    }
    const wrapper = mount(PurchaseRow, { props: { order: appointmentOrder } })

    expect(wrapper.text()).not.toContain('2026-07-05')
    expect(wrapper.text()).toMatch(/2026/)
  })
})
