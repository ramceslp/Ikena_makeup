import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import StarRating from '../components/ui/StarRating.vue'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function mountStar(props = {}) {
  return mount(StarRating, { props })
}

// ---------------------------------------------------------------------------
// Display mode
// ---------------------------------------------------------------------------

describe('StarRating.vue — display mode', () => {
  it('renders max stars (default 5)', () => {
    const wrapper = mountStar({ rating: 3 })
    // In display mode all stars are spans, not buttons
    const spans = wrapper.findAll('.material-symbols-outlined')
    expect(spans).toHaveLength(5)
  })

  it('renders 3 filled stars and 2 empty for rating=3', () => {
    const wrapper = mountStar({ rating: 3 })
    // Filled stars have FILL font-variation-settings style
    const filled = wrapper.findAll('[data-star="filled"]')
    const empty = wrapper.findAll('[data-star="empty"]')
    expect(filled).toHaveLength(3)
    expect(empty).toHaveLength(2)
  })

  it('renders a half star for rating=4.5 (star_half icon present)', () => {
    const wrapper = mountStar({ rating: 4.5 })
    const half = wrapper.findAll('[data-star="half"]')
    expect(half).toHaveLength(1)
    // And 4 filled + 0 empty (4 full + 1 half = 4.5)
    const filled = wrapper.findAll('[data-star="filled"]')
    expect(filled).toHaveLength(4)
  })

  it('renders 0 filled and 5 empty for rating=0', () => {
    const wrapper = mountStar({ rating: 0 })
    const filled = wrapper.findAll('[data-star="filled"]')
    const empty = wrapper.findAll('[data-star="empty"]')
    expect(filled).toHaveLength(0)
    expect(empty).toHaveLength(5)
  })

  it('wraps in role="img" with aria-label containing "Valoración"', () => {
    const wrapper = mountStar({ rating: 4 })
    const root = wrapper.find('[role="img"]')
    expect(root.exists()).toBe(true)
    expect(root.attributes('aria-label')).toContain('Valoración')
  })

  it('aria-label includes the rating value', () => {
    const wrapper = mountStar({ rating: 3.5 })
    const root = wrapper.find('[role="img"]')
    expect(root.attributes('aria-label')).toContain('3.5')
  })

  it('renders count prop as "(N)" text', () => {
    const wrapper = mountStar({ rating: 4, count: 12 })
    expect(wrapper.text()).toContain('(12)')
  })

  it('does not render count when count prop is null', () => {
    const wrapper = mountStar({ rating: 4, count: null })
    expect(wrapper.text()).not.toContain('(')
  })

  it('renders numeric rating when showValue=true', () => {
    const wrapper = mountStar({ rating: 3.7, showValue: true })
    expect(wrapper.text()).toContain('3.7')
  })

  it('does not render numeric rating when showValue=false', () => {
    const wrapper = mountStar({ rating: 3.7, showValue: false })
    expect(wrapper.text()).not.toContain('3.7')
  })

  it('applies text-apricot-glow class to filled stars', () => {
    const wrapper = mountStar({ rating: 5 })
    const filled = wrapper.findAll('[data-star="filled"]')
    filled.forEach((el) => {
      expect(el.classes()).toContain('text-apricot-glow')
    })
  })

  it('applies text-outline-variant class to empty stars', () => {
    const wrapper = mountStar({ rating: 0 })
    const empty = wrapper.findAll('[data-star="empty"]')
    empty.forEach((el) => {
      expect(el.classes()).toContain('text-outline-variant')
    })
  })

  it('does NOT render buttons in display mode', () => {
    const wrapper = mountStar({ rating: 3, editable: false })
    expect(wrapper.findAll('button')).toHaveLength(0)
  })
})

// ---------------------------------------------------------------------------
// Editable mode
// ---------------------------------------------------------------------------

describe('StarRating.vue — editable mode', () => {
  it('renders role="radiogroup" in editable mode', () => {
    const wrapper = mountStar({ editable: true, modelValue: 0 })
    const root = wrapper.find('[role="radiogroup"]')
    expect(root.exists()).toBe(true)
  })

  it('renders max button elements in editable mode', () => {
    const wrapper = mountStar({ editable: true, modelValue: 0, max: 5 })
    expect(wrapper.findAll('button')).toHaveLength(5)
  })

  it('emits update:modelValue with the clicked star index', async () => {
    const wrapper = mountStar({ editable: true, modelValue: 0 })
    const buttons = wrapper.findAll('button')
    await buttons[3].trigger('click') // 4th star (index 3 = value 4)
    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([4])
  })

  it('clicking the first star emits update:modelValue with 1', async () => {
    const wrapper = mountStar({ editable: true, modelValue: 0 })
    const buttons = wrapper.findAll('button')
    await buttons[0].trigger('click')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([1])
  })

  it('clicking the 5th star emits update:modelValue with 5', async () => {
    const wrapper = mountStar({ editable: true, modelValue: 0 })
    const buttons = wrapper.findAll('button')
    await buttons[4].trigger('click')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([5])
  })

  it('each button has an aria-label with star number', () => {
    const wrapper = mountStar({ editable: true, modelValue: 0 })
    const buttons = wrapper.findAll('button')
    expect(buttons[0].attributes('aria-label')).toContain('1 estrella')
    expect(buttons[1].attributes('aria-label')).toContain('2 estrellas')
  })

  it('does NOT render buttons when editable=false', () => {
    const wrapper = mountStar({ editable: false, modelValue: 3 })
    expect(wrapper.findAll('button')).toHaveLength(0)
  })
})
