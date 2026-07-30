import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ProductFilters from '../components/catalog/ProductFilters.vue'

// [DEFECT 1 fix] ProductFilters.vue used to render search + price range +
// stock dropdown + sort dropdown + category chips all at once, filling the
// entire first viewport on a 375px phone with zero product cards visible.
// Secondary controls (price range, stock, sort) now collapse behind a
// "Filtros" toggle; search and category chips stay always visible.
const categories = [
  { id: 1, slug: 'editorial', name: 'Editorial' },
  { id: 2, slug: 'novias', name: 'Novias' },
]

function mountFilters(props = {}) {
  return mount(ProductFilters, {
    props: { categories, ...props },
  })
}

describe('ProductFilters.vue — collapsible secondary filters', () => {
  it('is collapsed by default on mount', () => {
    const wrapper = mountFilters()

    const toggle = wrapper.find('[data-filters-toggle]')
    expect(toggle.attributes('aria-expanded')).toBe('false')
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
    expect(wrapper.find('input[aria-label="Buscar productos"]').exists()).toBe(true)
    expect(panel.find('input[aria-label="Buscar productos"]').exists()).toBe(false)

    expect(wrapper.find('[data-category-pill="all"]').exists()).toBe(true)
    expect(panel.find('[data-category-pill="all"]').exists()).toBe(false)
  })

  it('does not show a count badge when no secondary filter is active', () => {
    const wrapper = mountFilters({ sort: 'newest', minPrice: '', maxPrice: '', stockState: '' })

    const text = wrapper.find('[data-filters-toggle]').text()

    expect(text).toContain('Filtros')

    expect(text).not.toContain('·')
  })

  it('counts price range as ONE active filter when either bound is set', () => {
    const wrapper = mountFilters({ minPrice: '10', maxPrice: '' })
    expect(wrapper.find('[data-filters-toggle]').text()).toContain('Filtros · 1')
  })

  it('does not count sort when it is at its default ("newest")', () => {
    const wrapper = mountFilters({ sort: 'newest' })
    const text = wrapper.find('[data-filters-toggle]').text()
    expect(text).toContain('Filtros')
    expect(text).not.toContain('·')
  })

  it('counts a non-default sort as an active filter', () => {
    const wrapper = mountFilters({ sort: 'price_asc' })
    expect(wrapper.find('[data-filters-toggle]').text()).toContain('Filtros · 1')
  })

  it('counts stock state as an active filter when set', () => {
    const wrapper = mountFilters({ stockState: 'in_stock' })
    expect(wrapper.find('[data-filters-toggle]').text()).toContain('Filtros · 1')
  })

  it('sums independent active filters (price + stock + non-default sort = 3)', () => {
    const wrapper = mountFilters({ minPrice: '10', maxPrice: '50', stockState: 'in_stock', sort: 'price_desc' })
    expect(wrapper.find('[data-filters-toggle]').text()).toContain('Filtros · 3')
  })

  it('hidden filters still propagate v-model updates while the panel is collapsed', async () => {
    const wrapper = mountFilters()

    // Panel is collapsed (default) -- setting a value on a control inside it
    // must still update the bound model, since collapsing is purely visual.
    const stockSelect = wrapper.find('[data-stock-filter]')
    await stockSelect.setValue('out_of_stock')

    expect(wrapper.emitted('update:stockState')).toBeTruthy()
    expect(wrapper.emitted('update:stockState').at(-1)).toEqual(['out_of_stock'])
  })

  it('the toggle button meets the 48x48dp minimum touch target', () => {
    const wrapper = mountFilters()
    const toggle = wrapper.find('[data-filters-toggle]')
    expect(toggle.classes()).toEqual(
      expect.arrayContaining([expect.stringMatching(/min-h-12/)]),
    )
  })
})
