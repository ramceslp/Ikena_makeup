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
import AdminAppointments from '../views/admin/AdminAppointments.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/admin/appointments', name: 'AdminAppointments', component: { template: '<div/>' } },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

const fakeAppointments = [
  {
    id: 1,
    status: 'pending',
    scheduled_date: '2026-07-04',
    scheduled_time: '10:00',
    whatsapp: '+5930999',
    deposit_amount_cents: 4500,
    service: { title: 'Maquillaje Social' },
    user: { name: 'Ana García', email: 'ana@example.com' },
  },
  {
    id: 2,
    status: 'paid',
    scheduled_date: '2026-07-07',
    scheduled_time: '14:00',
    whatsapp: '+5931000',
    deposit_amount_cents: 5000,
    service: { title: 'Masterclass Novia' },
    user: { name: 'María López', email: 'maria@example.com' },
  },
]

async function mountAdminAppointments() {
  await router.push('/admin/appointments')
  await router.isReady()
  return mount(AdminAppointments, {
    global: { plugins: [router, createPinia()] },
  })
}

describe('AdminAppointments.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetches appointments on mount via GET /admin/appointments', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeAppointments, meta: {} } })

    const wrapper = await mountAdminAppointments()
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/appointments', { params: {} })
  })

  it('renders a row for each appointment', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeAppointments, meta: {} } })

    const wrapper = await mountAdminAppointments()
    await flushPromises()

    const rows = wrapper.findAll('[data-appointment-row]')
    expect(rows).toHaveLength(2)
  })

  it('renders service title, user name, and scheduled date/time', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeAppointments, meta: {} } })

    const wrapper = await mountAdminAppointments()
    await flushPromises()

    expect(wrapper.text()).toContain('Maquillaje Social')
    expect(wrapper.text()).toContain('Ana García')
    expect(wrapper.text()).toContain('10:00')
  })

  it('renders whatsapp contact for each appointment', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeAppointments, meta: {} } })

    const wrapper = await mountAdminAppointments()
    await flushPromises()

    expect(wrapper.text()).toContain('+5930999')
  })

  it('shows empty state when no appointments exist', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const wrapper = await mountAdminAppointments()
    await flushPromises()

    expect(wrapper.find('[data-appointment-row]').exists()).toBe(false)
    expect(wrapper.text()).toContain('No hay citas')
  })

  it('calls mark-paid PATCH when mark-paid button clicked', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeAppointments, meta: {} } })
    api.patch.mockResolvedValueOnce({ data: { data: { ...fakeAppointments[0], status: 'paid' } } })
    api.get.mockResolvedValueOnce({ data: { data: fakeAppointments, meta: {} } })

    const wrapper = await mountAdminAppointments()
    await flushPromises()

    const markPaidBtns = wrapper.findAll('[data-mark-paid-btn]')
    expect(markPaidBtns.length).toBeGreaterThan(0)

    await markPaidBtns[0].trigger('click')
    await flushPromises()

    expect(api.patch).toHaveBeenCalledWith('/admin/appointments/1/mark-paid')
  })

  it('calls cancel PATCH when cancel button clicked and confirmed', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeAppointments, meta: {} } })
    api.patch.mockResolvedValueOnce({ data: { data: { ...fakeAppointments[0], status: 'cancelled' } } })
    api.get.mockResolvedValueOnce({ data: { data: fakeAppointments, meta: {} } })

    vi.spyOn(window, 'confirm').mockReturnValueOnce(true)

    const wrapper = await mountAdminAppointments()
    await flushPromises()

    const cancelBtns = wrapper.findAll('[data-cancel-btn]')
    expect(cancelBtns.length).toBeGreaterThan(0)

    await cancelBtns[0].trigger('click')
    await flushPromises()

    expect(api.patch).toHaveBeenCalledWith('/admin/appointments/1/cancel')
  })

  it('has status filter that re-fetches on change', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeAppointments, meta: {} } })
    api.get.mockResolvedValueOnce({ data: { data: [fakeAppointments[0]], meta: {} } })

    const wrapper = await mountAdminAppointments()
    await flushPromises()

    const statusFilter = wrapper.find('[data-status-filter]')
    expect(statusFilter.exists()).toBe(true)

    await statusFilter.setValue('pending')
    await flushPromises()

    expect(api.get).toHaveBeenCalledTimes(2)
    expect(api.get).toHaveBeenLastCalledWith('/admin/appointments', {
      params: expect.objectContaining({ status: 'pending' }),
    })
  })
})
