/**
 * CheckoutResume.vue — additive web surface that resumes a checkout started
 * from the future mobile app (mobile-capacitor-setup PR 4).
 *
 * The view deliberately does NOT reuse the shared services/api.js singleton
 * (see component comments) — it creates its own axios instance so this test
 * mocks 'axios' directly instead of '../services/api.js'.
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// vi.hoisted runs BEFORE vi.mock hoisting — this is the only safe way to
// share a spy reference between a vi.mock factory and the test body
// (same pattern as TipTapEditor.test.js).
const mockPost = vi.hoisted(() => vi.fn())

vi.mock('axios', () => ({
  default: {
    create: vi.fn(() => ({ post: mockPost })),
  },
}))

import CheckoutResume from '../views/CheckoutResume.vue'

const PAYPHONE_JS = 'https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.js'
const SESSION_KEY = 'ikena_checkout_resume'

async function mountResume(fullPath = '/checkout/resume') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/checkout/resume', name: 'CheckoutResume', component: CheckoutResume },
    ],
  })
  await router.push(fullPath)
  await router.isReady()
  return mount(CheckoutResume, { global: { plugins: [router] } })
}

describe('CheckoutResume.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    window.location.hash = ''
    sessionStorage.clear()
    delete window.PPaymentButtonBox
  })

  afterEach(() => {
    window.location.hash = ''
    sessionStorage.clear()
    document.head.querySelectorAll('script, link').forEach((el) => el.remove())
  })

  // ── 4.1 — reads token from fragment and redeems it ────────────────────────

  it('reads the token from the URL fragment and calls POST /checkout/handoff/redeem', async () => {
    window.location.hash = '#token=abc123token'
    mockPost.mockResolvedValueOnce({
      data: {
        data: {
          order_id: 55,
          provider: 'payphone',
          config: { clientTransactionId: 'ORD-abc', amount: 1000 },
          confirm_token: 'scoped-token-xyz',
        },
      },
    })

    await mountResume()
    await flushPromises()

    expect(mockPost).toHaveBeenCalledWith('/checkout/handoff/redeem', { token: 'abc123token' })
  })

  // ── 4.2 — renders the PayPhone widget on successful redeem ────────────────

  it('renders the PayPhone widget after a successful redeem', async () => {
    window.location.hash = '#token=abc123token'
    mockPost.mockResolvedValueOnce({
      data: {
        data: {
          order_id: 55,
          provider: 'payphone',
          config: { clientTransactionId: 'ORD-abc', amount: 1000 },
          confirm_token: 'scoped-token-xyz',
        },
      },
    })

    // Pre-insert the PayPhone script tag so injectPayPhoneAssets() resolves
    // immediately instead of waiting for a real onload event (jsdom never
    // fires load events for injected <script> tags).
    const script = document.createElement('script')
    script.src = PAYPHONE_JS
    document.head.appendChild(script)

    const renderSpy = vi.fn()
    // Must be a real `function` (not an arrow) — the component calls this
    // with `new`, and vi.fn()'s arrow-based mock implementation cannot be
    // used as a constructor.
    window.PPaymentButtonBox = vi.fn(function PPaymentButtonBoxMock() {
      return { render: renderSpy }
    })

    const wrapper = await mountResume()

    await vi.waitFor(
      () => {
        expect(window.PPaymentButtonBox).toHaveBeenCalled()
      },
      { timeout: 2000, interval: 20 }
    )
    await wrapper.vm.$nextTick()

    expect(wrapper.find('#pp-button').exists()).toBe(true)
    expect(window.PPaymentButtonBox).toHaveBeenCalledWith(
      expect.objectContaining({
        clientTransactionId: 'ORD-abc',
        responseUrl: expect.stringContaining('/checkout/resume'),
      })
    )
    expect(renderSpy).toHaveBeenCalledWith('pp-button')
  })

  // ── 4.2 — confirms via /payments/confirm using the confirm_token ──────────

  it('confirms the payment via POST /payments/confirm using the confirm_token when returning from the provider redirect', async () => {
    sessionStorage.setItem(
      SESSION_KEY,
      JSON.stringify({ confirm_token: 'scoped-token-xyz', order_id: 55 })
    )
    mockPost.mockResolvedValueOnce({ data: { data: { status: 'paid', order_id: 55 } } })

    const wrapper = await mountResume('/checkout/resume?id=PAYPHONE123&clientTransactionId=ORD-abc')
    await flushPromises()

    expect(mockPost).toHaveBeenCalledWith(
      '/payments/confirm',
      { id: 'PAYPHONE123', clientTransactionId: 'ORD-abc' },
      { headers: { Authorization: 'Bearer scoped-token-xyz' } }
    )
    expect(wrapper.text().toLowerCase()).toContain('pago confirmado')
  })

  it('shows an error and does not call confirm when returning without a stored session', async () => {
    const wrapper = await mountResume('/checkout/resume?id=PAYPHONE123&clientTransactionId=ORD-abc')
    await flushPromises()

    expect(mockPost).not.toHaveBeenCalled()
    expect(wrapper.text().toLowerCase()).toMatch(/reinicia.*(app|aplicación)/)
  })

  // ── 4.4 — expired / reused token states ───────────────────────────────────

  it('shows a restart-from-app message when the token has expired (410)', async () => {
    window.location.hash = '#token=expiredtoken'
    mockPost.mockRejectedValueOnce({
      response: {
        status: 410,
        data: { message: 'This checkout link has expired. Please restart checkout from the app.' },
      },
    })

    const wrapper = await mountResume()
    await flushPromises()

    expect(wrapper.text().toLowerCase()).toMatch(/reinicia.*(app|aplicación)/)
  })

  it('shows a restart-from-app message when the token was already used (409)', async () => {
    window.location.hash = '#token=usedtoken'
    mockPost.mockRejectedValueOnce({
      response: {
        status: 409,
        data: { message: 'This checkout link has already been used.' },
      },
    })

    const wrapper = await mountResume()
    await flushPromises()

    expect(wrapper.text().toLowerCase()).toMatch(/reinicia.*(app|aplicación)/)
  })

  // ── Code-discriminated 409s ────────────────────────────────────────────────
  // Three different failures share status 409. Rendering all of them as "el
  // enlace ya fue utilizado" was harmless while the app could only hand off a
  // product cart; once bookings and courses hand off too, it actively misleads
  // the customer about why their payment stopped.

  it('tells the customer the slot was taken (not that the link was used) on a 409 with code slot_unavailable', async () => {
    window.location.hash = '#token=slottoken'
    mockPost.mockRejectedValueOnce({
      response: {
        status: 409,
        data: { message: 'No agenda block covers this slot.', code: 'slot_unavailable' },
      },
    })

    const wrapper = await mountResume()
    await flushPromises()

    expect(wrapper.text()).toContain('Ese horario ya no está disponible.')
    expect(wrapper.text()).not.toContain('ya fue utilizado')
  })

  it('tells the customer the slot just filled up on a 409 with code cap_exceeded', async () => {
    window.location.hash = '#token=captoken'
    mockPost.mockRejectedValueOnce({
      response: {
        status: 409,
        data: { message: 'Capacity exceeded.', code: 'cap_exceeded' },
      },
    })

    const wrapper = await mountResume()
    await flushPromises()

    expect(wrapper.text()).toContain('Ese horario acaba de llenarse.')
  })

  it('tells the customer they already own the course on a 409 with code already_enrolled', async () => {
    window.location.hash = '#token=enrolledtoken'
    mockPost.mockRejectedValueOnce({
      response: {
        status: 409,
        data: { message: 'You are already enrolled in this course.', code: 'already_enrolled' },
      },
    })

    const wrapper = await mountResume()
    await flushPromises()

    expect(wrapper.text()).toContain('Ya estás inscrito en este curso.')
    expect(wrapper.text()).not.toContain('ya fue utilizado')
  })

  it('still says "already used" when the 409 really is a consumed link', async () => {
    window.location.hash = '#token=usedtoken'
    mockPost.mockRejectedValueOnce({
      response: {
        status: 409,
        data: { message: 'This checkout link has already been used.', code: 'link_consumed' },
      },
    })

    const wrapper = await mountResume()
    await flushPromises()

    expect(wrapper.text()).toContain('Este enlace de pago ya fue utilizado.')
  })

  it('never shows the backend English message when a code is present', async () => {
    window.location.hash = '#token=coursetoken'
    mockPost.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { message: 'This course is no longer available.', code: 'course_unavailable' },
      },
    })

    const wrapper = await mountResume()
    await flushPromises()

    expect(wrapper.text()).toContain('Este curso ya no está disponible para compra.')
    expect(wrapper.text()).not.toContain('This course is no longer available')
  })

  it('shows a restart-from-app message for an unknown token (404)', async () => {
    window.location.hash = '#token=unknowntoken'
    mockPost.mockRejectedValueOnce({
      response: {
        status: 404,
        data: { message: 'Unknown or invalid checkout link.' },
      },
    })

    const wrapper = await mountResume()
    await flushPromises()

    expect(wrapper.text().toLowerCase()).toMatch(/reinicia.*(app|aplicación)/)
  })

  it('shows a restart-from-app message when the URL has no token at all', async () => {
    const wrapper = await mountResume()
    await flushPromises()

    expect(mockPost).not.toHaveBeenCalled()
    expect(wrapper.text().toLowerCase()).toMatch(/reinicia.*(app|aplicación)/)
  })

  // ── Judgment Day fix #1 — failed confirm must NOT clear the session ───────

  it('does not clear the stored session when confirmPayment fails, so a retry can reuse the confirm_token', async () => {
    sessionStorage.setItem(
      SESSION_KEY,
      JSON.stringify({ confirm_token: 'scoped-token-xyz', order_id: 55 })
    )
    mockPost.mockRejectedValueOnce({
      response: { status: 500, data: { message: 'Backend unavailable.' } },
    })

    await mountResume('/checkout/resume?id=PAYPHONE123&clientTransactionId=ORD-abc')
    await flushPromises()

    const stored = JSON.parse(sessionStorage.getItem(SESSION_KEY) || 'null')
    expect(stored).not.toBeNull()
    expect(stored.confirm_token).toBe('scoped-token-xyz')
  })

  it('still clears the stored session when confirmPayment succeeds', async () => {
    sessionStorage.setItem(
      SESSION_KEY,
      JSON.stringify({ confirm_token: 'scoped-token-xyz', order_id: 55 })
    )
    mockPost.mockResolvedValueOnce({ data: { data: { status: 'paid', order_id: 55 } } })

    await mountResume('/checkout/resume?id=PAYPHONE123&clientTransactionId=ORD-abc')
    await flushPromises()

    expect(sessionStorage.getItem(SESSION_KEY)).toBeNull()
  })

  // ── Judgment Day fix #2 — reload during 'payphone' phase must not replay redeem ──

  it('clears the URL fragment after a successful redeem', async () => {
    window.location.hash = '#token=abc123token'
    mockPost.mockResolvedValueOnce({
      data: {
        data: {
          order_id: 55,
          provider: 'payphone',
          config: { clientTransactionId: 'ORD-abc', amount: 1000 },
          confirm_token: 'scoped-token-xyz',
        },
      },
    })

    await mountResume()
    await flushPromises()

    expect(window.location.hash).toBe('')
  })

  it('shows an "already started" message instead of re-redeeming when a session already exists and the URL has no token (accidental reload after replaceState)', async () => {
    sessionStorage.setItem(
      SESSION_KEY,
      JSON.stringify({ confirm_token: 'scoped-token-xyz', order_id: 55 })
    )

    const wrapper = await mountResume()
    await flushPromises()

    expect(mockPost).not.toHaveBeenCalled()
    expect(wrapper.text().toLowerCase()).toContain('ya se inició este proceso de pago')
  })

  it('attempts to redeem a NEW/different fragment token even when a stale session from a different order lingers', async () => {
    window.location.hash = '#token=brandnewtoken'
    sessionStorage.setItem(
      SESSION_KEY,
      JSON.stringify({ confirm_token: 'stale-token-from-another-order', order_id: 12 })
    )
    mockPost.mockResolvedValueOnce({
      data: {
        data: {
          order_id: 99,
          provider: 'payphone',
          config: { clientTransactionId: 'ORD-new', amount: 2000 },
          confirm_token: 'fresh-token-xyz',
        },
      },
    })

    await mountResume()
    await flushPromises()

    expect(mockPost).toHaveBeenCalledWith('/checkout/handoff/redeem', { token: 'brandnewtoken' })
  })

  // ── Judgment Day fix #3 — retry/back actions on error/expired states ──────

  it('renders a "Reintentar" button in the error phase that reloads the page', async () => {
    window.location.hash = '#token=abc123token'
    mockPost.mockRejectedValueOnce({
      response: { status: 500, data: { message: 'Backend unavailable.' } },
    })

    const reloadSpy = vi.fn()
    const originalLocation = window.location
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: { ...originalLocation, reload: reloadSpy },
    })

    const wrapper = await mountResume()
    await flushPromises()

    const button = wrapper.findAll('button').find((b) => b.text().includes('Reintentar'))
    expect(button).toBeTruthy()

    await button.trigger('click')
    expect(reloadSpy).toHaveBeenCalled()

    Object.defineProperty(window, 'location', { configurable: true, value: originalLocation })
  })

  it('also renders a "Volver al inicio" link in the error phase, alongside "Reintentar", so the user is never trapped', async () => {
    window.location.hash = '#token=abc123token'
    mockPost.mockRejectedValueOnce({
      response: { status: 500, data: { message: 'Backend unavailable.' } },
    })

    const wrapper = await mountResume()
    await flushPromises()

    const button = wrapper.findAll('button').find((b) => b.text().includes('Reintentar'))
    const link = wrapper.findAll('a').find((a) => a.text().toLowerCase().includes('inicio'))

    expect(button).toBeTruthy()
    expect(link).toBeTruthy()
    expect(link.attributes('href')).toBe('/')
  })

  it('renders a link back to the site in the expired phase instead of a retry button', async () => {
    window.location.hash = '#token=expiredtoken'
    mockPost.mockRejectedValueOnce({
      response: {
        status: 410,
        data: { message: 'This checkout link has expired.' },
      },
    })

    const wrapper = await mountResume()
    await flushPromises()

    const link = wrapper.findAll('a').find((a) => a.text().toLowerCase().includes('inicio'))
    expect(link).toBeTruthy()
    expect(link.attributes('href')).toBe('/')
    expect(wrapper.findAll('button').some((b) => b.text().includes('Reintentar'))).toBe(false)
  })
})
