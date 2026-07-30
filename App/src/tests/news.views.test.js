import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

// News catalog + detail (push-notifications Slice 5b). These are the routes
// the news push notification's deep link lands on, and the ones Home.vue's
// news sections have always linked to.
vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

import api from '../services/api.js'
import News from '../views/News.vue'
import NewsDetail from '../views/NewsDetail.vue'
import { usePostsStore } from '../stores/posts.js'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/noticias', name: 'news', component: News },
    { path: '/noticias/:slug', name: 'news-detail', component: NewsDetail },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

function card(overrides = {}) {
  return {
    id: 1,
    title: 'Nueva colección de labiales',
    slug: 'nueva-coleccion-labiales',
    excerpt: 'Ya disponible en el catálogo',
    type: 'noticia',
    is_featured: false,
    cta_label: null,
    cta_url: null,
    published_at: '2026-07-20T10:00:00-05:00',
    cover_image_url: null,
    ...overrides,
  }
}

function listResponse(posts = [card()], meta = {}) {
  return {
    data: {
      data: posts,
      meta: { current_page: 1, last_page: 1, total: posts.length, ...meta },
    },
  }
}

describe('News catalog', () => {
  let pinia

  beforeEach(() => {
    vi.clearAllMocks()
    pinia = createPinia()
    setActivePinia(pinia)
  })

  function mountNews() {
    return mount(News, { global: { plugins: [pinia, router] } })
  }

  it('loads and renders posts on mount', async () => {
    api.get.mockResolvedValue(listResponse())
    const wrapper = mountNews()
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/posts', { params: { page: 1 } })
    expect(wrapper.findAll('[data-news-card]')).toHaveLength(1)
    expect(wrapper.text()).toContain('Nueva colección de labiales')
  })

  it('links each card to its detail route', async () => {
    api.get.mockResolvedValue(listResponse())
    const wrapper = mountNews()
    await flushPromises()

    expect(wrapper.find('[data-news-card]').attributes('href')).toBe(
      '/noticias/nueva-coleccion-labiales',
    )
  })

  /**
   * A cover_image_url that is set but fails to LOAD is worse than none: the
   * browser draws its broken-image glyph plus the alt text, which overflows
   * the thumbnail and collides with the "Destacada" badge. Observed for real
   * on the emulator when a seeded placeholder host returned a 500.
   */
  it('falls back to the placeholder when a cover image fails to load', async () => {
    api.get.mockResolvedValue(listResponse([card({ cover_image_url: 'http://broken.test/x.jpg' })]))

    const wrapper = mountNews()
    await flushPromises()

    expect(wrapper.find('[data-cover-image]').exists()).toBe(true)
    expect(wrapper.find('[data-cover-placeholder]').exists()).toBe(false)

    await wrapper.find('[data-cover-image]').trigger('error')

    expect(wrapper.find('[data-cover-image]').exists()).toBe(false)
    expect(wrapper.find('[data-cover-placeholder]').exists()).toBe(true)
  })

  it('shows an empty state when there are no posts', async () => {
    api.get.mockResolvedValue(listResponse([]))
    const wrapper = mountNews()
    await flushPromises()

    expect(wrapper.find('[data-empty-state]').exists()).toBe(true)
  })

  it('shows an error state when the request fails', async () => {
    api.get.mockRejectedValue({ response: { status: 500, data: {} } })
    const wrapper = mountNews()
    await flushPromises()

    expect(wrapper.find('[data-error]').exists()).toBe(true)
  })

  it('debounces the search and sends it as a query param', async () => {
    vi.useFakeTimers()
    api.get.mockResolvedValue(listResponse())

    const wrapper = mountNews()
    await flushPromises()
    api.get.mockClear()

    await wrapper.find('[data-news-search]').setValue('labiales')
    expect(api.get).not.toHaveBeenCalled() // still debouncing

    vi.advanceTimersByTime(400)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/posts', { params: { page: 1, search: 'labiales' } })
    vi.useRealTimers()
  })

  it('paginates', async () => {
    api.get.mockResolvedValue(listResponse([card()], { current_page: 1, last_page: 3 }))
    const wrapper = mountNews()
    await flushPromises()

    await wrapper.find('[data-next-page]').trigger('click')
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/posts', { params: { page: 2 } })
  })

  it('hides pagination on a single page', async () => {
    api.get.mockResolvedValue(listResponse())
    const wrapper = mountNews()
    await flushPromises()

    expect(wrapper.find('[data-next-page]').exists()).toBe(false)
  })

  /**
   * Without the onBeforeUnmount cleanup, a pending debounce fires after the
   * view is gone and mutates a store the user has navigated away from.
   */
  it('cancels a pending search when unmounted', async () => {
    vi.useFakeTimers()
    api.get.mockResolvedValue(listResponse())

    const wrapper = mountNews()
    await flushPromises()

    await wrapper.find('[data-news-search]').setValue('labiales')
    wrapper.unmount()
    api.get.mockClear()

    vi.advanceTimersByTime(400)
    await flushPromises()

    expect(api.get).not.toHaveBeenCalled()
    vi.useRealTimers()
  })
})

describe('News detail', () => {
  let pinia

  beforeEach(() => {
    vi.clearAllMocks()
    pinia = createPinia()
    setActivePinia(pinia)
  })

  async function mountDetail(slug = 'nueva-coleccion-labiales') {
    await router.push(`/noticias/${slug}`)
    await router.isReady()
    return mount(NewsDetail, { global: { plugins: [pinia, router] } })
  }

  function detail(overrides = {}) {
    return {
      data: {
        data: {
          ...card(),
          body: '<p>Contenido completo</p>',
          author: { id: 1, name: 'Ikena' },
          images: [],
          ...overrides,
        },
      },
    }
  }

  it('fetches the post by slug from the route', async () => {
    api.get.mockResolvedValue(detail())
    await mountDetail()
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/posts/nueva-coleccion-labiales')
  })

  it('renders the post', async () => {
    api.get.mockResolvedValue(detail())
    const wrapper = await mountDetail()
    await flushPromises()

    expect(wrapper.text()).toContain('Nueva colección de labiales')
    expect(wrapper.find('[data-post-body]').html()).toContain('Contenido completo')
    expect(wrapper.text()).toContain('Ikena')
  })

  it('shows a friendly message for a missing post', async () => {
    api.get.mockRejectedValue({ response: { status: 404, data: {} } })
    const wrapper = await mountDetail('no-existe')
    await flushPromises()

    expect(wrapper.find('[data-error]').exists()).toBe(true)
    expect(wrapper.text()).toContain('No encontramos esta noticia')
  })

  it('renders a safe external CTA', async () => {
    api.get.mockResolvedValue(
      detail({ cta_label: 'Ver más', cta_url: 'https://ikena.example/promo' }),
    )
    const wrapper = await mountDetail()
    await flushPromises()

    const cta = wrapper.find('[data-cta-link]')
    expect(cta.attributes('href')).toBe('https://ikena.example/promo')
    expect(cta.attributes('rel')).toBe('noopener noreferrer')
  })

  /**
   * cta_url is admin-authored free text; safeCtaUrl exists to keep a
   * javascript:/data: URL out of an href.
   */
  it('drops a javascript: CTA url', async () => {
    api.get.mockResolvedValue(detail({ cta_label: 'Ver más', cta_url: 'javascript:alert(1)' }))
    const wrapper = await mountDetail()
    await flushPromises()

    expect(wrapper.find('[data-cta-link]').exists()).toBe(false)
  })

  it('hides a cover image that fails to load', async () => {
    api.get.mockResolvedValue(detail({ cover_image_url: 'http://broken.test/x.jpg' }))
    const wrapper = await mountDetail()
    await flushPromises()

    expect(wrapper.find('[data-cover-image]').exists()).toBe(true)

    await wrapper.find('[data-cover-image]').trigger('error')

    expect(wrapper.find('[data-cover-image]').exists()).toBe(false)
    // The headline immediately below already carries the meaning.
    expect(wrapper.text()).toContain('Nueva colección de labiales')
  })

  it('renders the image gallery when present', async () => {
    api.get.mockResolvedValue(
      detail({ images: [{ id: 1, url: 'http://x/1.jpg', sort_order: 0 }] }),
    )
    const wrapper = await mountDetail()
    await flushPromises()

    expect(wrapper.findAll('[data-gallery-image]')).toHaveLength(1)
  })
})

describe('posts store — list and detail fetchers', () => {
  let store

  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
    store = usePostsStore()
  })

  it('omits an empty search from the query params', async () => {
    api.get.mockResolvedValue(listResponse())
    await store.fetchPosts({ search: '', page: 2 })

    expect(api.get).toHaveBeenCalledWith('/posts', { params: { page: 2 } })
  })

  /**
   * Params are built locally so a later unfiltered call cannot inherit an
   * earlier search term.
   */
  it('does not carry a search term into a later unfiltered call', async () => {
    api.get.mockResolvedValue(listResponse())

    await store.fetchPosts({ search: 'labiales' })
    await store.fetchPosts()

    expect(api.get).toHaveBeenLastCalledWith('/posts', { params: { page: 1 } })
  })

  it('clears the list on failure rather than leaving stale posts', async () => {
    api.get.mockResolvedValueOnce(listResponse())
    await store.fetchPosts()
    expect(store.posts).toHaveLength(1)

    api.get.mockRejectedValueOnce({ response: { status: 500, data: {} } })
    await store.fetchPosts()

    expect(store.posts).toEqual([])
    expect(store.error).toBeTruthy()
  })

  it('rethrows from fetchPost so the detail view can react to a 404', async () => {
    api.get.mockRejectedValue({ response: { status: 404, data: {} } })

    await expect(store.fetchPost('no-existe')).rejects.toBeTruthy()
    expect(store.currentPost).toBeNull()
  })
})
