/**
 * Tests for the v-reveal directive and its `trazo` variant.
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
   * The stagger rides a custom property, not an inline transition-delay:
   * custom properties inherit into pseudo-elements, and the trazo variant's
   * glow lives on ::after. An inline delay would reach the text but not the
   * pigment, and the two would separate by the stagger amount.
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
})

describe('v-reveal.trazo — brush-stroke variant', () => {
  /**
   * The variant swaps which class carries the hidden state. Sharing `reveal`
   * would apply the translate/opacity entrance on top of the wipe.
   */
  it('adds the trazo class instead of reveal', () => {
    const wrapper = mountWithDirective('<h2 v-reveal.trazo>Cursos Destacados</h2>')
    const el = wrapper.find('h2').element

    expect(el.classList.contains('trazo')).toBe(true)
    expect(el.classList.contains('reveal')).toBe(false)
  })

  it('paints in by adding is-revealed when the heading scrolls into view', () => {
    const wrapper = mountWithDirective('<h2 v-reveal.trazo>Cursos Destacados</h2>')
    const el = wrapper.find('h2').element

    expect(el.classList.contains('is-revealed')).toBe(false)

    observers[0].trigger(el)

    expect(el.classList.contains('is-revealed')).toBe(true)
  })

  /**
   * The wipe animates mask-position, not transform. Cleanup keyed to the wrong
   * property would never fire, leaving the mask applied for the life of the
   * page.
   */
  it('strips its classes once the mask-position transition finishes', () => {
    const wrapper = mountWithDirective('<h2 v-reveal.trazo>Cursos Destacados</h2>')
    const el = wrapper.find('h2').element

    observers[0].trigger(el)
    el.dispatchEvent(new Event('transitionend'))
    expect(el.classList.contains('trazo')).toBe(true) // wrong property: ignored

    el.dispatchEvent(transitionEnd('mask-position'))

    expect(el.classList.contains('trazo')).toBe(false)
    expect(el.classList.contains('is-revealed')).toBe(false)
  })

  /**
   * The name Chromium 150 actually emits in the Android WebView — prefixed AND
   * decomposed to the x axis. Measured over the DevTools protocol. Both an
   * exact match and a suffix match miss it, which left every heading with its
   * mask still applied.
   */
  it('settles on the prefixed, axis-decomposed name the WebView emits', () => {
    const wrapper = mountWithDirective('<h2 v-reveal.trazo>Cursos Destacados</h2>')
    const el = wrapper.find('h2').element

    observers[0].trigger(el)
    el.dispatchEvent(transitionEnd('-webkit-mask-position-x'))

    expect(el.classList.contains('trazo')).toBe(false)
  })

  /**
   * The glow pseudo-element settles as background-position-x. It must not be
   * mistaken for the wipe finishing, or cleanup fires while the mask is still
   * mid-sweep and the heading snaps.
   */
  it('does not settle on the glow’s own background-position', () => {
    const wrapper = mountWithDirective('<h2 v-reveal.trazo>Cursos Destacados</h2>')
    const el = wrapper.find('h2').element

    observers[0].trigger(el)
    el.dispatchEvent(transitionEnd('background-position-x'))

    expect(el.classList.contains('trazo')).toBe(true)
  })

  /**
   * Regression guard for the deadlock this variant was rewritten to escape:
   * hiding with clip-path zeroes the element's intersection rect in Chromium,
   * so the observer never fires and the heading never becomes visible. jsdom
   * has no layout, so this pins the contract — the hidden state is a mask —
   * rather than the browser behaviour itself.
   */
  it('never settles on clip-path, which must not be the hidden state', () => {
    const wrapper = mountWithDirective('<h2 v-reveal.trazo>Cursos Destacados</h2>')
    const el = wrapper.find('h2').element

    observers[0].trigger(el)
    el.dispatchEvent(transitionEnd('clip-path'))

    expect(el.classList.contains('trazo')).toBe(true)
  })

  it('renders at rest when the user prefers reduced motion', () => {
    setReducedMotion(true)

    const wrapper = mountWithDirective('<h2 v-reveal.trazo>Cursos Destacados</h2>')
    const el = wrapper.find('h2').element

    expect(el.classList.contains('trazo')).toBe(false)
    expect(observers.length).toBe(0)
  })
})
