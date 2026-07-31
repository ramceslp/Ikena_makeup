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

vi.mock('@capacitor/browser', () => ({
  Browser: { open: vi.fn().mockResolvedValue(undefined) },
}))

import api from '../services/api.js'
import { Browser } from '@capacitor/browser'
import { useMyCoursesStore } from '../stores/myCourses.js'
import MyCoursesSection from '../components/profile/MyCoursesSection.vue'

const course = {
  id: 4,
  title: 'Maquillaje Nupcial',
  slug: 'maquillaje-nupcial',
  thumbnail: null,
  instructor: { id: 3, name: 'Ana Torres' },
  total_lessons: 10,
  completed_lessons: 4,
  progress_percentage: 40,
  web_url: 'https://ikena.test/learn/maquillaje-nupcial',
}

function mountSection() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div/>' } },
      { path: '/cursos', component: { template: '<div/>' }, name: 'courses' },
    ],
  })
  return mount(MyCoursesSection, { global: { plugins: [router] } })
}

describe('MyCoursesSection.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('lists enrolled courses with their progress', async () => {
    const store = useMyCoursesStore()
    store.courses = [course]

    const wrapper = mountSection()
    await flushPromises()

    expect(wrapper.findAll('[data-my-course-row]')).toHaveLength(1)
    expect(wrapper.text()).toContain('Maquillaje Nupcial')
    expect(wrapper.text()).toContain('Ana Torres')
    // The bar is aria-hidden, so the same information must be readable as text.
    expect(wrapper.text()).toContain('4/10')
    expect(wrapper.text()).toContain('40%')
    expect(wrapper.find('[data-progress-bar]').attributes('style')).toContain('40%')
  })

  it('opens the web lesson player when a course is tapped', async () => {
    const store = useMyCoursesStore()
    store.courses = [course]

    const wrapper = mountSection()
    await wrapper.find('[data-my-course-row]').trigger('click')
    await flushPromises()

    expect(Browser.open).toHaveBeenCalledWith({
      url: 'https://ikena.test/learn/maquillaje-nupcial',
    })
  })

  it('surfaces an open failure instead of doing nothing visible', async () => {
    const store = useMyCoursesStore()
    store.courses = [{ ...course, web_url: null }]

    const wrapper = mountSection()
    await wrapper.find('[data-my-course-row]').trigger('click')
    await flushPromises()

    expect(Browser.open).not.toHaveBeenCalled()
    expect(wrapper.find('[data-my-courses-open-error]').exists()).toBe(true)
  })

  it('tells the user lessons open in the browser', async () => {
    const store = useMyCoursesStore()
    store.courses = [course]

    const wrapper = mountSection()
    await flushPromises()

    expect(wrapper.text()).toContain('Las lecciones se abren en tu navegador')
  })

  it('offers a way into the catalog when there are no enrollments', async () => {
    const wrapper = mountSection()
    await flushPromises()

    expect(wrapper.find('[data-my-courses-empty]').exists()).toBe(true)
    expect(wrapper.find('[data-browse-courses]').exists()).toBe(true)
  })

  it('shows a skeleton, not the empty state, while loading', async () => {
    const store = useMyCoursesStore()
    store.loading = true

    const wrapper = mountSection()
    await flushPromises()

    expect(wrapper.find('[data-my-courses-skeleton]').exists()).toBe(true)
    expect(wrapper.find('[data-my-courses-empty]').exists()).toBe(false)
  })

  it('distinguishes a failed fetch from an empty list and offers a retry', async () => {
    const store = useMyCoursesStore()
    store.error = 'Error al cargar tus cursos'

    const wrapper = mountSection()
    await flushPromises()

    expect(wrapper.find('[data-my-courses-error]').exists()).toBe(true)
    expect(wrapper.find('[data-my-courses-empty]').exists()).toBe(false)

    api.get.mockResolvedValueOnce({ data: { data: [course] } })
    await wrapper.find('[data-my-courses-retry]').trigger('click')
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/my-courses')
  })
})
