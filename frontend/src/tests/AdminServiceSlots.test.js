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
import AdminServiceSlots from '../views/admin/AdminServiceSlots.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/admin/services/:id/slots', name: 'AdminServiceSlots', component: { template: '<div/>' } },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

const fakeSlots = [
  { id: 1, day_of_week: 1, specific_date: null, start_time: '10:00', capacity: 1, is_blocked: false },
  { id: 2, day_of_week: null, specific_date: '2026-07-04', start_time: '14:00', capacity: 1, is_blocked: false },
]

async function mountAdminSlots(routeParams = {}) {
  await router.push({ name: 'AdminServiceSlots', params: { id: '1', ...routeParams } })
  await router.isReady()
  return mount(AdminServiceSlots, {
    global: { plugins: [router, createPinia()] },
  })
}

describe('AdminServiceSlots.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetches slots on mount via GET /admin/services/{id}/slots', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeSlots } })

    const wrapper = await mountAdminSlots()
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/services/1/slots')
  })

  it('renders a row for each slot', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeSlots } })

    const wrapper = await mountAdminSlots()
    await flushPromises()

    const rows = wrapper.findAll('[data-slot-row]')
    expect(rows).toHaveLength(2)
  })

  it('renders slot start_time for each slot', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeSlots } })

    const wrapper = await mountAdminSlots()
    await flushPromises()

    expect(wrapper.text()).toContain('10:00')
    expect(wrapper.text()).toContain('14:00')
  })

  it('shows empty state when no slots exist', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = await mountAdminSlots()
    await flushPromises()

    expect(wrapper.find('[data-slot-row]').exists()).toBe(false)
    expect(wrapper.text()).toContain('No hay horarios configurados')
  })

  it('shows create slot button', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = await mountAdminSlots()
    await flushPromises()

    expect(wrapper.find('[data-add-slot-btn]').exists()).toBe(true)
  })

  it('shows delete button for each slot', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeSlots } })

    const wrapper = await mountAdminSlots()
    await flushPromises()

    const deleteBtns = wrapper.findAll('[data-delete-slot-btn]')
    expect(deleteBtns).toHaveLength(2)
  })

  it('calls DELETE when delete button clicked and confirmed', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeSlots } })
    api.delete.mockResolvedValueOnce({})
    api.get.mockResolvedValueOnce({ data: { data: [fakeSlots[1]] } })

    // Mock window.confirm to return true
    vi.spyOn(window, 'confirm').mockReturnValueOnce(true)

    const wrapper = await mountAdminSlots()
    await flushPromises()

    const deleteBtns = wrapper.findAll('[data-delete-slot-btn]')
    await deleteBtns[0].trigger('click')
    await flushPromises()

    expect(api.delete).toHaveBeenCalledWith('/admin/services/1/slots/1')
  })

  it('shows create form when add button clicked', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = await mountAdminSlots()
    await flushPromises()

    const addBtn = wrapper.find('[data-add-slot-btn]')
    await addBtn.trigger('click')

    expect(wrapper.find('[data-slot-form]').exists()).toBe(true)
  })
})
