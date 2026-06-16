import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CourseFilters from '../components/home/CourseFilters.vue'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const fakeCategories = [
  { id: 1, name: 'Editorial', slug: 'editorial' },
  { id: 2, name: 'Novias', slug: 'novias' },
  { id: 3, name: 'Noche', slug: 'noche' },
]

function mountFilters(props = {}) {
  return mount(CourseFilters, {
    props: {
      categories: fakeCategories,
      ...props,
    },
  })
}

// ---------------------------------------------------------------------------
// Category pills
// ---------------------------------------------------------------------------

describe('CourseFilters.vue — category pills', () => {
  it('renders a "Todas" pill', () => {
    const wrapper = mountFilters()
    expect(wrapper.text()).toContain('Todas')
  })

  it('renders one pill per category', () => {
    const wrapper = mountFilters()
    for (const cat of fakeCategories) {
      expect(wrapper.text()).toContain(cat.name)
    }
  })

  it('renders a total of N+1 pills (categories + Todas)', () => {
    const wrapper = mountFilters()
    // Find all pill elements by data-testid or button/span with pill role
    // We search for any element containing "Todas" + category names
    const pills = wrapper.findAll('[data-category-pill]')
    expect(pills).toHaveLength(fakeCategories.length + 1)
  })

  it('clicking a category pill emits update:category with its slug', async () => {
    const wrapper = mountFilters()
    const pills = wrapper.findAll('[data-category-pill]')
    // pills[0] = Todas, pills[1] = Editorial
    await pills[1].trigger('click')
    const emitted = wrapper.emitted('update:category')
    expect(emitted).toBeTruthy()
    expect(emitted[0]).toEqual(['editorial'])
  })

  it('clicking "Todas" pill emits update:category with empty string', async () => {
    const wrapper = mountFilters({ category: 'editorial' })
    const pills = wrapper.findAll('[data-category-pill]')
    // pills[0] is always Todas
    await pills[0].trigger('click')
    const emitted = wrapper.emitted('update:category')
    expect(emitted).toBeTruthy()
    expect(emitted[0]).toEqual([''])
  })

  it('active category pill (matching model) has bg-primary class', () => {
    const wrapper = mountFilters({ category: 'novias' })
    const pills = wrapper.findAll('[data-category-pill]')
    // pills[0]=Todas, [1]=Editorial, [2]=Novias (matches category)
    expect(pills[2].classes()).toContain('bg-primary')
  })

  it('"Todas" pill is active when category model is empty string', () => {
    const wrapper = mountFilters({ category: '' })
    const pills = wrapper.findAll('[data-category-pill]')
    expect(pills[0].classes()).toContain('bg-primary')
  })

  it('inactive pills do NOT have bg-primary class', () => {
    const wrapper = mountFilters({ category: 'editorial' })
    const pills = wrapper.findAll('[data-category-pill]')
    // pills[2] = Novias, inactive
    expect(pills[2].classes()).not.toContain('bg-primary')
  })

  it('renders no category pills when categories prop is empty', () => {
    const wrapper = mountFilters({ categories: [] })
    const pills = wrapper.findAll('[data-category-pill]')
    // Only "Todas"
    expect(pills).toHaveLength(1)
    expect(wrapper.text()).toContain('Todas')
  })
})
