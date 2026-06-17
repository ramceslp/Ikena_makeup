import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
    patch: vi.fn(),
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
}))

import api from '../services/api.js'
import BookingForm from '../components/booking/BookingForm.vue'
import { useBookingStore } from '../stores/booking.js'

const selectedSlot = {
  id: 1,
  scheduled_date: '2026-07-04',
  scheduled_time: '10:00',
}

const fakeService = {
  id: 1,
  title: 'Maquillaje Social',
  price: '150.00',
  deposit_percentage: 30,
}

// Deposit: 150 * 30 / 100 = 45.00

function mountForm(propsOverride = {}) {
  return mount(BookingForm, {
    props: {
      selectedSlot,
      service: fakeService,
      ...propsOverride,
    },
    global: {
      plugins: [createPinia()],
      stubs: {
        // Stub router-link if needed
      },
    },
  })
}

describe('BookingForm.vue — deposit display', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('displays the deposit amount correctly (price * deposit_percentage / 100)', () => {
    const wrapper = mountForm()
    // 150.00 * 30% = $45.00
    expect(wrapper.text()).toContain('$45.00')
  })

  it('displays the selected slot date and time', () => {
    const wrapper = mountForm()
    expect(wrapper.text()).toContain('2026-07-04')
    expect(wrapper.text()).toContain('10:00')
  })

  it('shows whatsapp input field', () => {
    const wrapper = mountForm()
    const input = wrapper.find('[data-whatsapp-input]')
    expect(input.exists()).toBe(true)
  })

  it('shows "Confirmar y Pagar Depósito" submit button', () => {
    const wrapper = mountForm()
    const btn = wrapper.find('[data-submit-btn]')
    expect(btn.exists()).toBe(true)
    expect(btn.text()).toContain('Confirmar y Pagar')
  })
})

describe('BookingForm.vue — 409 inline error', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('shows inline error message when booking store has bookingError', async () => {
    const wrapper = mountForm()
    const store = useBookingStore()
    store.bookingError = 'Este horario ya no está disponible. Por favor elige otro.'

    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Este horario ya no está disponible')
  })

  it('shows inline error after failed submission (409)', async () => {
    const error = { response: { status: 409, data: {} } }
    api.post.mockRejectedValueOnce(error)

    const wrapper = mountForm()

    // Fill whatsapp
    const input = wrapper.find('[data-whatsapp-input]')
    await input.setValue('+5930999')

    // Submit
    const btn = wrapper.find('[data-submit-btn]')
    await btn.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Este horario ya no está disponible')
  })
})

describe('BookingForm.vue — submission', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('calls createBooking with correct payload on submit', async () => {
    const fakeResult = {
      appointment: { id: 10, status: 'pending' },
      gateway_payload: { checkout_url: 'https://pay.example.com/abc' },
    }
    api.post.mockResolvedValueOnce({ status: 201, data: fakeResult })

    const wrapper = mountForm()

    const input = wrapper.find('[data-whatsapp-input]')
    await input.setValue('+5930999888')

    const btn = wrapper.find('[data-submit-btn]')
    await btn.trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/bookings', {
      service_id: 1,
      scheduled_date: '2026-07-04',
      scheduled_time: '10:00',
      whatsapp: '+5930999888',
    })
  })

  it('disables submit button when no slot is selected', () => {
    const wrapper = mountForm({ selectedSlot: null })
    const btn = wrapper.find('[data-submit-btn]')
    expect(btn.attributes('disabled')).toBeDefined()
  })
})
