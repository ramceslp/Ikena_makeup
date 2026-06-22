import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
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

import NavBar from '../components/NavBar.vue'
import { useAuthStore } from '../stores/auth.js'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div/>' } }],
})

function adminHrefs(wrapper) {
  return wrapper
    .findAll('a')
    .map((l) => l.attributes('href'))
    .filter((h) => h && h.startsWith('/admin/'))
}

describe('NavBar — unified admin menu', () => {
  let pinia

  beforeEach(() => {
    localStorage.clear()
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
  })

  afterEach(() => {
    localStorage.clear()
  })

  function mountAs(user) {
    const auth = useAuthStore()
    auth.token = user ? 'token-123' : null
    auth.user = user
    return mount(NavBar, { global: { plugins: [pinia, router] } })
  }

  it('renders a single Admin trigger and no admin links until opened', () => {
    const wrapper = mountAs({ name: 'Admin', role: 'admin' })

    expect(wrapper.find('[data-admin-menu-trigger]').exists()).toBe(true)
    // Collapsed by default — the section links are not in the DOM yet.
    expect(adminHrefs(wrapper)).toHaveLength(0)
  })

  it('opens the dropdown with exactly the four admin sections', async () => {
    const wrapper = mountAs({ name: 'Admin', role: 'admin' })

    await wrapper.find('[data-admin-menu-trigger]').trigger('click')

    expect(adminHrefs(wrapper).sort()).toEqual([
      '/admin/appointments',
      '/admin/certificate',
      '/admin/noticias',
      '/admin/products',
      '/admin/services',
    ])
  })

  it('does not expose the admin menu to a non-admin user', () => {
    const wrapper = mountAs({ name: 'Carlos', role: 'student' })

    expect(wrapper.find('[data-admin-menu-trigger]').exists()).toBe(false)
    expect(adminHrefs(wrapper)).toHaveLength(0)
  })
})
