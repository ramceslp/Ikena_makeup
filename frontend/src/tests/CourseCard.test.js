import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import CourseCard from '../components/CourseCard.vue'

// ---------------------------------------------------------------------------
// Minimal router — CourseCard uses useRouter().push() on click
// ---------------------------------------------------------------------------
const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div/>' } }],
})

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function mountCard(course) {
  return mount(CourseCard, {
    props: { course },
    global: {
      plugins: [router],
    },
  })
}

const baseCourse = {
  id: 1,
  title: 'Makeup Fundamentals',
  slug: 'makeup-fundamentals',
  description: 'Learn the basics of professional makeup.',
  price: '49.99',
  thumbnail: 'https://example.com/thumb.jpg',
  instructor: { id: 2, name: 'Ana García' },
  lessons_count: 10,
  sections_count: 3,
  is_enrolled: false,
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('CourseCard.vue', () => {
  // router with createMemoryHistory() is immediately ready — no need to await isReady()

  it('renders the course title', () => {
    const wrapper = mountCard(baseCourse)
    expect(wrapper.text()).toContain('Makeup Fundamentals')
  })

  it('renders the instructor name', () => {
    const wrapper = mountCard(baseCourse)
    expect(wrapper.text()).toContain('Ana García')
  })

  it('renders the formatted price when course has a price', () => {
    const wrapper = mountCard({ ...baseCourse, price: '49.99' })
    expect(wrapper.text()).toContain('$49.99')
  })

  it('renders "Gratis" when price is "0.00"', () => {
    const wrapper = mountCard({ ...baseCourse, price: '0.00', slug: 'free-course' })
    expect(wrapper.text()).toContain('Gratis')
  })

  it('renders "Gratis" when price is 0 (number)', () => {
    const wrapper = mountCard({ ...baseCourse, price: 0 })
    expect(wrapper.text()).toContain('Gratis')
  })

  it('shows the thumbnail image when thumbnail is provided', () => {
    const wrapper = mountCard(baseCourse)
    const img = wrapper.find('img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://example.com/thumb.jpg')
    expect(img.attributes('alt')).toBe('Makeup Fundamentals')
  })

  it('shows a fallback placeholder when thumbnail is null', () => {
    const wrapper = mountCard({ ...baseCourse, thumbnail: null })
    const img = wrapper.find('img')
    expect(img.exists()).toBe(false)
    // The SVG fallback should be rendered instead
    const svg = wrapper.find('svg')
    expect(svg.exists()).toBe(true)
  })

  it('renders the lessons count', () => {
    const wrapper = mountCard({ ...baseCourse, lessons_count: 15 })
    expect(wrapper.text()).toContain('15')
  })

  it('renders the sections count', () => {
    const wrapper = mountCard({ ...baseCourse, sections_count: 4 })
    expect(wrapper.text()).toContain('4')
  })

  it('shows the "Inscrito" badge when is_enrolled is true', () => {
    const wrapper = mountCard({ ...baseCourse, is_enrolled: true })
    expect(wrapper.text()).toContain('Inscrito')
  })

  it('does not show the "Inscrito" badge when is_enrolled is false', () => {
    const wrapper = mountCard({ ...baseCourse, is_enrolled: false })
    expect(wrapper.text()).not.toContain('Inscrito')
  })

  it('does not show the "Inscrito" badge when is_enrolled is absent', () => {
    const { is_enrolled: _, ...courseWithoutFlag } = baseCourse
    const wrapper = mountCard(courseWithoutFlag)
    expect(wrapper.text()).not.toContain('Inscrito')
  })

  it('navigates to the course detail route when the card is clicked', async () => {
    const wrapper = mountCard(baseCourse)
    const pushSpy = vi.spyOn(router, 'push')

    await wrapper.trigger('click')

    expect(pushSpy).toHaveBeenCalledWith('/courses/makeup-fundamentals')
  })

  it('truncates a long description to 120 chars and appends ellipsis', () => {
    const longDesc = 'A'.repeat(200)
    const wrapper  = mountCard({ ...baseCourse, description: longDesc })
    // The excerpt function slices to 120 + '...'
    expect(wrapper.text()).toContain('A'.repeat(120) + '...')
  })

  it('renders the full description when it is shorter than 120 chars', () => {
    const shortDesc = 'Short description'
    const wrapper   = mountCard({ ...baseCourse, description: shortDesc })
    expect(wrapper.text()).toContain('Short description')
    expect(wrapper.text()).not.toContain('...')
  })

  // ── Ribbons: is_bestseller ──────────────────────────────────────────────────

  it('shows "Bestseller" ribbon when is_bestseller is true', () => {
    const wrapper = mountCard({ ...baseCourse, is_bestseller: true })
    expect(wrapper.text()).toContain('Bestseller')
  })

  it('does NOT show "Bestseller" ribbon when is_bestseller is false', () => {
    const wrapper = mountCard({ ...baseCourse, is_bestseller: false })
    expect(wrapper.text()).not.toContain('Bestseller')
  })

  it('does NOT show "Bestseller" ribbon when is_bestseller is absent', () => {
    const wrapper = mountCard({ ...baseCourse })
    expect(wrapper.text()).not.toContain('Bestseller')
  })

  // ── Ribbons: offers_certificate ─────────────────────────────────────────────

  it('shows "Certificado" ribbon when offers_certificate is true', () => {
    const wrapper = mountCard({ ...baseCourse, offers_certificate: true })
    expect(wrapper.text()).toContain('Certificado')
  })

  it('does NOT show "Certificado" ribbon when offers_certificate is false', () => {
    const wrapper = mountCard({ ...baseCourse, offers_certificate: false })
    expect(wrapper.text()).not.toContain('Certificado')
  })

  it('does NOT show "Certificado" ribbon when offers_certificate is absent', () => {
    const wrapper = mountCard({ ...baseCourse })
    expect(wrapper.text()).not.toContain('Certificado')
  })

  // ── Both ribbons stacked ────────────────────────────────────────────────────

  it('shows both "Bestseller" and "Certificado" ribbons when both flags are true', () => {
    const wrapper = mountCard({ ...baseCourse, is_bestseller: true, offers_certificate: true })
    expect(wrapper.text()).toContain('Bestseller')
    expect(wrapper.text()).toContain('Certificado')
  })

  // ── Category label ──────────────────────────────────────────────────────────

  it('renders the category name when course.category is present', () => {
    const wrapper = mountCard({
      ...baseCourse,
      category: { id: 1, name: 'Editorial', slug: 'editorial' },
    })
    expect(wrapper.text()).toContain('Editorial')
  })

  it('does NOT render a category label when course.category is null', () => {
    const wrapper = mountCard({ ...baseCourse, category: null })
    // No spurious category text leaking in
    expect(wrapper.text()).not.toContain('Editorial')
    expect(wrapper.text()).not.toContain('Novias')
  })

  it('does NOT render a category label when course.category is absent', () => {
    const wrapper = mountCard({ ...baseCourse })
    // The baseCourse has no category key — just a sanity guard
    expect(wrapper.text()).not.toContain('Sin categoría')
  })
})
