import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

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

// Router stub for redirect tests
const testRouter = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/login', name: 'Login', component: { template: '<div/>' } },
    { path: '/services/:slug', name: 'ServiceDetail', component: { template: '<div/>' } },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

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
    // 409 branch re-fetches available slots
    api.get.mockResolvedValueOnce({ data: { data: [] } })

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

describe('BookingForm.vue — 401 redirect to login', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    await testRouter.push('/services/maquillaje-social')
    await testRouter.isReady()
  })

  it('redirects to Login with redirect query on 401 response', async () => {
    const error = { response: { status: 401, data: {} } }
    api.post.mockRejectedValueOnce(error)

    const wrapper = mount(BookingForm, {
      props: { selectedSlot, service: fakeService },
      global: { plugins: [testRouter, createPinia()] },
    })

    const input = wrapper.find('[data-whatsapp-input]')
    await input.setValue('+5930999')

    const btn = wrapper.find('[data-submit-btn]')
    await btn.trigger('click')
    await flushPromises()

    // After 401 the router must have navigated to /login
    expect(testRouter.currentRoute.value.name).toBe('Login')
    expect(testRouter.currentRoute.value.query.redirect).toBeTruthy()
  })
})

describe('BookingForm.vue — whatsapp validation', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('does not call createBooking when whatsapp is empty', async () => {
    const wrapper = mountForm()

    // Do NOT fill in whatsapp (leave it empty)
    const btn = wrapper.find('[data-submit-btn]')
    await btn.trigger('click')
    await flushPromises()

    // createBooking must NOT have been called
    expect(api.post).not.toHaveBeenCalled()
  })

  it('shows validation message when whatsapp is empty and form is submitted', async () => {
    const wrapper = mountForm()

    const btn = wrapper.find('[data-submit-btn]')
    await btn.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('WhatsApp')
  })
})
