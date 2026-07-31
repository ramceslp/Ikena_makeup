/**
 * Port of frontend/src/tests/NewsletterTone.test.js for App/'s closing section.
 *
 * The closing section walks the brand palette as it scrolls into view: blush,
 * then apricot, then marsala. Three stacked gradient layers cross-fade by
 * opacity on a view timeline, so there is no JavaScript and nothing to trigger.
 *
 * jsdom has neither layout nor scroll timelines, so what is verifiable here is
 * the structure the CSS depends on: three layers exist in palette order, they
 * are hidden from assistive tech, and the section is both a stacking context
 * (or the negative z-index escapes behind the page) and free of the overflow
 * that would freeze the timeline.
 */
import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import NewsletterCta from '../components/home/NewsletterCta.vue'

let wrapper

beforeEach(() => {
  wrapper = mount(NewsletterCta)
})

describe('NewsletterCta.vue — tone wash', () => {
  it('stacks three tone layers in palette order', () => {
    const layers = wrapper.findAll('[data-tone-layer]')

    expect(layers).toHaveLength(3)
    expect(layers[0].classes()).toContain('tono-layer--blush')
    expect(layers[1].classes()).toContain('tono-layer--apricot')
    expect(layers[2].classes()).toContain('tono-layer--marsala')
  })

  /**
   * The wash carries no meaning a screen reader could use, and its layers are
   * empty elements that would otherwise be announced as nothing at all.
   */
  it('hides the wash from assistive tech', () => {
    expect(wrapper.find('[data-tone-wash]').attributes('aria-hidden')).toBe('true')
  })

  /**
   * The wash sits at z-index -1 so it paints above the section background and
   * below the copy. Without a stacking context on the section that -1 escapes
   * the section entirely and the wash disappears behind the page.
   */
  it('makes the section a stacking context so the wash cannot escape it', () => {
    const section = wrapper.find('[data-newsletter-cta]')

    expect(section.classes()).toContain('isolate')
    expect(section.classes()).toContain('relative')
  })

  /**
   * Same trap as everywhere else on this page: `overflow: hidden` makes the
   * section a scroll container, and `view()` resolves against the nearest one —
   * a container that never scrolls pins progress at a constant, so the tone
   * would never change.
   */
  it('keeps the section clear of the overflow that would freeze the timeline', () => {
    const section = wrapper.find('[data-newsletter-cta]')

    expect(section.classes()).not.toContain('overflow-hidden')
    expect(section.classes()).not.toContain('overflow-auto')
  })

  it('still renders the newsletter copy and form above the wash', () => {
    expect(wrapper.text()).toContain('Únete a nuestra comunidad')
    expect(wrapper.find('input[type="email"]').exists()).toBe(true)
  })
})
