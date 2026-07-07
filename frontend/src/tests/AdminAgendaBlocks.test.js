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
import AdminAgendaBlocks from '../views/admin/AdminAgendaBlocks.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/admin/agenda', name: 'AdminAgendaBlocks', component: { template: '<div/>' } },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

const fakeBlocks = [
  { id: 1, day_of_week: 1, specific_date: null, open_time: '09:00', close_time: '18:00', concurrency_limit: 3, soft_threshold: 2, is_blocked: false },
  { id: 2, day_of_week: null, specific_date: '2026-07-25', open_time: '10:00', close_time: '14:00', concurrency_limit: 2, soft_threshold: null, is_blocked: false },
]

async function mountAdminAgenda() {
  await router.push({ name: 'AdminAgendaBlocks' })
  await router.isReady()
  return mount(AdminAgendaBlocks, {
    global: { plugins: [router, createPinia()] },
  })
}

describe('AdminAgendaBlocks.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetches agenda blocks on mount via GET /admin/agenda', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeBlocks } })

    await mountAdminAgenda()
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/agenda')
  })

  it('renders a row for each agenda block', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeBlocks } })

    const wrapper = await mountAdminAgenda()
    await flushPromises()

    const rows = wrapper.findAll('[data-agenda-row]')
    expect(rows).toHaveLength(2)
  })

  it('renders open_time and close_time for each block', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeBlocks } })

    const wrapper = await mountAdminAgenda()
    await flushPromises()

    expect(wrapper.text()).toContain('09:00')
    expect(wrapper.text()).toContain('18:00')
    expect(wrapper.text()).toContain('10:00')
    expect(wrapper.text()).toContain('14:00')
  })

  it('renders the specific date for a one-off block', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeBlocks } })

    const wrapper = await mountAdminAgenda()
    await flushPromises()

    expect(wrapper.text()).toContain('2026-07-25')
  })

  it('shows empty state when no blocks exist', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = await mountAdminAgenda()
    await flushPromises()

    expect(wrapper.find('[data-agenda-row]').exists()).toBe(false)
    expect(wrapper.text()).toContain('No hay bloques de agenda configurados')
  })

  it('shows the create block button', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = await mountAdminAgenda()
    await flushPromises()

    expect(wrapper.find('[data-add-agenda-btn]').exists()).toBe(true)
  })

  it('shows create form when add button clicked, defaulting to weekly recurrence mode', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = await mountAdminAgenda()
    await flushPromises()

    await wrapper.find('[data-add-agenda-btn]').trigger('click')

    expect(wrapper.find('[data-agenda-form]').exists()).toBe(true)
    expect(wrapper.find('[data-recurrence-weekly]').element.checked).toBe(true)
    expect(wrapper.find('[data-day-of-week-select]').exists()).toBe(true)
    expect(wrapper.find('[data-specific-date-input]').exists()).toBe(false)
  })

  it('switching to specific-date mode hides the day select and shows a date input (XOR)', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = await mountAdminAgenda()
    await flushPromises()
    await wrapper.find('[data-add-agenda-btn]').trigger('click')
    await wrapper.find('[data-recurrence-specific]').setValue(true)

    expect(wrapper.find('[data-day-of-week-select]').exists()).toBe(false)
    expect(wrapper.find('[data-specific-date-input]').exists()).toBe(true)
  })

  it('creating a weekly block sends day_of_week and null specific_date (XOR enforced)', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })
    api.post.mockResolvedValueOnce({
      data: { data: { id: 9, day_of_week: 2, specific_date: null, open_time: '09:00', close_time: '12:00', concurrency_limit: 2, soft_threshold: null, is_blocked: false } },
    })
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = await mountAdminAgenda()
    await flushPromises()
    await wrapper.find('[data-add-agenda-btn]').trigger('click')

    await wrapper.find('[data-day-of-week-select]').setValue('2')
    await wrapper.find('[data-open-time-input]').setValue('09:00')
    await wrapper.find('[data-close-time-input]').setValue('12:00')
    await wrapper.find('[data-concurrency-limit-input]').setValue('2')
    await wrapper.find('[data-agenda-form-submit]').trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/admin/agenda', {
      day_of_week: 2,
      specific_date: null,
      open_time: '09:00',
      close_time: '12:00',
      concurrency_limit: 2,
      soft_threshold: null,
      is_blocked: false,
    })
  })

  it('creating a specific-date block sends specific_date and null day_of_week (XOR enforced)', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })
    api.post.mockResolvedValueOnce({
      data: { data: { id: 9, day_of_week: null, specific_date: '2026-08-01', open_time: '10:00', close_time: '14:00', concurrency_limit: 2, soft_threshold: 1, is_blocked: false } },
    })
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = await mountAdminAgenda()
    await flushPromises()
    await wrapper.find('[data-add-agenda-btn]').trigger('click')
    await wrapper.find('[data-recurrence-specific]').setValue(true)

    await wrapper.find('[data-specific-date-input]').setValue('2026-08-01')
    await wrapper.find('[data-open-time-input]').setValue('10:00')
    await wrapper.find('[data-close-time-input]').setValue('14:00')
    await wrapper.find('[data-concurrency-limit-input]').setValue('2')
    await wrapper.find('[data-soft-threshold-input]').setValue('1')
    await wrapper.find('[data-agenda-form-submit]').trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/admin/agenda', {
      day_of_week: null,
      specific_date: '2026-08-01',
      open_time: '10:00',
      close_time: '14:00',
      concurrency_limit: 2,
      soft_threshold: 1,
      is_blocked: false,
    })
  })

  it('shows a client-side validation error when submitting specific-date mode without a date', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = await mountAdminAgenda()
    await flushPromises()
    await wrapper.find('[data-add-agenda-btn]').trigger('click')
    await wrapper.find('[data-recurrence-specific]').setValue(true)
    await wrapper.find('[data-open-time-input]').setValue('10:00')
    await wrapper.find('[data-close-time-input]').setValue('14:00')

    await wrapper.find('[data-agenda-form-submit]').trigger('click')
    await flushPromises()

    expect(api.post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Selecciona una fecha específica')
  })

  it('shows the server overlap error message when creation fails with 422', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })
    const overlapError = {
      response: { status: 422, data: { message: 'El bloque se superpone con un horario existente.' } },
    }
    api.post.mockRejectedValueOnce(overlapError)

    const wrapper = await mountAdminAgenda()
    await flushPromises()
    await wrapper.find('[data-add-agenda-btn]').trigger('click')
    await wrapper.find('[data-day-of-week-select]').setValue('1')
    await wrapper.find('[data-open-time-input]').setValue('09:00')
    await wrapper.find('[data-close-time-input]').setValue('12:00')

    await wrapper.find('[data-agenda-form-submit]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('El bloque se superpone con un horario existente.')
  })

  it('opens the edit form pre-filled with the block data', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeBlocks } })

    const wrapper = await mountAdminAgenda()
    await flushPromises()

    await wrapper.findAll('[data-edit-agenda-btn]')[0].trigger('click')

    expect(wrapper.find('[data-agenda-form]').exists()).toBe(true)
    expect(wrapper.find('[data-open-time-input]').element.value).toBe('09:00')
    expect(wrapper.find('[data-close-time-input]').element.value).toBe('18:00')
  })

  it('shows a delete button for each block and calls DELETE when confirmed', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeBlocks } })
    api.delete.mockResolvedValueOnce({})
    api.get.mockResolvedValueOnce({ data: { data: [fakeBlocks[1]] } })
    vi.spyOn(window, 'confirm').mockReturnValueOnce(true)

    const wrapper = await mountAdminAgenda()
    await flushPromises()

    const deleteBtns = wrapper.findAll('[data-delete-agenda-btn]')
    expect(deleteBtns).toHaveLength(2)

    await deleteBtns[0].trigger('click')
    await flushPromises()

    expect(api.delete).toHaveBeenCalledWith('/admin/agenda/1')
  })

  it('does not delete when the confirmation is cancelled', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeBlocks } })
    vi.spyOn(window, 'confirm').mockReturnValueOnce(false)

    const wrapper = await mountAdminAgenda()
    await flushPromises()

    await wrapper.findAll('[data-delete-agenda-btn]')[0].trigger('click')
    await flushPromises()

    expect(api.delete).not.toHaveBeenCalled()
  })
})
