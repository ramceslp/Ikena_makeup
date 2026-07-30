/**
 * Tests for the v-reveal directive.
 *
 * jsdom does not implement IntersectionObserver, so the directive short-circuits
 * to "no animation" in every other test file — which is why the home component
 * suites never see these classes. Here we install a controllable stub so the
 * intersection can be fired on demand.
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import reveal from '../directives/reveal.js'

/** Captured observer instances, so a test can fire intersection by hand. */
let observers = []

class FakeIntersectionObserver {
  constructor(callback) {
    this.callback = callback
    this.observed = []
    this.disconnected = false
    observers.push(this)
  }

  observe(el) {
    this.observed.push(el)
  }

  unobserve(el) {
    this.observed = this.observed.filter((e) => e !== el)
  }

  disconnect() {
    this.disconnected = true
  }

  /** Simulates the element scrolling into view. */
  trigger(el) {
    this.callback([{ isIntersecting: true, target: el }], this)
  }
}

function mountWithDirective(template) {
  return mount(
    { template },
    { global: { directives: { reveal } } },
  )
}

/** jsdom's Event has no propertyName, so it has to be attached by hand. */
function transitionEnd(propertyName) {
  const event = new Event('transitionend')
  Object.defineProperty(event, 'propertyName', { value: propertyName })
  return event
}

function setReducedMotion(reduce) {
  window.matchMedia = vi.fn().mockImplementation((query) => ({
    matches: reduce && query === '(prefers-reduced-motion: reduce)',
    media: query,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  }))
}

beforeEach(() => {
  observers = []
  vi.stubGlobal('IntersectionObserver', FakeIntersectionObserver)
  setReducedMotion(false)
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('v-reveal — default variant', () => {
  it('adds the reveal class on mount and is-revealed on intersection', () => {
    const wrapper = mountWithDirective('<p v-reveal>Hola</p>')
    const el = wrapper.find('p').element

    expect(el.classList.contains('reveal')).toBe(true)
    expect(el.classList.contains('is-revealed')).toBe(false)

    observers[0].trigger(el)

    expect(el.classList.contains('is-revealed')).toBe(true)
  })

  /**
   * The stagger rides a custom property, not an inline transition-delay, so
   * the transition itself stays declared in style.css and the directive only
   * supplies a number.
   */
  it('staggers by index through the --reveal-delay custom property', () => {
    const wrapper = mountWithDirective('<p v-reveal="2">Hola</p>')

    expect(wrapper.find('p').element.style.getPropertyValue('--reveal-delay')).toBe('140ms')
  })

  it('clears the stagger property once the entrance settles', () => {
    const wrapper = mountWithDirective('<p v-reveal="2">Hola</p>')
    const el = wrapper.find('p').element

    observers[0].trigger(el)
    el.dispatchEvent(transitionEnd('transform'))

    expect(el.style.getPropertyValue('--reveal-delay')).toBe('')
  })

  it('renders at rest when the user prefers reduced motion', () => {
    setReducedMotion(true)

    const wrapper = mountWithDirective('<p v-reveal>Hola</p>')

    expect(wrapper.find('p').element.classList.contains('reveal')).toBe(false)
    expect(observers.length).toBe(0)
  })
})
