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

import api from '../services/api.js'
import NavBar from '../components/NavBar.vue'
import { useAuthStore } from '../stores/auth.js'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div/>' } }],
})

function visibleText(wrapper, selector) {
  return wrapper.findAll(selector).map((el) => el.text().trim())
}

describe('NavBar — user menu (desktop)', () => {
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

  it('renders a single user-menu trigger and no menu items until opened', () => {
    const wrapper = mountAs({ name: 'Carla', role: 'student' })

    expect(wrapper.find('[data-user-menu-trigger]').exists()).toBe(true)
    // Collapsed by default — Perfil link and Salir button are not in the DOM yet.
    expect(wrapper.find('[data-user-menu-profile]').exists()).toBe(false)
    expect(wrapper.find('[data-user-menu-logout]').exists()).toBe(false)
  })

  it('opens the dropdown with exactly Perfil and Salir', async () => {
    const wrapper = mountAs({ name: 'Carla', role: 'student' })

    await wrapper.find('[data-user-menu-trigger]').trigger('click')

    expect(wrapper.find('[data-user-menu-profile]').exists()).toBe(true)
    expect(wrapper.find('[data-user-menu-logout]').exists()).toBe(true)
    expect(wrapper.find('[data-user-menu-profile]').text().trim()).toBe('Perfil')
    expect(wrapper.find('[data-user-menu-logout]').text().trim()).toBe('Salir')
  })

  it('Perfil links to the profile page', async () => {
    const wrapper = mountAs({ name: 'Carla', role: 'student' })

    await wrapper.find('[data-user-menu-trigger]').trigger('click')

    expect(wrapper.find('[data-user-menu-profile]').attributes('href')).toBe('/profile')
  })

  it('Salir logs the user out', async () => {
    api.post.mockResolvedValue({ data: {} })
    const wrapper = mountAs({ name: 'Carla', role: 'student' })

    await wrapper.find('[data-user-menu-trigger]').trigger('click')
    await wrapper.find('[data-user-menu-logout]').trigger('click')

    expect(api.post).toHaveBeenCalledWith('/logout')
  })

  it('does not render the user menu when logged out', () => {
    const wrapper = mountAs(null)

    expect(wrapper.find('[data-user-menu-trigger]').exists()).toBe(false)
  })
})
