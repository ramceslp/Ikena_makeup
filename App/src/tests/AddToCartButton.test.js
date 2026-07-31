import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'

// storage.js is Preferences-backed (native bridge) — stub it so the cart
// store's persistence is a no-op here, same approach as cart.store.test.js.
vi.mock('../services/storage.js', () => ({
  set: vi.fn().mockResolvedValue(undefined),
  remove: vi.fn().mockResolvedValue(undefined),
  getCached: vi.fn(() => null),
  CART_KEY: 'cart',
}))

import AddToCartButton from '../components/cart/AddToCartButton.vue'
import { useCartStore } from '../stores/cart.js'

const inStock = {
  id: 7,
  slug: 'paleta-editorial',
  title: 'Paleta Editorial',
  price: '45.00',
  thumbnail: null,
  stock_qty: 3,
}

function mountButton(product = inStock, props = {}) {
  return mount(AddToCartButton, { props: { product, ...props } })
}

describe('AddToCartButton.vue — the CTA that was missing from the catalog', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('adds the product to the cart store when tapped', async () => {
    const wrapper = mountButton()
    const cart = useCartStore()

    expect(cart.count).toBe(0)

    await wrapper.find('[data-add-to-cart]').trigger('click')
    await flushPromises()

    expect(cart.count).toBe(1)
    expect(cart.items[0]).toMatchObject({ product_id: 7, title: 'Paleta Editorial', quantity: 1 })
  })

  it('increments the quantity when tapped again', async () => {
    const wrapper = mountButton()
    const cart = useCartStore()

    await wrapper.find('[data-add-to-cart]').trigger('click')
    await flushPromises()
    await wrapper.find('[data-add-to-cart]').trigger('click')
    await flushPromises()

    expect(cart.count).toBe(2)
    expect(cart.items).toHaveLength(1)
  })

  it('is disabled and labelled "Agotado" when stock_qty is 0', async () => {
    const wrapper = mountButton({ ...inStock, stock_qty: 0 })
    const cart = useCartStore()
    const button = wrapper.find('[data-add-to-cart]')

    expect(button.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Agotado')

    await button.trigger('click')
    await flushPromises()
    expect(cart.count).toBe(0)
  })

  it('treats a non-numeric stock_qty as out of stock rather than adding blindly', async () => {
    const wrapper = mountButton({ ...inStock, stock_qty: null })

    expect(wrapper.find('[data-add-to-cart]').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Agotado')
  })

  /**
   * cart.addItem() clamps to stock_qty, so without this state the button would
   * keep looking tappable while doing nothing at all.
   */
  it('disables itself once the cart already holds all available stock', async () => {
    vi.useFakeTimers()
    const wrapper = mountButton({ ...inStock, stock_qty: 2 })
    const button = wrapper.find('[data-add-to-cart]')

    await button.trigger('click')
    await flushPromises()
    await button.trigger('click')
    await flushPromises()

    expect(useCartStore().count).toBe(2)
    expect(wrapper.find('[data-add-to-cart]').attributes('disabled')).toBeDefined()

    // The just-added confirmation deliberately outranks the maxed-out label
    // for its 2s window — right after a tap, "it worked" is the more useful
    // message. The maxed-out state is what the button settles into afterwards.
    expect(wrapper.text()).toContain('Añadido al carrito')

    vi.advanceTimersByTime(2100)
    await flushPromises()

    expect(wrapper.text()).toContain('Máximo disponible')
    expect(wrapper.find('[data-add-to-cart]').attributes('disabled')).toBeDefined()
  })

  it('shows a transient confirmation and announces it politely', async () => {
    vi.useFakeTimers()
    const wrapper = mountButton()

    await wrapper.find('[data-add-to-cart]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Añadido al carrito')
    const status = wrapper.find('[data-add-to-cart-status]')
    expect(status.attributes('aria-live')).toBe('polite')
    expect(status.text()).toContain('Paleta Editorial')

    vi.advanceTimersByTime(2100)
    await flushPromises()

    expect(wrapper.text()).toContain('Añadir al carrito')
    expect(wrapper.find('[data-add-to-cart-status]').text()).toBe('')
  })

  it('clears its pending confirmation timer on unmount', async () => {
    vi.useFakeTimers()
    const clearSpy = vi.spyOn(globalThis, 'clearTimeout')
    const wrapper = mountButton()

    await wrapper.find('[data-add-to-cart]').trigger('click')
    await flushPromises()
    wrapper.unmount()

    expect(clearSpy).toHaveBeenCalled()
    // No "timer fired against an unmounted component" warning/throw.
    expect(() => vi.advanceTimersByTime(2100)).not.toThrow()
  })
})
