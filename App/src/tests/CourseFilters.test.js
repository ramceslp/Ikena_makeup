import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CourseFilters from '../components/course/CourseFilters.vue'

// [DEFECT 1 fix] Same collapsible-secondary-filters pattern as
// ProductFilters.test.js -- see that file's header comment. Courses have no
// domain dropdown (no stock/availability equivalent), so the panel only
// holds price range + sort.
const categories = [
  { id: 1, slug: 'editorial', name: 'Editorial' },
  { id: 2, slug: 'novias', name: 'Novias' },
]

function mountFilters(props = {}) {
  return mount(CourseFilters, {
    props: { categories, ...props },
  })
}

describe('CourseFilters.vue — collapsible secondary filters', () => {
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
    expect(wrapper.find('input[aria-label="Buscar cursos"]').exists()).toBe(true)
    expect(panel.find('input[aria-label="Buscar cursos"]').exists()).toBe(false)

    expect(wrapper.find('[data-category-pill="all"]').exists()).toBe(true)
    expect(panel.find('[data-category-pill="all"]').exists()).toBe(false)
  })

  it('does not show a count badge when no secondary filter is active', () => {
    const wrapper = mountFilters({ sort: 'newest', minPrice: '', maxPrice: '' })
    const text = wrapper.find('[data-filters-toggle]').text()
    expect(text).toContain('Filtros')
    expect(text).not.toContain('·')
  })

  it('counts price range as ONE active filter when either bound is set', () => {
    const wrapper = mountFilters({ minPrice: '20', maxPrice: '' })
    expect(wrapper.find('[data-filters-toggle]').text()).toContain('Filtros · 1')
  })

  it('does not count sort when it is at its default ("newest")', () => {
    const wrapper = mountFilters({ sort: 'newest' })
    const text = wrapper.find('[data-filters-toggle]').text()
    expect(text).toContain('Filtros')
    expect(text).not.toContain('·')
  })

  it('sums independent active filters (price + non-default sort = 2)', () => {
    const wrapper = mountFilters({ minPrice: '20', maxPrice: '100', sort: 'price_desc' })
    expect(wrapper.find('[data-filters-toggle]').text()).toContain('Filtros · 2')
  })

  it('hidden filters still propagate v-model updates while the panel is collapsed', async () => {
    const wrapper = mountFilters()

    const sortSelect = wrapper.find('select[aria-label="Ordenar"]')
    await sortSelect.setValue('price_asc')

    expect(wrapper.emitted('update:sort')).toBeTruthy()
    expect(wrapper.emitted('update:sort').at(-1)).toEqual(['price_asc'])
  })

  it('the toggle button meets the 48x48dp minimum touch target', () => {
    const wrapper = mountFilters()
    const toggle = wrapper.find('[data-filters-toggle]')
    expect(toggle.classes()).toEqual(
      expect.arrayContaining([expect.stringMatching(/min-h-12/)]),
    )
  })

  it('is sticky below the app top bar at z-20, aligned with ProductFilters/ServiceFilters convention', () => {
    const wrapper = mountFilters()
    const section = wrapper.find('section')
    expect(section.classes()).toContain('sticky')
    expect(section.classes()).toContain('z-20')
    expect(section.classes().some((c) => c.includes('top-[calc(var(--app-topbar-h)'))).toBe(true)
  })
})
