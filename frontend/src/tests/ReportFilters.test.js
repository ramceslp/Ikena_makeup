import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ReportFilters from '../components/admin/reports/ReportFilters.vue'

function mountFilters(props = {}) {
  return mount(ReportFilters, {
    props: {
      from: '2026-07-01',
      to: '2026-07-31',
      granularity: 'day',
      degraded: false,
      requestedGranularity: 'day',
      effectiveGranularity: 'day',
      ...props,
    },
  })
}

describe('ReportFilters.vue', () => {
  it('renders the current from/to/granularity values', () => {
    const wrapper = mountFilters()

    expect(wrapper.find('[data-filter-from]').element.value).toBe('2026-07-01')
    expect(wrapper.find('[data-filter-to]').element.value).toBe('2026-07-31')
    expect(wrapper.find('[data-filter-granularity]').element.value).toBe('day')
  })

  it('emits update:from when the from date changes', async () => {
    const wrapper = mountFilters()

    await wrapper.find('[data-filter-from]').setValue('2026-06-01')

    expect(wrapper.emitted('update:from')).toEqual([['2026-06-01']])
  })

  it('emits update:to when the to date changes', async () => {
    const wrapper = mountFilters()

    await wrapper.find('[data-filter-to]').setValue('2026-08-01')

    expect(wrapper.emitted('update:to')).toEqual([['2026-08-01']])
  })

  it('emits update:granularity when the granularity select changes', async () => {
    const wrapper = mountFilters()

    await wrapper.find('[data-filter-granularity]').setValue('week')

    expect(wrapper.emitted('update:granularity')).toEqual([['week']])
  })

  it('shows no degradation notice when degraded is false', () => {
    const wrapper = mountFilters({ degraded: false })

    expect(wrapper.find('[data-degradation-notice]').exists()).toBe(false)
  })

  it('shows a degradation notice naming both granularities when degraded is true', () => {
    const wrapper = mountFilters({
      degraded: true,
      requestedGranularity: 'day',
      effectiveGranularity: 'week',
    })

    const notice = wrapper.find('[data-degradation-notice]')
    expect(notice.exists()).toBe(true)
    expect(notice.text()).toContain('semana')
    expect(notice.text()).toContain('día')
  })
})
