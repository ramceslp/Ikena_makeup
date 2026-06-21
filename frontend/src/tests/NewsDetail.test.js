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
import { usePostsStore } from '../stores/posts.js'
import NewsDetail from '../views/NewsDetail.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/noticias', component: { template: '<div/>' }, name: 'News' },
    { path: '/noticias/:slug', component: NewsDetail, name: 'NewsDetail' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

const fakePost = {
  id: 1,
  title: 'Nuevo Curso de Verano',
  slug: 'nuevo-curso-verano',
  excerpt: 'Aprende técnicas avanzadas de maquillaje.',
  cover_image_url: 'http://example.com/cover1.jpg',
  body: '<p>Este es el cuerpo del post con <strong>HTML</strong> seguro.</p>',
  type: 'nuevo_curso',
  is_published: true,
  published_at: '2026-06-01T00:00:00.000Z',
  author: 'Administrador',
  cta_label: null,
  cta_url: null,
}

async function mountNewsDetail(pinia, slug = 'nuevo-curso-verano') {
  await router.push(`/noticias/${slug}`)
  return mount(NewsDetail, { global: { plugins: [pinia, router] } })
}

describe('NewsDetail.vue — post detail page', () => {
  let pinia

  beforeEach(async () => {
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
  })

  it('renders the post title after loading', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const wrapper = await mountNewsDetail(pinia)
    await flushPromises()

    expect(wrapper.text()).toContain('Nuevo Curso de Verano')
  })

  it('renders body HTML via v-html (not escaped)', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const wrapper = await mountNewsDetail(pinia)
    await flushPromises()

    // The body should be rendered as real HTML, not escaped text
    const bodyEl = wrapper.find('[data-post-body]')
    expect(bodyEl.exists()).toBe(true)
    expect(bodyEl.element.innerHTML).toContain('<strong>HTML</strong>')
  })

  it('renders cover image when cover_image_url is present', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const wrapper = await mountNewsDetail(pinia)
    await flushPromises()

    const img = wrapper.find('[data-cover-image]')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('http://example.com/cover1.jpg')
  })

  it('does NOT render a broken img when cover_image_url is null', async () => {
    const postNoCover = { ...fakePost, cover_image_url: null }
    api.get.mockResolvedValueOnce({ data: { data: postNoCover } })

    const wrapper = await mountNewsDetail(pinia)
    await flushPromises()

    // No img with src pointing to null or broken src
    const coverImg = wrapper.find('[data-cover-image]')
    expect(coverImg.exists()).toBe(false)
  })

  it('renders author name', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const wrapper = await mountNewsDetail(pinia)
    await flushPromises()

    expect(wrapper.text()).toContain('Administrador')
  })

  it('renders published_at date', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const wrapper = await mountNewsDetail(pinia)
    await flushPromises()

    // The date should appear somewhere in the page
    const timeEl = wrapper.find('time')
    expect(timeEl.exists()).toBe(true)
  })

  it('renders a type badge', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const wrapper = await mountNewsDetail(pinia)
    await flushPromises()

    expect(wrapper.find('[data-type-badge]').exists()).toBe(true)
  })

  it('shows 404/error state when API returns 404', async () => {
    api.get.mockRejectedValueOnce({
      response: { status: 404, data: { message: 'No encontrado' } },
    })

    const wrapper = await mountNewsDetail(pinia, 'no-existe')
    await flushPromises()

    expect(wrapper.find('[data-not-found]').exists()).toBe(true)
  })

  it('shows loading state initially', async () => {
    let resolveCall
    api.get.mockImplementationOnce(
      () => new Promise((resolve) => { resolveCall = resolve }),
    )

    const wrapper = await mountNewsDetail(pinia)
    // Before flushPromises — loading should show
    expect(wrapper.find('[data-loading]').exists()).toBe(true)

    // Cleanup
    resolveCall({ data: { data: fakePost } })
    await flushPromises()
  })
})
