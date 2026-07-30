import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ServiceFilters from '../components/service/ServiceFilters.vue'

// [DEFECT 1 fix] Same collapsible-secondary-filters pattern as
// ProductFilters.test.js -- see that file's header comment. Service's
// domain dropdown is availability (immediate / by_appointment) instead of
// stock.
const categories = [
  { id: 1, slug: 'editorial', name: 'Editorial' },
  { id: 2, slug: 'novias', name: 'Novias' },
]

function mountFilters(props = {}) {
  return mount(ServiceFilters, {
    props: { categories, ...props },
  })
}

describe('ServiceFilters.vue — collapsible secondary filters', () => {
  it('is collapsed by default on mount', () => {
    const wrapper = mountFilters()

    expect(wrapper.find('[data-filters-toggle]').attributes('aria-expanded')).toBe('false')
    expect(wrapper.find('[data-filters-panel]').attributes('inert')).toBe('true')
  })

  it('the toggle button is a real <button> wired to the collapsible panel via aria-controls/id', () => {
    const wrapper = mountFilters()

    const toggle = wrapper.find('[data-filters-toggle]')
    const panel = wrapper.find('[data-filters-panel]')
    expect(toggle.element.tagName).toBe('BUTTON')
    expect(toggle.attributes('aria-controls')).toBe(panel.attributes('id'))
  })

  it('expands the panel and updates aria-expanded when the toggle is clicked', async () => {
    const wrapper = mountFilters()

    await wrapper.find('[data-filters-toggle]').trigger('click')

    expect(wrapper.find('[data-filters-toggle]').attributes('aria-expanded')).toBe('true')
    expect(wrapper.find('[data-filters-panel]').attributes('inert')).not.toBe('true')
  })

  it('search input and category chips are always visible, never inside the collapsible panel', () => {
    const wrapper = mountFilters()

    const panel = wrapper.find('[data-filters-panel]')
    expect(wrapper.find('input[aria-label="Buscar servicios"]').exists()).toBe(true)
    expect(panel.find('input[aria-label="Buscar servicios"]').exists()).toBe(false)

    expect(wrapper.find('[data-category-pill="all"]').exists()).toBe(true)
    expect(panel.find('[data-category-pill="all"]').exists()).toBe(false)
  })

  it('does not show a count badge when no secondary filter is active', () => {
    const wrapper = mountFilters({ sort: 'newest', minPrice: '', maxPrice: '', availabilityType: '' })
    const text = wrapper.find('[data-filters-toggle]').text()
    expect(text).toContain('Filtros')
    expect(text).not.toContain('·')
  })

  it('counts price range as ONE active filter when either bound is set', () => {
    const wrapper = mountFilters({ minPrice: '', maxPrice: '80' })
    expect(wrapper.find('[data-filters-toggle]').text()).toContain('Filtros · 1')
  })

  it('does not count sort when it is at its default ("newest")', () => {
    const wrapper = mountFilters({ sort: 'newest' })
    const text = wrapper.find('[data-filters-toggle]').text()
    expect(text).toContain('Filtros')
    expect(text).not.toContain('·')
  })

  it('counts availability as an active filter when set', () => {
    const wrapper = mountFilters({ availabilityType: 'immediate' })
    expect(wrapper.find('[data-filters-toggle]').text()).toContain('Filtros · 1')
  })

  it('sums independent active filters (price + availability + non-default sort = 3)', () => {
    const wrapper = mountFilters({
      minPrice: '10',
      maxPrice: '50',
      availabilityType: 'by_appointment',
      sort: 'price_asc',
    })
    expect(wrapper.find('[data-filters-toggle]').text()).toContain('Filtros · 3')
  })

  it('hidden filters still propagate v-model updates while the panel is collapsed', async () => {
    const wrapper = mountFilters()

    const availabilitySelect = wrapper.find('[data-availability]')
    await availabilitySelect.setValue('by_appointment')

    expect(wrapper.emitted('update:availabilityType')).toBeTruthy()
    expect(wrapper.emitted('update:availabilityType').at(-1)).toEqual(['by_appointment'])
  })

  it('the toggle button meets the 48x48dp minimum touch target', () => {
    const wrapper = mountFilters()
    const toggle = wrapper.find('[data-filters-toggle]')
    expect(toggle.classes()).toEqual(
      expect.arrayContaining([expect.stringMatching(/min-h-12/)]),
    )
  })

  it('stays sticky below the app top bar at z-20, matching ProductFilters', () => {
    const wrapper = mountFilters()
    const section = wrapper.find('section')
    expect(section.classes()).toContain('sticky')
    expect(section.classes()).toContain('z-20')
    expect(section.classes().some((c) => c.includes('top-[calc(var(--app-topbar-h)'))).toBe(true)
  })
})
