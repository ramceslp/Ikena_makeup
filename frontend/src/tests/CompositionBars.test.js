import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CompositionBars from '../components/admin/reports/CompositionBars.vue'

describe('CompositionBars.vue', () => {
  it('renders a labelled row per order type with its formatted amount', () => {
    const wrapper = mount(CompositionBars, {
      props: {
        byType: { course: 9900, product: 5000, service: 8000 },
        retainedDepositsCents: 2000,
        totalCents: 24900,
      },
    })

    expect(wrapper.text()).toContain('Cursos')
    expect(wrapper.text()).toContain('$99.00')
    expect(wrapper.text()).toContain('Productos')
    expect(wrapper.text()).toContain('$50.00')
    expect(wrapper.text()).toContain('Servicios')
    expect(wrapper.text()).toContain('$80.00')
  })

  it('renders retained deposits as its own labelled line, not merged into service revenue', () => {
    const wrapper = mount(CompositionBars, {
      props: {
        byType: { course: 0, product: 0, service: 8000 },
        retainedDepositsCents: 2000,
        totalCents: 10000,
      },
    })

    const retainedRow = wrapper.find('[data-composition-retained]')
    expect(retainedRow.exists()).toBe(true)
    expect(retainedRow.text()).toContain('$20.00')
    expect(retainedRow.text()).toContain('retenid')
  })

  it('scales each bar width proportionally to totalCents, guarding a zero total', () => {
    const wrapper = mount(CompositionBars, {
      props: { byType: { course: 0, product: 0, service: 0 }, retainedDepositsCents: 0, totalCents: 0 },
    })

    const bars = wrapper.findAll('[data-composition-bar]')
    for (const bar of bars) {
      expect(bar.attributes('style')).toContain('width: 0%')
    }
  })
})
