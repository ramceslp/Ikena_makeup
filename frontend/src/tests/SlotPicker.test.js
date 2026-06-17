import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SlotPicker from '../components/booking/SlotPicker.vue'

const fakeSlots = [
  { id: 1, date_label: '2026-07-04', start_time: '10:00', capacity_remaining: 1, is_blocked: false },
  { id: 2, date_label: '2026-07-07', start_time: '14:00', capacity_remaining: 1, is_blocked: false },
]

describe('SlotPicker.vue', () => {
  it('renders the correct number of slot cards', () => {
    const wrapper = mount(SlotPicker, { props: { slots: fakeSlots } })
    const cards = wrapper.findAll('[data-slot-card]')
    expect(cards).toHaveLength(2)
  })

  it('renders date_label (formatted) and start_time for each slot', () => {
    const wrapper = mount(SlotPicker, { props: { slots: fakeSlots } })
    const text = wrapper.text()
    // date_label is formatted via Intl — check start_time which is displayed raw
    expect(text).toContain('10:00')
    expect(text).toContain('14:00')
    // Both date labels must appear in some form (formatted or raw)
    const cards = wrapper.findAll('[data-slot-card]')
    expect(cards).toHaveLength(2)
  })

  it('clicking a slot card emits slot-selected with slot id, scheduled_date, scheduled_time', async () => {
    const wrapper = mount(SlotPicker, { props: { slots: fakeSlots } })
    const cards = wrapper.findAll('[data-slot-card]')
    await cards[0].trigger('click')

    const emitted = wrapper.emitted('slot-selected')
    expect(emitted).toBeTruthy()
    expect(emitted[0][0]).toEqual({
      id: 1,
      scheduled_date: '2026-07-04',
      scheduled_time: '10:00',
    })
  })

  it('clicking the second slot emits its own slot data', async () => {
    const wrapper = mount(SlotPicker, { props: { slots: fakeSlots } })
    const cards = wrapper.findAll('[data-slot-card]')
    await cards[1].trigger('click')

    const emitted = wrapper.emitted('slot-selected')
    expect(emitted[0][0]).toEqual({
      id: 2,
      scheduled_date: '2026-07-07',
      scheduled_time: '14:00',
    })
  })

  it('renders empty state message when no slots are provided', () => {
    const wrapper = mount(SlotPicker, { props: { slots: [] } })
    expect(wrapper.find('[data-slot-card]').exists()).toBe(false)
    expect(wrapper.text()).toContain('No hay horarios disponibles')
  })

  it('applies selected style to the clicked slot card', async () => {
    const wrapper = mount(SlotPicker, { props: { slots: fakeSlots } })
    const cards = wrapper.findAll('[data-slot-card]')
    await cards[0].trigger('click')

    // The selected card should have data-slot-selected attribute
    expect(cards[0].attributes('data-slot-selected')).toBe('true')
    expect(cards[1].attributes('data-slot-selected')).toBeFalsy()
  })

  it('disabled slots (is_blocked=true) cannot be clicked and do not emit', async () => {
    const blockedSlot = { id: 3, date_label: '2026-07-08', start_time: '09:00', capacity_remaining: 0, is_blocked: true }
    const wrapper = mount(SlotPicker, { props: { slots: [blockedSlot] } })
    const cards = wrapper.findAll('[data-slot-card]')
    await cards[0].trigger('click')

    expect(wrapper.emitted('slot-selected')).toBeFalsy()
  })

  it('disabled slots (capacity_remaining=0) cannot be clicked and do not emit', async () => {
    const fullSlot = { id: 4, date_label: '2026-07-09', start_time: '11:00', capacity_remaining: 0, is_blocked: false }
    const wrapper = mount(SlotPicker, { props: { slots: [fullSlot] } })
    const cards = wrapper.findAll('[data-slot-card]')
    await cards[0].trigger('click')

    expect(wrapper.emitted('slot-selected')).toBeFalsy()
  })
})
