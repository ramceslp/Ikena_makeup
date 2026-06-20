import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ProductFilters from '../components/catalog/ProductFilters.vue'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const fakeCategories = [
  { id: 1, name: 'Paletas', slug: 'paletas' },
  { id: 2, name: 'Pinceles', slug: 'pinceles' },
  { id: 3, name: 'Labiales', slug: 'labiales' },
]

function mountFilters(props = {}) {
  return mount(ProductFilters, {
    props: {
      categories: fakeCategories,
      ...props,
    },
  })
}

// ---------------------------------------------------------------------------
// Category pills
// ---------------------------------------------------------------------------

describe('ProductFilters.vue — category pills', () => {
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
    await pills[1].trigger('click') // index 0 = Todas, index 1 = Paletas
    const emitted = wrapper.emitted('update:category')
    expect(emitted).toBeTruthy()
    expect(emitted[0]).toEqual(['paletas'])
  })

  it('clicking "Todas" pill emits update:category with empty string', async () => {
    const wrapper = mountFilters({ category: 'paletas' })
    const pills = wrapper.findAll('[data-category-pill]')
    await pills[0].trigger('click')
    const emitted = wrapper.emitted('update:category')
    expect(emitted).toBeTruthy()
    expect(emitted[0]).toEqual([''])
  })

  it('active category pill has bg-primary class', () => {
    const wrapper = mountFilters({ category: 'pinceles' })
    const pills = wrapper.findAll('[data-category-pill]')
    // pills[0]=Todas, [1]=Paletas, [2]=Pinceles
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

describe('ProductFilters.vue — price range', () => {
  it('renders min_price and max_price inputs', () => {
    const wrapper = mountFilters()
    const inputs = wrapper.findAll('input[type="number"]')
    expect(inputs.length).toBeGreaterThanOrEqual(2)
  })

  it('emits update:minPrice with the entered value when min price input changes', async () => {
    const wrapper = mountFilters()
    const minInput = wrapper.find('input[aria-label*="mín"]')
    await minInput.setValue('50')
    const emitted = wrapper.emitted('update:minPrice')
    expect(emitted).toBeTruthy()
    expect(emitted[0][0]).toBe('50')
  })

  it('emits update:maxPrice with the entered value when max price input changes', async () => {
    const wrapper = mountFilters()
    const maxInput = wrapper.find('input[aria-label*="máx"]')
    await maxInput.setValue('200')
    const emitted = wrapper.emitted('update:maxPrice')
    expect(emitted).toBeTruthy()
    expect(emitted[0][0]).toBe('200')
  })
})

// ---------------------------------------------------------------------------
// Sort and search
// ---------------------------------------------------------------------------

describe('ProductFilters.vue — sort and search', () => {
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
// Stock state filter (product-specific — in_stock / out_of_stock)
// ---------------------------------------------------------------------------

describe('ProductFilters.vue — stock state filter', () => {
  it('renders a stock state filter control', () => {
    const wrapper = mountFilters()
    const hasStockFilter =
      wrapper.find('[data-stock-filter]').exists() ||
      wrapper.find('option[value="in_stock"]').exists() ||
      wrapper.find('input[value="in_stock"]').exists()
    expect(hasStockFilter).toBe(true)
  })

  it('emits update:stockState with correct value when stock filter changes', async () => {
    const wrapper = mountFilters()
    const stockSelect = wrapper.find('[data-stock-filter]')
    expect(stockSelect.exists()).toBe(true)
    await stockSelect.setValue('in_stock')
    const emitted = wrapper.emitted('update:stockState')
    expect(emitted).toBeTruthy()
    expect(emitted[0][0]).toBe('in_stock')
  })
})
