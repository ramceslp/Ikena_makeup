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

  // [Judgment Day fix, PR8d Round 1]: a user who was redirected to /login
  // with a `redirect` query (see router/index.js's resolveGuard, e.g. after
  // direct-linking to the auth-gated /profile route while logged out) must
  // land back on that original destination after signing in, not always on
  // Home. Regression guard for the bug where handleGoogleSignIn() ignored
  // route.query.redirect entirely.
  it('navigates to route.query.redirect instead of Home when present', async () => {
    signInWithGoogle.mockResolvedValueOnce({ idToken: 'native-id-token', accessToken: null, profile: {} })
    router.addRoute({ path: '/profile', component: { template: '<div data-profile/>' }, name: 'profile' })
    await router.push('/login?redirect=%2Fprofile')

    const wrapper = mount(Login, { global: { plugins: [router] } })
    await wrapper.find('[data-google-signin]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/profile')
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
    const backendError = { response: { data: { message: 'Token de Google inválido' } } }
    loginWithGoogleMock.mockRejectedValueOnce(backendError)
    const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {})

    const wrapper = mount(Login, { global: { plugins: [router] } })
    await wrapper.find('[data-google-signin]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-login-error]').text()).toContain('Token de Google inválido')
    expect(router.currentRoute.value.path).toBe('/login')
    // Non-cancel failures must be logged (matching api.js's convention of
    // console.error-ing swallowed failures), not silently absorbed.
    expect(consoleErrorSpy).toHaveBeenCalledWith('Google Sign-In failed:', backendError)

    consoleErrorSpy.mockRestore()
  })

  // [DEFECT 2 fix] Login renders with meta.hideChrome: true (no top bar, no
  // bottom tab bar), so the only way back was the Android back gesture --
  // invisible and undiscoverable (Skill: escape-routes, gesture-alternative).
  // A visible close/back button now covers this. It must not blindly call
  // router.back(): a user who deep-links straight into /login has no prior
  // in-app history entry to go back to, and blindly calling back() would
  // leave the app (or do nothing useful) instead of landing somewhere safe.
  describe('close/back button [Spec: escape-routes]', () => {
    it('renders a labeled, real <button> close control', () => {
      const wrapper = mount(Login, { global: { plugins: [router] } })

      const closeBtn = wrapper.find('[data-login-close]')
      expect(closeBtn.exists()).toBe(true)
      expect(closeBtn.element.tagName).toBe('BUTTON')
      expect(closeBtn.attributes('aria-label')).toBeTruthy()
    })

    it('goes back to the previous screen when in-app history exists', async () => {
      // vue-router's createWebHistory writes {back, current, forward, ...}
      // onto window.history.state on every navigation, so `state.back` being
      // non-null is the real signal (in production) that there is a prior
      // in-app entry to return to.
      window.history.replaceState({ back: '/' }, '', '/login')
      const backSpy = vi.spyOn(router, 'back').mockImplementation(() => {})

      const wrapper = mount(Login, { global: { plugins: [router] } })
      await wrapper.find('[data-login-close]').trigger('click')

      expect(backSpy).toHaveBeenCalledTimes(1)
      backSpy.mockRestore()
    })

    it('navigates to home instead of calling back() when there is no history (deep link)', async () => {
      // No prior entry: state.back is absent/null, as it would be on a cold
      // app start that lands straight on /login.
      window.history.replaceState({}, '', '/login')
      const pushSpy = vi.spyOn(router, 'push')
      const backSpy = vi.spyOn(router, 'back').mockImplementation(() => {})

      const wrapper = mount(Login, { global: { plugins: [router] } })
      await wrapper.find('[data-login-close]').trigger('click')

      expect(backSpy).not.toHaveBeenCalled()
      expect(pushSpy).toHaveBeenCalledWith({ name: 'home' })
      backSpy.mockRestore()
      pushSpy.mockRestore()
    })

    it('does NOT target route.query.redirect -- that is the post-login destination, not the close target', async () => {
      router.addRoute({ path: '/profile', component: { template: '<div data-profile/>' }, name: 'profile' })
      window.history.replaceState({}, '', '/login')
      await router.push('/login?redirect=%2Fprofile')
      const pushSpy = vi.spyOn(router, 'push')

      const wrapper = mount(Login, { global: { plugins: [router] } })
      await wrapper.find('[data-login-close]').trigger('click')

      expect(pushSpy).toHaveBeenCalledWith({ name: 'home' })
      pushSpy.mockRestore()
    })
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
