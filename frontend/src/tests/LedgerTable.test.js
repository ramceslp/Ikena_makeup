import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
  },
}))

import api from '../services/api.js'
import LedgerTable from '../components/admin/reports/LedgerTable.vue'

function makeRows() {
  return [
    { occurred_at: '2026-08-05T10:00:00-05:00', amount_cents: 9900, stream: 'course_sale', label: 'Venta de curso', counterparty: 'Ana Buyer' },
    { occurred_at: '2026-08-06T10:00:00-05:00', amount_cents: 2000, stream: 'appointment_deposit', label: 'Anticipo de cita', counterparty: null },
  ]
}

describe('LedgerTable.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    global.URL.createObjectURL = vi.fn(() => 'blob:mock-url')
    global.URL.revokeObjectURL = vi.fn()
    window.print = vi.fn()
  })

  it('renders a row per ledger entry with date, label, counterparty, and formatted amount', () => {
    const wrapper = mount(LedgerTable, {
      props: { rows: makeRows(), meta: { current_page: 1, last_page: 1, total: 2 }, from: '2026-08-01', to: '2026-08-31' },
    })

    const rows = wrapper.findAll('[data-ledger-row]')
    expect(rows).toHaveLength(2)
    expect(wrapper.text()).toContain('Venta de curso')
    expect(wrapper.text()).toContain('Ana Buyer')
    expect(wrapper.text()).toContain('$99.00')
    expect(wrapper.text()).toContain('Anticipo de cita')
  })

  it('shows an empty state when there are no rows', () => {
    const wrapper = mount(LedgerTable, {
      props: { rows: [], meta: null, from: '2026-08-01', to: '2026-08-31' },
    })

    expect(wrapper.text()).toContain('No hay movimientos')
  })

  it('emits update:stream when the stream filter changes', async () => {
    const wrapper = mount(LedgerTable, {
      props: { rows: makeRows(), meta: { current_page: 1, last_page: 1, total: 2 }, from: '2026-08-01', to: '2026-08-31' },
    })

    await wrapper.find('[data-ledger-stream-filter]').setValue('product_sale')

    expect(wrapper.emitted('update:stream')[0]).toEqual(['product_sale'])
  })

  it('emits update:page when paging past the first page', async () => {
    const wrapper = mount(LedgerTable, {
      props: { rows: makeRows(), meta: { current_page: 1, last_page: 3, total: 60 }, from: '2026-08-01', to: '2026-08-31' },
    })

    await wrapper.find('[data-ledger-page-next]').trigger('click')

    expect(wrapper.emitted('update:page')[0]).toEqual([2])
  })

  it('exports the CSV as a blob fetched through the shared axios instance (not a bare link)', async () => {
    api.get.mockResolvedValueOnce({ data: new Blob(['csv']) })

    const wrapper = mount(LedgerTable, {
      props: { rows: makeRows(), meta: { current_page: 1, last_page: 1, total: 2 }, from: '2026-08-01', to: '2026-08-31' },
    })

    await wrapper.find('[data-ledger-export]').trigger('click')
    await Promise.resolve()

    expect(api.get).toHaveBeenCalledWith('/admin/reports/ledger/export', {
      params: { from: '2026-08-01', to: '2026-08-31' },
      responseType: 'blob',
    })
    expect(global.URL.createObjectURL).toHaveBeenCalled()
  })

  it('calls window.print when the print button is clicked', async () => {
    const wrapper = mount(LedgerTable, {
      props: { rows: makeRows(), meta: { current_page: 1, last_page: 1, total: 2 }, from: '2026-08-01', to: '2026-08-31' },
    })

    await wrapper.find('[data-ledger-print]').trigger('click')

    expect(window.print).toHaveBeenCalled()
  })
})
