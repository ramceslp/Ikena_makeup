import { describe, it, expect, vi, beforeEach } from 'vitest'

// ---------------------------------------------------------------------------
// Mock @capgo/capacitor-social-login and env.js BEFORE importing the module
// under test, so signInWithGoogle() drives a controllable fake instead of
// the real native plugin bridge.
// ---------------------------------------------------------------------------
vi.mock('@capgo/capacitor-social-login', () => ({
  SocialLogin: {
    initialize: vi.fn().mockResolvedValue(undefined),
    login: vi.fn(),
  },
}))

vi.mock('../config/env.js', () => ({
  GOOGLE_WEB_CLIENT_ID: 'test-web-client-id.apps.googleusercontent.com',
}))

describe('googleAuth service', () => {
  beforeEach(() => {
    vi.resetModules()
    vi.clearAllMocks()
  })

  it('initializes the plugin with the configured Google web client ID before logging in', async () => {
    const { SocialLogin } = await import('@capgo/capacitor-social-login')
    SocialLogin.login.mockResolvedValueOnce({
      provider: 'google',
      result: { idToken: 'tok', accessToken: null, profile: {}, responseType: 'online' },
    })

    const { signInWithGoogle } = await import('../services/googleAuth.js')
    await signInWithGoogle()

    expect(SocialLogin.initialize).toHaveBeenCalledWith({
      google: { webClientId: 'test-web-client-id.apps.googleusercontent.com' },
    })
    expect(SocialLogin.login).toHaveBeenCalledWith({ provider: 'google', options: {} })
  })

  it('returns the idToken/accessToken/profile from a successful login', async () => {
    const { SocialLogin } = await import('@capgo/capacitor-social-login')
    SocialLogin.login.mockResolvedValueOnce({
      provider: 'google',
      result: {
        idToken: 'my-id-token',
        accessToken: { token: 'access-abc' },
        profile: { email: 'a@b.com' },
        responseType: 'online',
      },
    })

    const { signInWithGoogle } = await import('../services/googleAuth.js')
    const result = await signInWithGoogle()

    expect(result.idToken).toBe('my-id-token')
    expect(result.accessToken).toEqual({ token: 'access-abc' })
    expect(result.profile).toEqual({ email: 'a@b.com' })
  })

  it('only initializes once across multiple sign-in attempts (idempotent init)', async () => {
    const { SocialLogin } = await import('@capgo/capacitor-social-login')
    SocialLogin.login.mockResolvedValue({
      provider: 'google',
      result: { idToken: 'tok', accessToken: null, profile: {}, responseType: 'online' },
    })

    const { signInWithGoogle } = await import('../services/googleAuth.js')
    await signInWithGoogle()
    await signInWithGoogle()

    expect(SocialLogin.initialize).toHaveBeenCalledTimes(1)
    expect(SocialLogin.login).toHaveBeenCalledTimes(2)
  })

  it('propagates the USER_CANCELLED error as-is when the user cancels the native sheet', async () => {
    const { SocialLogin } = await import('@capgo/capacitor-social-login')
    const cancelError = new Error('User cancelled the login flow')
    cancelError.code = 'USER_CANCELLED'
    SocialLogin.login.mockRejectedValueOnce(cancelError)

    const { signInWithGoogle } = await import('../services/googleAuth.js')

    await expect(signInWithGoogle()).rejects.toMatchObject({ code: 'USER_CANCELLED' })
  })

  it('propagates a non-cancellation error as-is (e.g. plugin/config failure)', async () => {
    const { SocialLogin } = await import('@capgo/capacitor-social-login')
    const configError = new Error('Google Play Services not available')
    SocialLogin.login.mockRejectedValueOnce(configError)

    const { signInWithGoogle } = await import('../services/googleAuth.js')

    await expect(signInWithGoogle()).rejects.toThrow('Google Play Services not available')
  })
})
