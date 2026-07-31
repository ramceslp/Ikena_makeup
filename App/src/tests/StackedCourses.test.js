/**
 * Tests for the "cartas apiladas" treatment of FeaturedCourses.vue.
 *
 * The three featured courses stop being a grid and become a deck: each card
 * sticks below the one before it, gets read on its own, and is then covered by
 * the next while it recedes.
 *
 * jsdom has no layout, so what is verifiable is the contract the CSS needs: the
 * cards are a stack rather than columns, each one knows its depth, and nothing
 * above them turns into a scroll container — which would silently break
 * `position: sticky` outright, not just degrade it.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

import api from '../services/api.js'
import FeaturedCourses from '../components/home/FeaturedCourses.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/cursos', component: { template: '<div/>' }, name: 'Cursos' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

const fakeCourses = [
  { id: 1, title: 'Maquillaje Nupcial', slug: 'maquillaje-nupcial', price: '250.00', thumbnail: null, category: { name: 'Novias' } },
  { id: 2, title: 'Técnicas Editoriales', slug: 'tecnicas-editoriales', price: '180.00', thumbnail: null, category: null },
  { id: 3, title: 'Corrección de Color', slug: 'correccion-color', price: '120.00', thumbnail: null, category: null },
]

/**
 * `position: sticky` is cancelled outright by ANY ancestor with a clipping
 * overflow — the card simply scrolls away as if it were static, with no error
 * and nothing to see in a screenshot. `overflow: clip` is safe; `hidden`,
 * `auto` and `scroll` are not.
 */
function stickyBreakingAncestors(card, root) {
  const offenders = []
  let node = card.parentElement

  while (node) {
    const className = node.getAttribute('class') ?? ''
    if (/\boverflow-(hidden|auto|scroll|x-auto|y-auto|x-scroll|y-scroll)\b/.test(className)) {
      offenders.push(className)
    }
    if (node === root) break
    node = node.parentElement
  }

  return offenders
}

async function mountCourses() {
  api.get.mockResolvedValueOnce({ data: { data: fakeCourses } })
  const wrapper = mount(FeaturedCourses, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('FeaturedCourses.vue — stacked deck', () => {
  it('lays the courses out as a stack rather than three columns', async () => {
    const wrapper = await mountCourses()
    const stack = wrapper.find('[data-course-stack]')

    expect(stack.exists()).toBe(true)
    expect(stack.classes().join(' ')).not.toContain('grid-cols-3')
  })

  it('still renders one card per course', async () => {
    const wrapper = await mountCourses()

    expect(wrapper.findAll('[data-course-card]')).toHaveLength(3)
  })

  /**
   * Each card sticks a step lower than the one before it, so the top edge of
   * every card underneath stays visible and the deck reads as a deck. The depth
   * rides a custom property so the offset stays one expression in CSS instead
   * of an nth-child rule per card.
   */
  it('gives each card its depth in the stack', async () => {
    const wrapper = await mountCourses()
    const indices = wrapper
      .findAll('[data-course-card]')
      .map((card) => card.element.style.getPropertyValue('--stack-index'))

    expect(indices).toEqual(['0', '1', '2'])
  })

  it('marks the cards as deck cards', async () => {
    const wrapper = await mountCourses()

    wrapper.findAll('[data-course-card]').forEach((card) => {
      expect(card.classes()).toContain('apilada')
    })
  })

  it('keeps every card clear of an ancestor that would cancel sticky', async () => {
    const wrapper = await mountCourses()

    wrapper.findAll('[data-course-card]').forEach((card) => {
      expect(stickyBreakingAncestors(card.element, wrapper.element)).toEqual([])
    })
  })

  /**
   * The card clips its own thumbnail to the rounded corner. `clip` rather than
   * `hidden` for the same reason as everywhere else in this page: `hidden` makes
   * it a scroll container, which would resolve any scroll timeline inside it
   * against a box that never scrolls.
   */
  it('clips each card without making it a scroll container', async () => {
    const wrapper = await mountCourses()
    const card = wrapper.find('[data-course-card]')

    expect(card.classes()).toContain('overflow-clip')
    expect(card.classes()).not.toContain('overflow-hidden')
  })

  it('falls back to the empty state when there are no courses', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })
    const wrapper = mount(FeaturedCourses, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.find('[data-course-stack]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Próximamente')
  })
})
