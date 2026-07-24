import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

// ---------------------------------------------------------------------------
// Mock the native sign-in service and the auth store BEFORE importing
// Login.vue, so the component's click handler drives controllable fakes
// instead of the real plugin/HTTP call.
// ---------------------------------------------------------------------------
vi.mock('../services/googleAuth.js', () => ({
  signInWithGoogle: vi.fn(),
}))

vi.mock('../stores/auth.js', () => ({
  useAuthStore: vi.fn(),
}))

import { signInWithGoogle } from '../services/googleAuth.js'
import { useAuthStore } from '../stores/auth.js'
import Login from '../views/Login.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div data-home/>' }, name: 'home' },
      { path: '/login', component: Login, name: 'login' },
    ],
  })
}

describe('Login.vue (App) — native Google Sign-In', () => {
  let router
  let loginWithGoogleMock

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    loginWithGoogleMock = vi.fn().mockResolvedValue({ user: { id: 1 }, token: 'tok' })
    useAuthStore.mockReturnValue({ loginWithGoogle: loginWithGoogleMock })
    router = makeRouter()
    await router.push('/login')
  })

  it('renders a Google sign-in button', () => {
    const wrapper = mount(Login, { global: { plugins: [router] } })

    expect(wrapper.find('[data-google-signin]').exists()).toBe(true)
    expect(wrapper.find('[data-google-signin]').text()).toMatch(/google/i)
  })

  it('on tap, signs in via the native plugin, posts the resulting idToken, and navigates to Home', async () => {
    signInWithGoogle.mockResolvedValueOnce({ idToken: 'native-id-token', accessToken: null, profile: {} })

    const wrapper = mount(Login, { global: { plugins: [router] } })
    await wrapper.find('[data-google-signin]').trigger('click')
    await flushPromises()

    expect(signInWithGoogle).toHaveBeenCalledTimes(1)
    expect(loginWithGoogleMock).toHaveBeenCalledWith('native-id-token')
    expect(router.currentRoute.value.path).toBe('/')
  })

  it('cancelling the native sheet returns to the login screen with no token posted and no crash/error shown', async () => {
    const cancelError = new Error('User cancelled the login flow')
    cancelError.code = 'USER_CANCELLED'
    signInWithGoogle.mockRejectedValueOnce(cancelError)

    const wrapper = mount(Login, { global: { plugins: [router] } })
    await wrapper.find('[data-google-signin]').trigger('click')
    await flushPromises()

    expect(loginWithGoogleMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-login-error]').exists()).toBe(false)
    expect(router.currentRoute.value.path).toBe('/login')
  })

  it('shows an error message and stays on login when the backend call fails for a real (non-cancel) reason', async () => {
    signInWithGoogle.mockResolvedValueOnce({ idToken: 'native-id-token', accessToken: null, profile: {} })
    loginWithGoogleMock.mockRejectedValueOnce({ response: { data: { message: 'Token de Google inválido' } } })

    const wrapper = mount(Login, { global: { plugins: [router] } })
    await wrapper.find('[data-google-signin]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-login-error]').text()).toContain('Token de Google inválido')
    expect(router.currentRoute.value.path).toBe('/login')
  })

  it('disables the button while a sign-in attempt is in flight', async () => {
    let resolveSignIn
    signInWithGoogle.mockReturnValueOnce(
      new Promise((resolve) => {
        resolveSignIn = resolve
      })
    )

    const wrapper = mount(Login, { global: { plugins: [router] } })
    const button = wrapper.find('[data-google-signin]')
    await button.trigger('click')

    expect(wrapper.find('[data-google-signin]').attributes('disabled')).toBeDefined()

    resolveSignIn({ idToken: 'native-id-token', accessToken: null, profile: {} })
    await flushPromises()

    expect(wrapper.find('[data-google-signin]').attributes('disabled')).toBeUndefined()
  })
})
