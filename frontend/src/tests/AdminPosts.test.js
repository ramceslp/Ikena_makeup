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
import AdminPosts from '../views/admin/AdminPosts.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/admin/noticias', component: AdminPosts, name: 'AdminPosts' },
    { path: '/admin/noticias/new', component: { template: '<div/>' }, name: 'AdminPostCreate' },
    { path: '/admin/noticias/:id/edit', component: { template: '<div/>' }, name: 'AdminPostEdit' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

const fakePosts = [
  {
    id: 1,
    title: 'Nuevo Curso de Verano',
    slug: 'nuevo-curso-verano',
    type: 'nuevo_curso',
    is_published: true,
    is_featured: false,
    published_at: '2026-06-01T00:00:00.000Z',
  },
  {
    id: 2,
    title: 'Oferta Especial',
    slug: 'oferta-especial',
    type: 'oferta',
    is_published: false,
    is_featured: false,
    published_at: null,
  },
]

function mountAdminPosts(pinia) {
  return mount(AdminPosts, { global: { plugins: [pinia, router] } })
}

describe('AdminPosts.vue — admin post list', () => {
  let pinia

  beforeEach(async () => {
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
    await router.push('/admin/noticias')
  })

  it('calls fetchAdminPosts on mount', async () => {
    // Spy BEFORE mounting — api.get is never called (spy intercepts fetchAdminPosts).
    // Do NOT add api.get.mockResolvedValueOnce here; it would not be consumed and
    // would leak into later tests (vi.clearAllMocks() does not clear the Once queue).
    const store = usePostsStore()
    const fetchSpy = vi.spyOn(store, 'fetchAdminPosts').mockResolvedValue()

    mountAdminPosts(pinia)
    await flushPromises()

    expect(fetchSpy).toHaveBeenCalled()
  })

  it('renders post titles in the table', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts, meta: {} } })

    const wrapper = mountAdminPosts(pinia)
    await flushPromises()

    expect(wrapper.text()).toContain('Nuevo Curso de Verano')
    expect(wrapper.text()).toContain('Oferta Especial')
  })

  it('renders type column for each post', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts, meta: {} } })

    const wrapper = mountAdminPosts(pinia)
    await flushPromises()

    expect(wrapper.text()).toMatch(/nuevo_curso|Nuevo Curso/i)
  })

  it('renders Publicado badge for published post', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts, meta: {} } })

    const wrapper = mountAdminPosts(pinia)
    await flushPromises()

    expect(wrapper.text()).toContain('Publicado')
  })

  it('renders Borrador badge for unpublished post', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts, meta: {} } })

    const wrapper = mountAdminPosts(pinia)
    await flushPromises()

    expect(wrapper.text()).toContain('Borrador')
  })

  it('each post row has an edit button', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts, meta: {} } })

    const wrapper = mountAdminPosts(pinia)
    await flushPromises()

    const editBtns = wrapper.findAll('[data-edit-btn]')
    expect(editBtns).toHaveLength(fakePosts.length)
  })

  it('each post row has a delete button', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts, meta: {} } })

    const wrapper = mountAdminPosts(pinia)
    await flushPromises()

    const deleteBtns = wrapper.findAll('[data-delete-btn]')
    expect(deleteBtns).toHaveLength(fakePosts.length)
  })

  it('shows empty state when no posts', async () => {
    // FIX 5: API mock returns empty payload; no manual store mutation.
    // Test fails if v-else-if="!posts.length" regresses.
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const wrapper = mountAdminPosts(pinia)
    await flushPromises()

    expect(wrapper.find('[data-empty-state]').exists()).toBe(true)
  })

  it('calls deletePost from store when delete is confirmed', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts, meta: {} } })
    api.get.mockResolvedValueOnce({ data: { data: [fakePosts[1]], meta: {} } })

    vi.spyOn(window, 'confirm').mockReturnValue(true)

    const store = usePostsStore()
    const wrapper = mountAdminPosts(pinia)
    await flushPromises()
    // Force store state so the table renders
    store.posts = [...fakePosts]
    store.loading = false
    await wrapper.vm.$nextTick()

    const deleteSpy = vi.spyOn(store, 'deletePost').mockResolvedValue()

    const deleteBtns = wrapper.findAll('[data-delete-btn]')
    await deleteBtns[0].trigger('click')
    await flushPromises()

    expect(deleteSpy).toHaveBeenCalledWith(fakePosts[0].id)
  })

  it('does NOT call deletePost when confirm is cancelled', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePosts, meta: {} } })

    vi.spyOn(window, 'confirm').mockReturnValue(false)

    const wrapper = mountAdminPosts(pinia)
    await flushPromises()

    const store = usePostsStore()
    const deleteSpy = vi.spyOn(store, 'deletePost')

    const deleteBtns = wrapper.findAll('[data-delete-btn]')
    await deleteBtns[0].trigger('click')
    await flushPromises()

    expect(deleteSpy).not.toHaveBeenCalled()
  })

  it('has a link/button to create new post', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const wrapper = mountAdminPosts(pinia)
    await flushPromises()

    // Should have some way to navigate to /admin/posts/new
    const newPostBtn = wrapper.find('[data-new-post-btn]')
    expect(newPostBtn.exists()).toBe(true)
  })
})
