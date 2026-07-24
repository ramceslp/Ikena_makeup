/**
 * End-to-end booking flow test (mobile-capacitor-setup Phase 7, tasks 7.3/7.4):
 * mounts the real ServiceDetail.vue + SlotPicker.vue + BookingCalendar.vue +
 * BookingForm.vue tree and drives day -> slot -> confirm through the DOM,
 * exactly like a user would.
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

import api from '../services/api.js'
import ServiceDetail from '../views/ServiceDetail.vue'

const fakeService = {
  id: 5,
  slug: 'maquillaje-novia',
  title: 'Maquillaje de Novia',
  description: 'Un maquillaje profesional para tu gran día.',
  price: '80.00',
  duration_hours: 2,
  availability_type: 'by_appointment',
  deposit_percentage: 50,
  images: [],
  category: null,
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/services/:slug', component: ServiceDetail, name: 'service-detail' },
      { path: '/services', component: { template: '<div/>' }, name: 'services' },
      { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
    ],
  })
}

async function mountBooked(router) {
  const wrapper = mount(ServiceDetail, { global: { plugins: [router] } })
  await flushPromises()

  // Day '2026-07-21' is 'today' (fake system time below) and available —
  // click it to load that day's slots.
  await wrapper.find('[data-calendar-day][data-date="2026-07-21"]').trigger('click')
  await flushPromises()

  // Select the 10:00 slot.
  await wrapper.find('[data-slot-card]').trigger('click')

  // Fill the required WhatsApp field.
  await wrapper.find('[data-whatsapp-input]').setValue('+593999999999')

  return wrapper
}

describe('ServiceDetail.vue (App) — end-to-end booking flow (day -> slot -> confirm)', () => {
  let router

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    vi.useFakeTimers()
    // 2026-07-21T15:00:00Z === 2026-07-21T10:00:00-05:00 (America/Guayaquil) —
    // business "today" is unambiguously 2026-07-21, matching localDate.test.js.
    vi.setSystemTime(new Date('2026-07-21T15:00:00Z'))

    api.get.mockImplementation((url, config) => {
      if (url === '/services/maquillaje-novia') {
        return Promise.resolve({ data: { data: fakeService } })
      }
      if (url === '/services/5/available-days') {
        return Promise.resolve({ data: { data: [{ date: '2026-07-21', available_count: 2 }] } })
      }
      if (url === '/services/5/available-slots') {
        return Promise.resolve({
          data: {
            data: [
              {
                id: 10,
                date_label: '2026-07-21',
                start_time: '10:00',
                capacity_remaining: 2,
                is_blocked: false,
                is_near_capacity: false,
              },
            ],
          },
        })
      }
      return Promise.reject(new Error(`unexpected GET ${url}`))
    })

    router = makeRouter()
    await router.push('/services/maquillaje-novia')
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('completes day -> slot -> confirm and shows a confirmation [Spec: booking succeeds]', async () => {
    api.post.mockResolvedValueOnce({
      data: { data: { order_id: 42, provider: 'payphone', config: {}, is_near_capacity: false, warning_message: null } },
    })

    const wrapper = await mountBooked(router)
    await wrapper.find('[data-submit-btn]').trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/bookings', {
      service_id: 5,
      scheduled_date: '2026-07-21',
      scheduled_time: '10:00',
      whatsapp: '+593999999999',
    })
    expect(wrapper.find('[data-booking-confirmed]').exists()).toBe(true)
    expect(wrapper.text()).toContain('#42')
    // No payment URL navigation of any kind — confirmation only.
    expect(wrapper.find('[data-submit-btn]').exists()).toBe(false)
  })

  it('shows a refreshed-slot error (not a silent failure) when the slot is claimed before confirmation [Spec: slot goes stale before confirmation]', async () => {
    const conflict = new Error('Conflict')
    conflict.response = { status: 409, data: { code: 'cap_exceeded' } }
    api.post.mockRejectedValueOnce(conflict)

    const wrapper = await mountBooked(router)
    await wrapper.find('[data-submit-btn]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-booking-error]').text()).toBe(
      'Este horario ya no está disponible. Por favor elige otro.',
    )
    // Not a silent failure: no confirmation rendered, and the calendar/slot
    // picker is still there with refreshed data (re-fetched via the store).
    expect(wrapper.find('[data-booking-confirmed]').exists()).toBe(false)
    expect(api.get).toHaveBeenCalledWith('/services/5/available-slots', { params: { date: '2026-07-21' } })
    expect(api.get).toHaveBeenCalledWith('/services/5/available-days')
    // The previously selected slot must be cleared, not silently resubmittable.
    expect(wrapper.find('[data-slot-card][data-slot-selected]').exists()).toBe(false)
  })
})
