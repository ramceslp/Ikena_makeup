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
import AdminNotifications from '../views/admin/AdminNotifications.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/admin/notificaciones', component: AdminNotifications, name: 'AdminNotifications' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

function log(overrides = {}) {
  return {
    id: 1,
    type: 'custom',
    title: 'Promo 2x1',
    body: 'Solo por hoy',
    route: '/cursos',
    audience: 'all',
    status: 'sent',
    recipients_count: 10,
    success_count: 9,
    failure_count: 1,
    sent_by: { id: 1, name: 'Ikena' },
    sent_at: '2026-07-29T12:04:00-05:00',
    created_at: '2026-07-29T12:04:00-05:00',
    ...overrides,
  }
}

const HISTORY_URL = '/admin/push-notifications'
const STATS_URL = '/admin/push-notifications/stats'

/** Routes GETs by URL so history and stats can be stubbed independently. */
function stubGets({ logs = [log()], meta = null, stats = { device_count: 12, push_enabled: true } } = {}) {
  api.get.mockImplementation((url) => {
    if (url === STATS_URL) return Promise.resolve({ data: { data: stats } })
    return Promise.resolve({
      data: { data: logs, meta: meta ?? { current_page: 1, last_page: 1, total: logs.length } },
    })
  })
}

function mountView(pinia) {
  return mount(AdminNotifications, { global: { plugins: [pinia, router] } })
}

describe('AdminNotifications', () => {
  let pinia

  beforeEach(() => {
    vi.clearAllMocks()
    pinia = createPinia()
    setActivePinia(pinia)
  })

  // -------------------------------------------------------------------
  // History
  // -------------------------------------------------------------------

  it('loads and renders the send history on mount', async () => {
    stubGets()
    const wrapper = mountView(pinia)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith(HISTORY_URL, { params: { page: 1 } })
    expect(wrapper.findAll('[data-log-row]')).toHaveLength(1)
    expect(wrapper.text()).toContain('Promo 2x1')
  })

  it('shows an empty state when nothing has been sent', async () => {
    stubGets({ logs: [] })
    const wrapper = mountView(pinia)
    await flushPromises()

    expect(wrapper.find('[data-empty-state]').exists()).toBe(true)
  })

  it('labels an automatic send as coming from the system', async () => {
    stubGets({ logs: [log({ type: 'course.published', sent_by: null })] })
    const wrapper = mountView(pinia)
    await flushPromises()

    expect(wrapper.text()).toContain('Sistema')
    expect(wrapper.text()).toContain('Curso')
  })

  it('shows delivery counts for a sent notification', async () => {
    stubGets({ logs: [log({ status: 'sent', success_count: 9, recipients_count: 10, failure_count: 1 })] })
    const wrapper = mountView(pinia)
    await flushPromises()

    expect(wrapper.text()).toContain('9 / 10')
    expect(wrapper.text()).toContain('1 fallaron')
  })

  /**
   * 'skipped' means nobody was reached. It must never read as a success —
   * that is the exact confusion the history exists to prevent.
   */
  it('renders a skipped send as a warning, not a success', async () => {
    stubGets({ logs: [log({ status: 'skipped' })] })
    const wrapper = mountView(pinia)
    await flushPromises()

    const badge = wrapper.find('[data-status-badge]')
    expect(badge.text()).toBe('No enviada')
    expect(badge.classes().join(' ')).toContain('error-container')
  })

  it('filters the history by type', async () => {
    stubGets()
    const wrapper = mountView(pinia)
    await flushPromises()

    await wrapper.find('[data-type-filter]').setValue('course.published')
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith(HISTORY_URL, {
      params: { page: 1, type: 'course.published' },
    })
  })

  it('paginates', async () => {
    stubGets({ meta: { current_page: 1, last_page: 3, total: 45 } })
    const wrapper = mountView(pinia)
    await flushPromises()

    await wrapper.find('[data-next-page]').trigger('click')
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith(HISTORY_URL, { params: { page: 2 } })
  })

  // -------------------------------------------------------------------
  // Stats
  // -------------------------------------------------------------------

  it('reports how many devices a broadcast would reach', async () => {
    stubGets({ stats: { device_count: 12, push_enabled: true } })
    const wrapper = mountView(pinia)
    await flushPromises()

    expect(wrapper.find('[data-device-count]').text()).toContain('12')
  })

  it('warns when push is disabled on the server', async () => {
    stubGets({ stats: { device_count: 0, push_enabled: false } })
    const wrapper = mountView(pinia)
    await flushPromises()

    expect(wrapper.find('[data-push-disabled-warning]').exists()).toBe(true)
  })

  it('does not warn when push is enabled', async () => {
    stubGets({ stats: { device_count: 5, push_enabled: true } })
    const wrapper = mountView(pinia)
    await flushPromises()

    expect(wrapper.find('[data-push-disabled-warning]').exists()).toBe(false)
  })

  // -------------------------------------------------------------------
  // Sending
  // -------------------------------------------------------------------

  it('sends a custom notification and clears the form', async () => {
    stubGets()
    api.post.mockResolvedValue({ data: { data: log({ status: 'pending' }) } })

    const wrapper = mountView(pinia)
    await flushPromises()

    await wrapper.find('[data-title-input]').setValue('Promo')
    await wrapper.find('[data-body-input]').setValue('Solo por hoy')
    await wrapper.find('[data-route-input]').setValue('/cursos')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith(HISTORY_URL, {
      title: 'Promo',
      body: 'Solo por hoy',
      route: '/cursos',
    })
    expect(wrapper.find('[data-send-success]').exists()).toBe(true)
    expect(wrapper.find('[data-title-input]').element.value).toBe('')
  })

  it('omits the route from the payload when left blank', async () => {
    stubGets()
    api.post.mockResolvedValue({ data: { data: log({ status: 'pending', route: null }) } })

    const wrapper = mountView(pinia)
    await flushPromises()

    await wrapper.find('[data-title-input]').setValue('Aviso')
    await wrapper.find('[data-body-input]').setValue('Cerramos el lunes')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith(HISTORY_URL, { title: 'Aviso', body: 'Cerramos el lunes' })
  })

  /**
   * A send that comes back 'skipped' must not be reported as delivered.
   */
  it('tells the admin plainly when a send was recorded but not delivered', async () => {
    stubGets()
    api.post.mockResolvedValue({ data: { data: log({ status: 'skipped' }) } })

    const wrapper = mountView(pinia)
    await flushPromises()

    await wrapper.find('[data-title-input]').setValue('X')
    await wrapper.find('[data-body-input]').setValue('Y')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.find('[data-send-success]').text()).toContain('NO se envió')
  })

  it('surfaces the field-level validation message from the API', async () => {
    stubGets()
    api.post.mockRejectedValue({
      response: {
        status: 422,
        data: { errors: { route: ['The route must be an internal path starting with a single "/".'] } },
      },
    })

    const wrapper = mountView(pinia)
    await flushPromises()

    await wrapper.find('[data-title-input]').setValue('X')
    await wrapper.find('[data-body-input]').setValue('Y')
    await wrapper.find('[data-route-input]').setValue('https://evil.com')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.find('[data-send-error]').text()).toContain('internal path')
  })

  it('refreshes the history after a successful send', async () => {
    stubGets()
    api.post.mockResolvedValue({ data: { data: log({ status: 'pending' }) } })

    const wrapper = mountView(pinia)
    await flushPromises()

    const callsBefore = api.get.mock.calls.filter((c) => c[0] === HISTORY_URL).length

    await wrapper.find('[data-title-input]').setValue('X')
    await wrapper.find('[data-body-input]').setValue('Y')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    const callsAfter = api.get.mock.calls.filter((c) => c[0] === HISTORY_URL).length
    expect(callsAfter).toBeGreaterThan(callsBefore)
  })

  it('keeps the send button disabled until title and body are filled', async () => {
    stubGets()
    const wrapper = mountView(pinia)
    await flushPromises()

    expect(wrapper.find('[data-send-btn]').attributes('disabled')).toBeDefined()

    await wrapper.find('[data-title-input]').setValue('X')
    await wrapper.find('[data-body-input]').setValue('Y')
    await flushPromises()

    expect(wrapper.find('[data-send-btn]').attributes('disabled')).toBeUndefined()
  })
})
