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
import News from '../views/News.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/noticias', component: News, name: 'News' },
    { path: '/noticias/:slug', component: { template: '<div/>' }, name: 'NewsDetail' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

const fakePosts = [
  {
    id: 1,
    title: 'Nuevo Curso de Verano',
    slug: 'nuevo-curso-verano',
    excerpt: 'Aprende técnicas avanzadas de maquillaje.',
    cover_image_url: 'http://example.com/cover1.jpg',
    type: 'nuevo_curso',
    is_published: true,
    published_at: '2026-06-01T00:00:00.000Z',
    cta_label: null,
    cta_url: null,
  },
  {
    id: 2,
    title: 'Oferta Especial',
    slug: 'oferta-especial',
    excerpt: 'Descuentos exclusivos este mes.',
    cover_image_url: null,
    type: 'oferta',
    is_published: true,
    published_at: '2026-06-05T00:00:00.000Z',
    cta_label: 'Ver oferta',
    cta_url: 'https://ikena.com/oferta',
  },
]

function mountNews(pinia) {
  return mount(News, { global: { plugins: [pinia, router] } })
}

describe('News.vue — public news list', () => {
  let pinia

  beforeEach(async () => {
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
    await router.push('/noticias')
  })

  it('calls fetchPosts on mount', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts, meta: { current_page: 1, last_page: 1, total: 2 } } })

    const store = usePostsStore()
    const fetchSpy = vi.spyOn(store, 'fetchPosts').mockResolvedValue()

    mountNews(pinia)
    await flushPromises()

    expect(fetchSpy).toHaveBeenCalled()
  })

  it('renders post titles after load', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts, meta: { current_page: 1, last_page: 1, total: 2 } } })

    const wrapper = mountNews(pinia)
    await flushPromises()

    expect(wrapper.text()).toContain('Nuevo Curso de Verano')
    expect(wrapper.text()).toContain('Oferta Especial')
  })

  it('renders excerpt for each post', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts, meta: {} } })

    const wrapper = mountNews(pinia)
    await flushPromises()

    expect(wrapper.text()).toContain('Aprende técnicas avanzadas de maquillaje.')
  })

  it('renders type badge for each post', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts, meta: {} } })

    const wrapper = mountNews(pinia)
    await flushPromises()

    // Type badges should be visible
    expect(wrapper.text()).toMatch(/nuevo_curso|Nuevo Curso/i)
  })

  it('renders post cards with data-post-card attribute', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts, meta: {} } })

    const wrapper = mountNews(pinia)
    await flushPromises()

    const cards = wrapper.findAll('[data-post-card]')
    expect(cards).toHaveLength(2)
  })

  it('shows empty state when posts array is empty', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } } })

    const store = usePostsStore()
    const wrapper = mountNews(pinia)
    await flushPromises()
    // Directly set store state to bypass async resolution issues
    store.posts = []
    store.postMeta = { current_page: 1, last_page: 1, total: 0 }
    store.loading = false
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-empty-state]').exists()).toBe(true)
  })

  it('shows pagination when last_page > 1', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakePosts, meta: { current_page: 1, last_page: 3, total: 30 } },
    })

    const store = usePostsStore()
    const wrapper = mountNews(pinia)
    await flushPromises()
    store.posts = fakePosts
    store.postMeta = { current_page: 1, last_page: 3, total: 30 }
    store.loading = false
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-pagination]').exists()).toBe(true)
  })

  it('hides pagination when last_page is 1', async () => {
    api.get.mockResolvedValueOnce({
      data: { data: fakePosts, meta: { current_page: 1, last_page: 1, total: 2 } },
    })

    const store = usePostsStore()
    const wrapper = mountNews(pinia)
    await flushPromises()
    store.posts = fakePosts
    store.postMeta = { current_page: 1, last_page: 1, total: 2 }
    store.loading = false
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-pagination]').exists()).toBe(false)
  })

  it('renders a "Leer más" link to /noticias/:slug when cta_label is null', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [fakePosts[0]], meta: {} } })

    const wrapper = mountNews(pinia)
    await flushPromises()

    const links = wrapper.findAll('a')
    const slugLink = links.find((l) => l.attributes('href') === '/noticias/nuevo-curso-verano')
    expect(slugLink).toBeDefined()
  })
})
