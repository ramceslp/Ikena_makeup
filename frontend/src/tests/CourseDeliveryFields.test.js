import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CourseDeliveryFields from '../components/course/CourseDeliveryFields.vue'

const onDemand = {
  delivery_mode: 'on_demand',
  starts_on: null,
  ends_on: null,
  total_hours: null,
}

const live = {
  delivery_mode: 'live',
  starts_on: '2026-09-01',
  ends_on: '2026-09-30',
  total_hours: 20,
}

function mountFields(modelValue = onDemand, props = {}) {
  return mount(CourseDeliveryFields, {
    props: { modelValue, ...props },
  })
}

describe('CourseDeliveryFields', () => {
  it('hides the calendar for an on-demand course', () => {
    const wrapper = mountFields()

    expect(wrapper.find('#course-starts-on').exists()).toBe(false)
    expect(wrapper.find('#course-ends-on').exists()).toBe(false)
  })

  it('shows and prefills the calendar for a live course', () => {
    const wrapper = mountFields(live)

    expect(wrapper.find('#course-starts-on').element.value).toBe('2026-09-01')
    expect(wrapper.find('#course-ends-on').element.value).toBe('2026-09-30')
    expect(wrapper.find('#course-total-hours').element.value).toBe('20')
  })

  it('offers total hours in both modes', () => {
    expect(mountFields().find('#course-total-hours').exists()).toBe(true)
    expect(mountFields(live).find('#course-total-hours').exists()).toBe(true)
  })

  it('switching to live emits the mode without inventing dates', async () => {
    const wrapper = mountFields()

    await wrapper.find('input[value="live"]').setValue()

    const [emitted] = wrapper.emitted('update:modelValue').at(-1)
    expect(emitted.delivery_mode).toBe('live')
    expect(emitted.starts_on).toBeNull()
  })

  /**
   * The API rejects the calendar fields outright on an on-demand course, so
   * leaving stale dates in the payload would turn a mode switch into a 422.
   */
  it('switching back to on-demand clears the calendar but keeps total hours', async () => {
    const wrapper = mountFields(live)

    await wrapper.find('input[value="on_demand"]').setValue()

    const [emitted] = wrapper.emitted('update:modelValue').at(-1)
    expect(emitted).toEqual({
      delivery_mode: 'on_demand',
      starts_on: null,
      ends_on: null,
      total_hours: 20,
    })
  })

  it('emits an edited start date', async () => {
    const wrapper = mountFields(live)

    await wrapper.find('#course-starts-on').setValue('2026-10-05')

    const [emitted] = wrapper.emitted('update:modelValue').at(-1)
    expect(emitted.starts_on).toBe('2026-10-05')
  })

  it('emits null when total hours is cleared', async () => {
    const wrapper = mountFields(live)

    await wrapper.find('#course-total-hours').setValue('')

    const [emitted] = wrapper.emitted('update:modelValue').at(-1)
    expect(emitted.total_hours).toBeNull()
  })

  it('surfaces server validation errors on the calendar', () => {
    const wrapper = mountFields(live, {
      validationErrors: { ends_on: ['The end date cannot be earlier than the start date.'] },
    })

    expect(wrapper.find('[data-error-ends-on]').text()).toContain(
      'The end date cannot be earlier than the start date.',
    )
  })
})
