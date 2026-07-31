/**
 * "Mi agenda" — the customer's own appointments.
 *
 * Until now appointments were readable only through the ADMIN endpoint, so a
 * customer could not check when their next appointment was. Covers both the
 * store's two-scope model and the section that renders it.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

import api from '../services/api.js'
import { useAppointmentsStore } from '../stores/appointments.js'
import AgendaSection from '../components/profile/AgendaSection.vue'

const upcoming = {
  id: 11,
  scheduled_date: '2026-08-01',
  scheduled_time: '10:00',
  scheduled_end_time: '12:00',
  status: 'paid',
  deposit_amount_cents: 4000,
  service: { id: 5, title: 'Maquillaje de Novia', slug: 'maquillaje-novia', thumbnail: null, duration_hours: 2 },
  order: { id: 3, status: 'paid', amount_cents: 4000, currency: 'USD' },
}

const past = { ...upcoming, id: 12, scheduled_date: '2026-06-01', status: 'cancelled' }

function page(items) {
  return { data: { data: items, meta: { current_page: 1, total: items.length } } }
}

describe('appointments store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetches a scope with the scope + page query params', async () => {
    api.get.mockResolvedValueOnce(page([upcoming]))

    const store = useAppointmentsStore()
    await store.fetchAppointments('upcoming')

    expect(api.get).toHaveBeenCalledWith('/profile/appointments', {
      params: { scope: 'upcoming', page: 1 },
    })
    expect(store.upcoming.items).toHaveLength(1)
    expect(store.upcoming.error).toBeNull()
    expect(store.upcoming.loading).toBe(false)
  })

  it('keeps the two scopes in independent slices', async () => {
    api.get
      .mockResolvedValueOnce(page([upcoming]))
      .mockResolvedValueOnce(page([past]))

    const store = useAppointmentsStore()
    await store.fetchAppointments('upcoming')
    await store.fetchAppointments('past')

    expect(store.upcoming.items.map((a) => a.id)).toEqual([11])
    expect(store.past.items.map((a) => a.id)).toEqual([12])
  })

  it('exposes the next appointment, trusting the server sort', async () => {
    api.get.mockResolvedValueOnce(page([upcoming, { ...upcoming, id: 13 }]))

    const store = useAppointmentsStore()
    await store.fetchAppointments('upcoming')

    expect(store.nextAppointment.id).toBe(11)
  })

  it('returns null for the next appointment when there are none', () => {
    expect(useAppointmentsStore().nextAppointment).toBeNull()
  })

  it('records the error on the failing scope only', async () => {
    const failure = new Error('Server Error')
    failure.response = { status: 500, data: { message: 'Algo salió mal' } }
    api.get.mockRejectedValueOnce(failure).mockResolvedValueOnce(page([past]))

    const store = useAppointmentsStore()
    await store.fetchAppointments('upcoming')
    await store.fetchAppointments('past')

    expect(store.upcoming.error).toBe('Algo salió mal')
    expect(store.past.error).toBeNull()
    expect(store.past.items).toHaveLength(1)
  })

  /**
   * allSettled, not all: one scope failing must not blank out the other.
   */
  it('fetchAll loads both scopes even when one of them fails', async () => {
    api.get
      .mockRejectedValueOnce(new Error('Network Error'))
      .mockResolvedValueOnce(page([past]))

    const store = useAppointmentsStore()
    await store.fetchAll()

    expect(store.upcoming.error).toBeTruthy()
    expect(store.past.items).toHaveLength(1)
  })

  it('ignores an unknown scope instead of crashing on an undefined slice', async () => {
    const store = useAppointmentsStore()
    await store.fetchAppointments('whenever')

    expect(api.get).not.toHaveBeenCalled()
  })
})

function mountSection() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div/>' } },
      { path: '/services', component: { template: '<div/>' }, name: 'services' },
    ],
  })
  return mount(AgendaSection, { global: { plugins: [router] } })
}

describe('AgendaSection.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('shows upcoming appointments by default', async () => {
    const store = useAppointmentsStore()
    store.upcoming.items = [upcoming]
    store.past.items = [past]

    const wrapper = mountSection()
    await flushPromises()

    expect(wrapper.findAll('[data-agenda-row]')).toHaveLength(1)
    expect(wrapper.text()).toContain('Maquillaje de Novia')
    expect(wrapper.text()).toContain('10:00 – 12:00')
  })

  it('switches to the past scope without refiltering the upcoming list', async () => {
    const store = useAppointmentsStore()
    store.upcoming.items = [upcoming]
    store.past.items = [past, { ...past, id: 14 }]

    const wrapper = mountSection()
    await wrapper.find('[data-agenda-tab="past"]').trigger('click')
    await flushPromises()

    expect(wrapper.findAll('[data-agenda-row]')).toHaveLength(2)
    expect(wrapper.find('[data-agenda-tab="past"]').attributes('aria-selected')).toBe('true')
  })

  /**
   * scheduled_date is a bare YYYY-MM-DD. Parsed as UTC midnight it renders as
   * the previous day at UTC-5, which is Ecuador — every appointment would show
   * a day early.
   */
  it('renders the scheduled date in local time, not shifted a day back', async () => {
    const store = useAppointmentsStore()
    store.upcoming.items = [upcoming]

    const wrapper = mountSection()
    await flushPromises()

    const expected = new Intl.DateTimeFormat('es', {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
    }).format(new Date('2026-08-01T00:00:00'))

    expect(wrapper.find('[data-agenda-row]').text()).toContain(expected)
  })

  it('renders a cancelled appointment with its status rather than hiding it', async () => {
    const store = useAppointmentsStore()
    store.upcoming.items = [{ ...upcoming, status: 'cancelled' }]

    const wrapper = mountSection()
    await flushPromises()

    expect(wrapper.text()).toContain('Cancelada')
  })

  it('distinguishes an unpaid appointment from a paid one', async () => {
    const store = useAppointmentsStore()
    store.upcoming.items = [{ ...upcoming, status: 'pending' }]

    const wrapper = mountSection()
    await flushPromises()

    expect(wrapper.text()).toContain('Pago pendiente')
  })

  it('shows a scope-specific empty state with a way forward', async () => {
    const wrapper = mountSection()
    await flushPromises()

    expect(wrapper.find('[data-agenda-empty]').text()).toContain('No tienes citas próximas.')
    expect(wrapper.find('[data-browse-services]').exists()).toBe(true)
  })

  it('does not offer to book from the past-scope empty state', async () => {
    const wrapper = mountSection()
    await wrapper.find('[data-agenda-tab="past"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-agenda-empty]').text()).toContain('Todavía no tienes citas pasadas.')
    expect(wrapper.find('[data-browse-services]').exists()).toBe(false)
  })

  it('renders an error with a retry that refetches the current scope', async () => {
    const store = useAppointmentsStore()
    store.upcoming.error = 'Error al cargar tu agenda'

    const wrapper = mountSection()
    await flushPromises()

    expect(wrapper.find('[data-agenda-error]').text()).toContain('Error al cargar tu agenda')

    api.get.mockResolvedValueOnce(page([upcoming]))
    await wrapper.find('[data-agenda-retry]').trigger('click')
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/profile/appointments', {
      params: { scope: 'upcoming', page: 1 },
    })
  })

  it('shows a skeleton, not an empty state, while loading', async () => {
    const store = useAppointmentsStore()
    store.upcoming.loading = true

    const wrapper = mountSection()
    await flushPromises()

    expect(wrapper.find('[data-agenda-skeleton]').exists()).toBe(true)
    expect(wrapper.find('[data-agenda-empty]').exists()).toBe(false)
  })
})
