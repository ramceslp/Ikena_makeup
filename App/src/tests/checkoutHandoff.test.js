import { describe, it, expect, vi, beforeEach } from 'vitest'
import { readFile } from 'node:fs/promises'
import { resolve } from 'node:path'

vi.mock('../services/api.js', () => ({
  default: {
    post: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

vi.mock('@capacitor/browser', () => ({
  Browser: { open: vi.fn().mockResolvedValue(undefined) },
}))

import api from '../services/api.js'
import { Browser } from '@capacitor/browser'
import { startCheckoutHandoff, isAllowedHandoffUrl } from '../services/checkoutHandoff.js'

describe('isAllowedHandoffUrl — the one guard standing between a bad payload and window.open(undefined)', () => {
  it('accepts https in production and development alike', () => {
    expect(isAllowedHandoffUrl('https://app.ikena.com/checkout/resume#token=a', true)).toBe(true)
    expect(isAllowedHandoffUrl('https://app.ikena.com/checkout/resume#token=a', false)).toBe(true)
  })

  it('rejects plain http in production', () => {
    expect(isAllowedHandoffUrl('http://app.ikena.com/checkout/resume', true)).toBe(false)
  })

  /**
   * config('app.frontend_url') defaults to http://localhost:5173, so an
   * https-only rule would make every payment flow untestable off production —
   * which is how a guard ends up weakened rather than worked around.
   */
  it('accepts plain http in development', () => {
    expect(isAllowedHandoffUrl('http://localhost:5173/checkout/resume#token=a', false)).toBe(true)
  })

  it.each([
    ['undefined', undefined],
    ['null', null],
    ['a number', 42],
    ['an empty string', ''],
    ['a relative path', '/checkout/resume'],
    ['a javascript: URL', 'javascript:alert(1)'],
    ['a data: URL', 'data:text/html,<script>alert(1)</script>'],
    ['a file: URL', 'file:///etc/passwd'],
  ])('rejects %s in both modes', (_label, value) => {
    expect(isAllowedHandoffUrl(value, true)).toBe(false)
    expect(isAllowedHandoffUrl(value, false)).toBe(false)
  })
})

/**
 * Regression guard for a bug that ONLY showed up on a real emulator build.
 *
 * The guard was originally wired to `import.meta.env.PROD`. That flag is
 * derived from NODE_ENV, and `vite build` sets NODE_ENV=production regardless
 * of `--mode`, so PROD is true even for the `--mode development` build that
 * gets installed on the emulator. The guard compiled to
 * `isAllowedHandoffUrl(url, true)` and rejected the valid
 * http://localhost:5173 handoff URL the dev backend returns — every checkout
 * on the emulator failed with "No se pudo iniciar el pago".
 *
 * Both unit tests below passed the whole time, because they call the pure
 * function directly and never exercise the env wiring. Reading the source is
 * the only way to pin the flag, so that is what this does.
 */
describe('the production signal wired into the guard', () => {
  it('reads import.meta.env.MODE, never PROD', async () => {
    // Resolved from cwd, NOT via fileURLToPath(new URL(..., import.meta.url)):
    // under Vitest's jsdom environment that pattern throws "The URL must be of
    // scheme file" (the global URL shadows node:url's). Vitest runs from the
    // App/ package root.
    const source = await readFile(
      resolve(process.cwd(), 'src/services/checkoutHandoff.js'),
      'utf8'
    )

    // Comments are stripped first: the block comment above IS_PRODUCTION_BUILD
    // names `import.meta.env.PROD` on purpose, to explain why it must not be
    // used. Only executable code is asserted on.
    const code = source
      .replace(/\/\*[\s\S]*?\*\//g, '')
      .replace(/^\s*\/\/.*$/gm, '')

    expect(code).toContain("import.meta.env.MODE === 'production'")
    expect(code).not.toMatch(/import\.meta\.env\.PROD/)
  })
})

describe('startCheckoutHandoff', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('posts the payload verbatim and opens the returned URL in the system browser', async () => {
    api.post.mockResolvedValueOnce({
      data: { data: { url: 'https://app.ikena.com/checkout/resume#token=xyz' } },
    })

    const url = await startCheckoutHandoff({ type: 'course', course_id: 3 })

    expect(api.post).toHaveBeenCalledWith('/checkout/handoff', { type: 'course', course_id: 3 })
    expect(Browser.open).toHaveBeenCalledWith({ url: 'https://app.ikena.com/checkout/resume#token=xyz' })
    expect(url).toBe('https://app.ikena.com/checkout/resume#token=xyz')
  })

  it('throws and never opens the browser when the response carries no url', async () => {
    api.post.mockResolvedValueOnce({ data: { data: { expires_at: '2026-07-30T00:00:00Z' } } })

    await expect(startCheckoutHandoff({ type: 'product_cart', items: [] })).rejects.toThrow(
      /checkout handoff URL/i
    )
    expect(Browser.open).not.toHaveBeenCalled()
  })

  it('throws and never opens the browser when the response body is malformed', async () => {
    api.post.mockResolvedValueOnce({})

    await expect(startCheckoutHandoff({ type: 'product_cart', items: [] })).rejects.toThrow()
    expect(Browser.open).not.toHaveBeenCalled()
  })

  it('propagates the API error so each caller owns its own error copy', async () => {
    const failure = new Error('Request failed')
    failure.response = { status: 422, data: { message: 'Sin stock.' } }
    api.post.mockRejectedValueOnce(failure)

    await expect(startCheckoutHandoff({ type: 'product_cart', items: [] })).rejects.toBe(failure)
    expect(Browser.open).not.toHaveBeenCalled()
  })
})
