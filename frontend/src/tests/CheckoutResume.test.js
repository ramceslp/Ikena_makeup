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
})
