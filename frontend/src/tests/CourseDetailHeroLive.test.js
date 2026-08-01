import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CourseDetailHero from '../components/course/CourseDetailHero.vue'
import CurriculumAccordion from '../components/course/CurriculumAccordion.vue'

const baseCourse = {
  id: 1,
  title: 'Maquillaje social',
  description: 'Curso completo',
  price: '120.00',
  instructor: { id: 2, name: 'Ana Ruiz' },
  total_lessons: 5,
  reviews_count: 0,
  offers_certificate: false,
  delivery_mode: 'on_demand',
  starts_on: null,
  ends_on: null,
  total_hours: null,
}

const liveCourse = {
  ...baseCourse,
  delivery_mode: 'live',
  starts_on: '2026-09-01',
  ends_on: '2026-09-30',
  total_hours: '20.0',
  offers_certificate: true,
}

function mountHero(course) {
  return mount(CourseDetailHero, {
    props: { course },
    global: { stubs: { BaseButton: true, BaseBadge: false, StarRating: true } },
  })
}

describe('CourseDetailHero — live courses', () => {
  it('marks a live course as such', () => {
    const wrapper = mountHero(liveCourse)

    expect(wrapper.find('[data-live-badge]').exists()).toBe(true)
    expect(wrapper.find('[data-on-demand-badge]').exists()).toBe(false)
  })

  it('marks an on-demand course as self-paced', () => {
    const wrapper = mountHero(baseCourse)

    expect(wrapper.find('[data-on-demand-badge]').exists()).toBe(true)
    expect(wrapper.find('[data-live-badge]').exists()).toBe(false)
  })

  it('shows the certificate badge only when the course offers one', () => {
    expect(mountHero(liveCourse).find('[data-certificate-badge]').exists()).toBe(true)
    expect(mountHero(baseCourse).find('[data-certificate-badge]').exists()).toBe(false)
  })

  /**
   * Calendar days arrive as plain "YYYY-MM-DD" with no offset. Parsing them
   * with the Date constructor reads them as UTC midnight, which renders the
   * previous day for every timezone west of Greenwich — including this one.
   */
  it('renders the start date as the day that was typed', () => {
    const wrapper = mountHero(liveCourse)

    expect(wrapper.find('[data-date-range]').text()).toContain('1 de septiembre de 2026')
  })

  it('trims the trailing decimal off whole total hours', () => {
    const wrapper = mountHero(liveCourse)

    expect(wrapper.find('[data-total-hours]').text()).toContain('20 horas')
  })

  it('omits the calendar and hours for an on-demand course', () => {
    const wrapper = mountHero(baseCourse)

    expect(wrapper.find('[data-date-range]').exists()).toBe(false)
    expect(wrapper.find('[data-total-hours]').exists()).toBe(false)
  })

  it('counts sessions rather than lessons on a live course', () => {
    expect(mountHero(liveCourse).text()).toContain('5 sesiones')
    expect(mountHero(baseCourse).text()).toContain('5 lecciones')
  })
})

describe('CurriculumAccordion — live sessions', () => {
  const sections = [
    {
      id: 1,
      title: 'Módulo 1',
      position: 0,
      lessons: [
        {
          id: 10,
          title: 'Sesión 1',
          position: 0,
          is_free: false,
          duration: 5400,
          starts_at: '2026-09-02T00:00:00.000000Z',
        },
        { id: 11, title: 'Lección grabada', position: 1, is_free: false, duration: 600 },
      ],
    },
  ]

  it('shows the schedule for a session and the duration for a recording', () => {
    const wrapper = mount(CurriculumAccordion, {
      props: { sections, openSections: { 1: true } },
      global: { stubs: { BaseBadge: true } },
    })

    // Only the scheduled lesson gets a date; the recording keeps its runtime.
    const dates = wrapper.findAll('[data-session-date]')
    expect(dates).toHaveLength(1)

    // Asserted against the same instant rather than a literal, because the
    // rendered string follows whatever timezone the test runner is in.
    const expected = new Date('2026-09-02T00:00:00.000000Z').toLocaleString('es-EC', {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    })
    expect(dates[0].text()).toBe(expected)

    // 600 seconds, rendered by the untouched duration path.
    expect(wrapper.text()).toContain('10:00')
  })
})
