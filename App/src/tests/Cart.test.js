import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('../services/storage.js', () => ({
  set: vi.fn().mockResolvedValue(undefined),
  remove: vi.fn().mockResolvedValue(undefined),
  getCached: vi.fn().mockReturnValue(null),
  CART_KEY: 'ikena_cart',
}))

// pay() (tasks 8.3-8.5) POSTs to /checkout/handoff and opens the result via
// @capacitor/browser -- mock both so the pay CTA can be exercised without a
// real network call or a real native browser (unavailable in vitest/jsdom).
vi.mock('../services/api.js', () => ({
  default: {
    post: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

vi.mock('@capacitor/browser', () => ({
  Browser: { open: vi.fn().mockResolvedValue(undefined) },
}))

import { set } from '../services/storage.js'
import api from '../services/api.js'
import { Browser } from '@capacitor/browser'
import { useCartStore } from '../stores/cart.js'
import Cart from '../views/Cart.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/cart', component: Cart, name: 'cart' },
      { path: '/products', component: { template: '<div/>' }, name: 'products' },
      { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
    ],
  })
}

const product = {
  id: 1,
  slug: 'paleta-editorial',
  title: 'Paleta Editorial',
  price: '19.99',
  thumbnail: 'thumb.jpg',
  stock_qty: 5,
}

describe('Cart.vue (App) — build/manage UX, no in-app payment [Spec: cart persists across sessions]', () => {
  let router

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    router = makeRouter()
    await router.push('/cart')
  })

  it('shows the empty-cart state with a link back to the product catalog when there are no items', async () => {
    const wrapper = mount(Cart, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-empty-cart]').exists()).toBe(true)
    expect(wrapper.find('[data-browse-link]').exists()).toBe(true)
    expect(wrapper.find('[data-cart-items]').exists()).toBe(false)
  })

  it('renders one row per cart item with title, quantity, and line total', async () => {
    const cart = useCartStore()
    await cart.addItem(product)

    const wrapper = mount(Cart, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-empty-cart]').exists()).toBe(false)
    const rows = wrapper.findAll('[data-cart-row]')
    expect(rows).toHaveLength(1)
    expect(rows[0].text()).toContain('Paleta Editorial')
    expect(rows[0].text()).toContain('$19.99')
  })

  it('tapping the increment/decrement steppers updates the cart store quantity and re-renders', async () => {
    const cart = useCartStore()
    await cart.addItem(product)

    const wrapper = mount(Cart, { global: { plugins: [router] } })
    await flushPromises()

    await wrapper.find('[data-qty-inc]').trigger('click')
    await flushPromises()

    expect(cart.items[0].quantity).toBe(2)
    expect(wrapper.find('[data-qty-value]').text()).toBe('2')
  })

  it('tapping remove takes the item out of the cart and shows the empty state', async () => {
    const cart = useCartStore()
    await cart.addItem(product)

    const wrapper = mount(Cart, { global: { plugins: [router] } })
    await flushPromises()

    await wrapper.find('[data-remove-btn]').trigger('click')
    await flushPromises()

    expect(cart.items).toHaveLength(0)
    expect(wrapper.find('[data-empty-cart]').exists()).toBe(true)
  })

  it('shows the subtotal, tax, and total for the items in the cart', async () => {
    const cart = useCartStore()
    await cart.addItem(product) // 19.99 x 1

    const wrapper = mount(Cart, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toContain('$19.99') // subtotal
    expect(wrapper.find('[data-cart-total]').exists()).toBe(true)
  })

  it('never shows "Gratis" for subtotal/tax/total, even when the 15% tax rounds to $0.00', async () => {
    // Regression for Judgment Day Round 1: subtotalCents=3 -> taxCents =
    // Math.round(3 * 0.15) = Math.round(0.45) = 0. Routing that through
    // formatPrice()'s "Gratis" fallback rendered "IVA (15%): Gratis" --
    // nonsensical for a tax line that merely rounds to zero, not a genuine
    // free item.
    const cart = useCartStore()
    await cart.addItem({ ...product, price: '0.03', stock_qty: 1 })

    const wrapper = mount(Cart, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).not.toContain('Gratis')
    expect(wrapper.text()).toContain('$0.00') // tax row, rounds to zero cents
  })

  // ── pay CTA wiring (tasks 8.3-8.5) ───────────────────────────────────────
  // The pay button was rendered-but-disabled in PR8a; this PR wires it to
  // cart.pay(), which POSTs /checkout/handoff and opens the result via
  // @capacitor/browser -- never the app's own router/WebView.

  it('the pay CTA is enabled (not disabled) once the cart has items', async () => {
    const cart = useCartStore()
    await cart.addItem(product)

    const wrapper = mount(Cart, { global: { plugins: [router] } })
    await flushPromises()

    const checkoutBtn = wrapper.find('[data-checkout-btn]')
    expect(checkoutBtn.exists()).toBe(true)
    expect(checkoutBtn.attributes('disabled')).toBeUndefined()
  })

  it('tapping pay opens the returned checkout URL via @capacitor/browser (never the app WebView/router) [Spec: handoff opens browser pre-loaded / payment container isolation]', async () => {
    const cart = useCartStore()
    await cart.addItem(product)
    api.post.mockResolvedValueOnce({
      data: { data: { url: 'https://app.ikena.com/checkout/resume#token=abc', expires_at: '' } },
    })

    const wrapper = mount(Cart, { global: { plugins: [router] } })
    await flushPromises()

    await wrapper.find('[data-checkout-btn]').trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/checkout/handoff', {
      type: 'product_cart',
      items: [{ product_id: 1, quantity: 1 }],
    })
    expect(Browser.open).toHaveBeenCalledWith({
      url: 'https://app.ikena.com/checkout/resume#token=abc',
    })
    // The app's own router never navigates anywhere on a successful pay tap
    // -- the checkout URL is handed to @capacitor/browser, not router.push().
    expect(router.currentRoute.value.path).toBe('/cart')
  })

  it('shows a pay-error banner when the handoff call fails, and never opens the browser', async () => {
    const cart = useCartStore()
    await cart.addItem(product)
    api.post.mockRejectedValueOnce(new Error('Network Error'))

    const wrapper = mount(Cart, { global: { plugins: [router] } })
    await flushPromises()

    await wrapper.find('[data-checkout-btn]').trigger('click')
    await flushPromises()

    expect(Browser.open).not.toHaveBeenCalled()
    const banner = wrapper.find('[data-pay-error]')
    expect(banner.exists()).toBe(true)
    expect(banner.text()).toBe('No se pudo iniciar el pago. Inténtalo de nuevo.')
  })

  it('the empty-cart state never renders a pay CTA at all, and shows the "cart is empty" message [Spec: cart empty at checkout tap]', async () => {
    const wrapper = mount(Cart, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-checkout-btn]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Tu carrito está vacío')
  })

  // ── Judgment Day Round 2 regression ─────────────────────────────────────
  // cart.js's persistError (added in Round 1) was set on a failed persist
  // but never surfaced anywhere, silently swallowing storage failures.
  it('shows a persist-error banner when cart.persistError is set (storage.js set() rejected)', async () => {
    const cart = useCartStore()
    set.mockRejectedValueOnce(new Error('Preferences native bridge failure'))
    await cart.addItem(product)

    const wrapper = mount(Cart, { global: { plugins: [router] } })
    await flushPromises()

    const banner = wrapper.find('[data-persist-error]')
    expect(banner.exists()).toBe(true)
    expect(banner.text()).toBe('No se pudo guardar el carrito. Tus cambios podrían perderse.')
  })

  it('does not show the persist-error banner when cart.persistError is null', async () => {
    const cart = useCartStore()
    await cart.addItem(product)

    const wrapper = mount(Cart, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-persist-error]').exists()).toBe(false)
  })
})
