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

// The booking CTA now pays the deposit through the checkout handoff, which
// ends in @capacitor/browser — the app never renders payment in its own
// WebView (spec's Mobile App Boundaries).
vi.mock('@capacitor/browser', () => ({
  Browser: { open: vi.fn().mockResolvedValue(undefined) },
}))

import api from '../services/api.js'
import { Browser } from '@capacitor/browser'
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

  it('completes day -> slot -> pay and hands the deposit off to the browser [Spec: booking succeeds]', async () => {
    api.post.mockResolvedValueOnce({
      data: { data: { url: 'https://app.ikena.com/checkout/resume#token=abc123', expires_at: '2026-07-21T10:10:00Z' } },
    })

    const wrapper = await mountBooked(router)
    await wrapper.find('[data-submit-btn]').trigger('click')
    await flushPromises()

    // A snapshot, NOT a booking: POST /bookings would create a pending
    // appointment that holds the slot before anyone has paid for it.
    expect(api.post).toHaveBeenCalledWith('/checkout/handoff', {
      type: 'appointment',
      service_id: 5,
      scheduled_date: '2026-07-21',
      scheduled_time: '10:00',
      whatsapp: '+593999999999',
    })
    expect(api.post).not.toHaveBeenCalledWith('/bookings', expect.anything())

    // Payment opens in the system browser, never in this app's WebView.
    expect(Browser.open).toHaveBeenCalledWith({
      url: 'https://app.ikena.com/checkout/resume#token=abc123',
    })

    expect(wrapper.find('[data-booking-handed-off]').exists()).toBe(true)
    // The form is gone, so a second tap cannot mint a second handoff token.
    expect(wrapper.find('[data-submit-btn]').exists()).toBe(false)
  })

  it('does not claim the appointment is confirmed — it is not booked until the deposit is paid', async () => {
    api.post.mockResolvedValueOnce({
      data: { data: { url: 'https://app.ikena.com/checkout/resume#token=abc123' } },
    })

    const wrapper = await mountBooked(router)
    await wrapper.find('[data-submit-btn]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).not.toContain('Reserva confirmada')
    expect(wrapper.text()).toContain('Continúa en tu navegador')
  })

  it('surfaces a handoff failure inline instead of failing silently', async () => {
    const failure = new Error('Unprocessable')
    failure.response = { status: 422, data: { message: 'Este servicio no acepta citas.' } }
    api.post.mockRejectedValueOnce(failure)

    const wrapper = await mountBooked(router)
    await wrapper.find('[data-submit-btn]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-booking-error]').text()).toBe('Este servicio no acepta citas.')
    expect(Browser.open).not.toHaveBeenCalled()
    expect(wrapper.find('[data-booking-handed-off]').exists()).toBe(false)
    // The form stays on screen so the customer can retry.
    expect(wrapper.find('[data-submit-btn]').exists()).toBe(true)
  })

  it('lets the customer retry after the browser was opened (closed tab, expired token)', async () => {
    api.post.mockResolvedValueOnce({
      data: { data: { url: 'https://app.ikena.com/checkout/resume#token=abc123' } },
    })

    const wrapper = await mountBooked(router)
    await wrapper.find('[data-submit-btn]').trigger('click')
    await flushPromises()

    await wrapper.find('[data-booking-retry]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-booking-handed-off]').exists()).toBe(false)
    expect(wrapper.find('[data-submit-btn]').exists()).toBe(true)
  })
})
