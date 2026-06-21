/**
 * Tests for MyCourses.vue — empty state.
 * Covers the "Explorar cursos" CTA pointing to the relocated catalog at /cursos
 * (PR3 portal: the course catalog moved from / to /cursos).
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
    patch: vi.fn(),
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
}))

import api from '../services/api.js'
import MyCourses from '../views/MyCourses.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/cursos', name: 'Cursos', component: { template: '<div/>' } },
    { path: '/learn/:slug', name: 'Player', component: { template: '<div/>' } },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

describe('MyCourses.vue — empty state', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('points the "Explorar cursos" CTA to /cursos when no enrolled courses', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mount(MyCourses, {
      global: { plugins: [router] },
    })
    await flushPromises()

    const explore = wrapper.findAll('a').find((a) => a.text().includes('Explorar cursos'))
    expect(explore).toBeDefined()
    expect(explore.attributes('href')).toBe('/cursos')
  })
})
