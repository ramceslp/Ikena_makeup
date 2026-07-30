/**
 * Tests for SpecialtiesRibbon.vue — the "cinta" marquee band.
 *
 * The band loops by holding each list TWICE and travelling exactly one copy's
 * width, so most of what matters here is that the two copies stay identical and
 * that only one of them is exposed to assistive tech.
 */
import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import SpecialtiesRibbon from '../components/home/SpecialtiesRibbon.vue'

let wrapper

beforeEach(() => {
  wrapper = mount(SpecialtiesRibbon)
})

const groupsOf = (track) => track.findAll('[data-ribbon-group]')

describe('SpecialtiesRibbon.vue — marquee band', () => {
  it('renders the band with two tracks', () => {
    expect(wrapper.find('[data-specialties-ribbon]').exists()).toBe(true)
    expect(wrapper.findAll('[data-ribbon-track]')).toHaveLength(2)
  })

  it('names real makeup specialties and techniques', () => {
    const text = wrapper.text()

    expect(text).toContain('Novias')
    expect(text).toContain('Editorial')
    expect(text).toContain('Piel luminosa')
  })

  /**
   * The loop needs a second copy, but a screen reader must not read the whole
   * band twice — so the duplicate is the one that gets aria-hidden.
   */
  it('exposes each list once to assistive tech and hides the looping copy', () => {
    wrapper.findAll('[data-ribbon-track]').forEach((track) => {
      const groups = groupsOf(track)

      expect(groups).toHaveLength(2)
      expect(groups[0].attributes('aria-hidden')).toBeUndefined()
      expect(groups[1].attributes('aria-hidden')).toBe('true')
    })
  })

  /**
   * The track travels -50%, which is exactly one copy, so at the end of a cycle
   * copy #2 lands where copy #1 started and the restart is invisible. If the
   * copies ever diverge the band visibly jumps once per cycle.
   */
  it('keeps the looping copy identical to the original, or the loop jumps', () => {
    wrapper.findAll('[data-ribbon-track]').forEach((track) => {
      const [original, duplicate] = groupsOf(track)

      expect(duplicate.text()).toBe(original.text())
      expect(duplicate.findAll('li')).toHaveLength(original.findAll('li').length)
    })
  })

  it('gives the two tracks different content, so the rows do not read as one block', () => {
    const [first, second] = wrapper.findAll('[data-ribbon-track]')

    expect(groupsOf(second)[0].text()).not.toBe(groupsOf(first)[0].text())
  })

  /**
   * `overflow: clip` rather than `hidden`: the band would otherwise become a
   * scroll container mid-page, which is both a stray scrollable region for
   * keyboard users and the trap that freezes any scroll timeline underneath it
   * (see the Velo and Trazo blocks in style.css).
   */
  it('clips the overflow without becoming a scroll container', () => {
    const band = wrapper.find('[data-specialties-ribbon]')

    expect(band.classes()).toContain('overflow-clip')
    expect(band.classes()).not.toContain('overflow-hidden')
  })
})
