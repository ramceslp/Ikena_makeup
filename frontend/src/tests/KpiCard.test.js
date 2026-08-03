import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import KpiCard from '../components/admin/reports/KpiCard.vue'

describe('KpiCard.vue', () => {
  it('renders the label and formatted value', () => {
    const wrapper = mount(KpiCard, {
      props: { label: 'Ingresos confirmados', value: '$1,234.50' },
    })

    expect(wrapper.text()).toContain('Ingresos confirmados')
    expect(wrapper.text()).toContain('$1,234.50')
  })

  it('renders an optional hint under the value', () => {
    const wrapper = mount(KpiCard, {
      props: {
        label: 'Depósitos retenidos',
        value: '$80.00',
        hint: 'Por cancelaciones, no es ingreso por servicio entregado',
      },
    })

    expect(wrapper.text()).toContain('Por cancelaciones, no es ingreso por servicio entregado')
  })

  it('does not render a hint element when none is provided', () => {
    const wrapper = mount(KpiCard, { props: { label: 'Órdenes', value: '12' } })

    expect(wrapper.find('[data-kpi-hint]').exists()).toBe(false)
  })
})
