import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ServiceFilters from '../components/service/ServiceFilters.vue'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const fakeCategories = [
  { id: 1, name: 'Social', slug: 'social' },
  { id: 2, name: 'Novias', slug: 'novias' },
  { id: 3, name: 'Noche', slug: 'noche' },
]

function mountFilters(props = {}) {
  return mount(ServiceFilters, {
    props: {
      categories: fakeCategories,
      ...props,
    },
  })
}

// ---------------------------------------------------------------------------
// Category pills
// ---------------------------------------------------------------------------

describe('ServiceFilters.vue — category pills', () => {
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

  it('renders N+1 pills (categories + Todas)', () => {
    const wrapper = mountFilters()
    const pills = wrapper.findAll('[data-category-pill]')
    expect(pills).toHaveLength(fakeCategories.length + 1)
  })

  it('clicking a category pill emits update:category with its slug', async () => {
    const wrapper = mountFilters()
    const pills = wrapper.findAll('[data-category-pill]')
    await pills[1].trigger('click') // index 0 = Todas, index 1 = Social
    const emitted = wrapper.emitted('update:category')
    expect(emitted).toBeTruthy()
    expect(emitted[0]).toEqual(['social'])
  })

  it('clicking "Todas" pill emits update:category with empty string', async () => {
    const wrapper = mountFilters({ category: 'social' })
    const pills = wrapper.findAll('[data-category-pill]')
    await pills[0].trigger('click')
    const emitted = wrapper.emitted('update:category')
    expect(emitted).toBeTruthy()
    expect(emitted[0]).toEqual([''])
  })

  it('active category pill has bg-primary class', () => {
    const wrapper = mountFilters({ category: 'novias' })
    const pills = wrapper.findAll('[data-category-pill]')
    // pills[0]=Todas, [1]=Social, [2]=Novias
    expect(pills[2].classes()).toContain('bg-primary')
  })

  it('"Todas" pill is active when category is empty string', () => {
    const wrapper = mountFilters({ category: '' })
    const pills = wrapper.findAll('[data-category-pill]')
    expect(pills[0].classes()).toContain('bg-primary')
  })

  it('renders only "Todas" when categories prop is empty', () => {
    const wrapper = mountFilters({ categories: [] })
    const pills = wrapper.findAll('[data-category-pill]')
    expect(pills).toHaveLength(1)
    expect(wrapper.text()).toContain('Todas')
  })
})

// ---------------------------------------------------------------------------
// Price range
// ---------------------------------------------------------------------------

describe('ServiceFilters.vue — price range', () => {
  it('renders min_price and max_price inputs', () => {
    const wrapper = mountFilters()
    const inputs = wrapper.findAll('input[type="number"]')
    expect(inputs.length).toBeGreaterThanOrEqual(2)
  })

  it('emits update:minPrice when min price input changes', async () => {
    const wrapper = mountFilters()
    const minInput = wrapper.find('input[aria-label*="mín"]')
    await minInput.setValue('50')
    const emitted = wrapper.emitted('update:minPrice')
    expect(emitted).toBeTruthy()
  })

  it('emits update:maxPrice when max price input changes', async () => {
    const wrapper = mountFilters()
    const maxInput = wrapper.find('input[aria-label*="máx"]')
    await maxInput.setValue('200')
    const emitted = wrapper.emitted('update:maxPrice')
    expect(emitted).toBeTruthy()
  })
})

// ---------------------------------------------------------------------------
// Sort + search
// ---------------------------------------------------------------------------

describe('ServiceFilters.vue — sort and search', () => {
  it('renders sort select with price_asc option', () => {
    const wrapper = mountFilters()
    expect(wrapper.find('select').exists()).toBe(true)
    expect(wrapper.find('option[value="price_asc"]').exists()).toBe(true)
  })

  it('renders search input', () => {
    const wrapper = mountFilters()
    const searchInput = wrapper.find('input[type="text"]')
    expect(searchInput.exists()).toBe(true)
  })
})

// ---------------------------------------------------------------------------
// Availability radios/select
// ---------------------------------------------------------------------------

describe('ServiceFilters.vue — availability filter', () => {
  it('renders an availability filter control', () => {
    const wrapper = mountFilters()
    // Either a select or radio buttons for availability
    const hasAvailability =
      wrapper.find('[data-availability]').exists() ||
      wrapper.find('option[value="immediate"]').exists() ||
      wrapper.find('input[value="immediate"]').exists()
    expect(hasAvailability).toBe(true)
  })
})
