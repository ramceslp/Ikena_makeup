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
import CourseDetail from '../views/CourseDetail.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/cursos', component: { template: '<div/>' }, name: 'courses' },
      { path: '/cursos/:slug', component: CourseDetail, name: 'course-detail' },
      { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
    ],
  })
}

const fakeCourse = {
  id: 1,
  slug: 'maquillaje-nupcial',
  title: 'Maquillaje Nupcial Profesional',
  description: 'Domina el maquillaje de novias paso a paso.',
  price: '120.00',
  thumbnail: null,
  instructor: { id: 3, name: 'Ana Torres' },
  category: { id: 2, name: 'Novias', slug: 'novias' },
  total_lessons: 3,
  is_enrolled: false,
  average_rating: 4.8,
  reviews_count: 24,
  is_bestseller: true,
  offers_certificate: true,
  my_review: null,
  sections: [
    {
      id: 1,
      title: 'Introducción',
      position: 1,
      lessons: [
        { id: 1, title: 'Bienvenida', position: 1, is_free: true, duration: 125 },
        { id: 2, title: 'Materiales necesarios', position: 2, is_free: false, duration: 340 },
      ],
    },
    {
      id: 2,
      title: 'Técnicas avanzadas',
      position: 2,
      lessons: [
        { id: 3, title: 'Contorno de ojos', position: 1, is_free: false, duration: 610 },
      ],
    },
  ],
}

describe('CourseDetail.vue (App) — renders from the API [Spec: catalog browses successfully]', () => {
  let router

  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    router = makeRouter()
  })

  it('renders the course fetched from GET /courses/:slug', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeCourse } })
    await router.push('/cursos/maquillaje-nupcial')

    const wrapper = mount(CourseDetail, { global: { plugins: [router] } })
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/courses/maquillaje-nupcial')
    expect(wrapper.text()).toContain('Maquillaje Nupcial Profesional')
    expect(wrapper.text()).toContain('$120.00')
    expect(wrapper.text()).toContain('Ana Torres')
  })

  it('renders the curriculum outline grouped by section, with the first section open', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeCourse } })
    await router.push('/cursos/maquillaje-nupcial')

    const wrapper = mount(CourseDetail, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toContain('Introducción')
    expect(wrapper.text()).toContain('Técnicas avanzadas')

    const toggles = wrapper.findAll('[data-section-toggle]')
    expect(toggles[0].attributes('aria-expanded')).toBe('true')
    expect(toggles[1].attributes('aria-expanded')).toBe('false')

    // v-show (not v-if) hides collapsed sections, so all 3 lessons across both
    // sections stay in the DOM -- only their visibility toggles.
    const lessons = wrapper.findAll('[data-curriculum-lesson]')
    expect(lessons).toHaveLength(3)
    expect(lessons[0].text()).toContain('Bienvenida')
  })

  it('toggles a collapsed section open, and the previously-open first section closed, on click', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakeCourse } })
    await router.push('/cursos/maquillaje-nupcial')

    const wrapper = mount(CourseDetail, { global: { plugins: [router] } })
    await flushPromises()

    const toggles = wrapper.findAll('[data-section-toggle]')

    // Open the second (collapsed) section
    await toggles[1].trigger('click')
    expect(toggles[1].attributes('aria-expanded')).toBe('true')

    // Close the first (initially open, no explicit entry yet) section
    await toggles[0].trigger('click')
    expect(toggles[0].attributes('aria-expanded')).toBe('false')
  })

  it('renders an error state (not a crash) when the course cannot be loaded', async () => {
    const notFound = new Error('Not Found')
    notFound.response = { status: 404, data: { message: 'Curso no encontrado' } }
    api.get.mockRejectedValueOnce(notFound)
    await router.push('/cursos/missing')

    const wrapper = mount(CourseDetail, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toContain('Curso no encontrado')
  })
})
