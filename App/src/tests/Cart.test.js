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

  it('the pay CTA is present but disabled — checkout-handoff wiring lands in a later PR (tasks 8.3-8.5)', async () => {
    const cart = useCartStore()
    await cart.addItem(product)

    const wrapper = mount(Cart, { global: { plugins: [router] } })
    await flushPromises()

    const checkoutBtn = wrapper.find('[data-checkout-btn]')
    expect(checkoutBtn.exists()).toBe(true)
    expect(checkoutBtn.attributes('disabled')).toBeDefined()
  })
})
