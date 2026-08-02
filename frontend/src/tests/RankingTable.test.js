import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import RankingTable from '../components/admin/reports/RankingTable.vue'

function productsRows() {
  return [
    {
      product_id: 1,
      title: 'Labial Rosa',
      quantity: 10,
      revenue_cents: 50000,
      known_cost_revenue_cents: 30000,
      margin_cents: 12000,
    },
    {
      product_id: 2,
      title: 'Base Líquida',
      quantity: 5,
      revenue_cents: 20000,
      known_cost_revenue_cents: 20000,
      margin_cents: 8000,
    },
  ]
}

function servicesRows() {
  return [
    { service_id: 1, title: 'Maquillaje de novia', revenue_cents: 80000, duration_hours: 2, revenue_per_hour_cents: 40000 },
  ]
}

function coursesRows() {
  return [
    { course_id: 1, title: 'Curso de maquillaje', revenue_cents: 60000, paid_enrollment_count: 6, free_enrollment_count: 2 },
  ]
}

describe('RankingTable.vue', () => {
  it('renders product rows with revenue, margin, and a partial cost-coverage indicator', () => {
    const wrapper = mount(RankingTable, {
      props: { products: productsRows(), services: [], courses: [] },
    })

    const rows = wrapper.findAll('[data-ranking-row]')
    expect(rows).toHaveLength(2)
    expect(wrapper.text()).toContain('Labial Rosa')
    expect(wrapper.text()).toContain('$500.00')
    expect(wrapper.text()).toContain('$120.00')

    // Row 1: known_cost_revenue_cents (30000) < revenue_cents (50000) -> partial coverage
    const coverage = wrapper.find('[data-ranking-margin-coverage]')
    expect(coverage.exists()).toBe(true)
    expect(coverage.text()).toContain('60%')
  })

  it('does not show a coverage indicator when a product row has full cost coverage', () => {
    const wrapper = mount(RankingTable, {
      props: { products: [productsRows()[1]], services: [], courses: [] },
    })

    expect(wrapper.find('[data-ranking-margin-coverage]').exists()).toBe(false)
  })

  it('renders service rows with revenue per hour and no margin column', async () => {
    const wrapper = mount(RankingTable, {
      props: { products: [], services: servicesRows(), courses: [] },
    })

    await wrapper.find('[data-ranking-tab="services"]').trigger('click')

    expect(wrapper.text()).toContain('Maquillaje de novia')
    expect(wrapper.text()).toContain('$800.00')
    expect(wrapper.text()).toContain('$400.00')
    expect(wrapper.text()).not.toContain('Margen')
  })

  it('renders course rows with paid revenue and a separate free-enrollment count', async () => {
    const wrapper = mount(RankingTable, {
      props: { products: [], services: [], courses: coursesRows() },
    })

    await wrapper.find('[data-ranking-tab="courses"]').trigger('click')

    expect(wrapper.text()).toContain('Curso de maquillaje')
    expect(wrapper.text()).toContain('$600.00')
    expect(wrapper.text()).toContain('6')
    expect(wrapper.text()).toContain('2')
  })

  it('shows an empty state when the active type has no ranking data', () => {
    const wrapper = mount(RankingTable, { props: { products: [], services: [], courses: [] } })

    expect(wrapper.text()).toContain('No hay datos para el rango seleccionado')
  })

  it('emits update:active-type when switching tabs', async () => {
    const wrapper = mount(RankingTable, {
      props: { products: productsRows(), services: servicesRows(), courses: coursesRows() },
    })

    await wrapper.find('[data-ranking-tab="courses"]').trigger('click')

    expect(wrapper.emitted('update:active-type')[0]).toEqual(['courses'])
  })
})
