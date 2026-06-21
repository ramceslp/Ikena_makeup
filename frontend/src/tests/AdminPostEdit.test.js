/**
 * AdminPostEdit.vue component tests.
 * TipTap is mocked to avoid jsdom limitations.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

// Mock TipTap
vi.mock('@tiptap/vue-3', () => {
  const useEditor = vi.fn(() => ({
    chain: () => ({ focus: () => ({ run: vi.fn() }) }),
    getHTML: vi.fn(() => '<p>body</p>'),
    isActive: vi.fn(() => false),
    isDestroyed: false,
    destroy: vi.fn(),
    commands: { setContent: vi.fn() },
  }))
  const EditorContent = { template: '<div class="tiptap-mock"></div>', name: 'EditorContent' }
  return { useEditor, EditorContent }
})
vi.mock('@tiptap/starter-kit', () => ({ default: { configure: vi.fn(() => ({})) } }))
vi.mock('@tiptap/extension-youtube', () => ({ default: { configure: vi.fn(() => ({})) } }))

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
import AdminPostEdit from '../views/admin/AdminPostEdit.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/admin/posts/:id/edit', component: AdminPostEdit, name: 'AdminPostEdit' },
    { path: '/admin/posts', component: { template: '<div/>' }, name: 'AdminPosts' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

const fakePost = {
  id: 7,
  title: 'Post a editar',
  slug: 'post-a-editar',
  excerpt: 'Extracto del post',
  body: '<p>Contenido existente</p>',
  type: 'noticia',
  is_featured: false,
  cta_label: 'Ver más',
  cta_url: 'https://example.com',
  is_published: true,
  cover_image_url: 'http://example.com/cover.jpg',
  images: [
    { id: 10, url: 'http://example.com/img1.jpg', sort_order: 0 },
  ],
}

describe('AdminPostEdit.vue — edit form', () => {
  let pinia

  beforeEach(async () => {
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
    await router.push('/admin/posts/7/edit')
  })

  function mountEdit() {
    return mount(AdminPostEdit, { global: { plugins: [pinia, router] } })
  }

  it('calls fetchAdminPost on mount', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const store = usePostsStore()
    const fetchSpy = vi.spyOn(store, 'fetchAdminPost').mockResolvedValue(fakePost)

    mountEdit()
    await flushPromises()

    expect(fetchSpy).toHaveBeenCalledWith('7')
  })

  it('populates form fields with existing post data', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const store = usePostsStore()
    vi.spyOn(store, 'fetchAdminPost').mockResolvedValue(fakePost)

    const wrapper = mountEdit()
    await flushPromises()

    expect(wrapper.find('input[name="title"]').element.value).toBe('Post a editar')
    expect(wrapper.find('input[name="slug"]').element.value).toBe('post-a-editar')
  })

  it('calls updatePost with FormData containing _method=PATCH on submit', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const store = usePostsStore()
    vi.spyOn(store, 'fetchAdminPost').mockResolvedValue(fakePost)
    const updateSpy = vi.spyOn(store, 'updatePost').mockResolvedValue(fakePost)

    const wrapper = mountEdit()
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(updateSpy).toHaveBeenCalledTimes(1)
    const [calledId, calledFd] = updateSpy.mock.calls[0]
    expect(calledFd).toBeInstanceOf(FormData)
    expect(calledFd.get('_method')).toBe('PATCH')
  })

  it('calls uploadCover when a cover file is selected and form submitted', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const store = usePostsStore()
    vi.spyOn(store, 'fetchAdminPost').mockResolvedValue(fakePost)
    vi.spyOn(store, 'updatePost').mockResolvedValue(fakePost)
    const coverSpy = vi.spyOn(store, 'uploadCover').mockResolvedValue({})

    const wrapper = mountEdit()
    await flushPromises()

    // Select a cover file
    const coverInput = wrapper.find('input[name="cover_image"]')
    expect(coverInput.exists()).toBe(true)
    const fakeFile = new File(['img'], 'new-cover.jpg', { type: 'image/jpeg' })
    Object.defineProperty(coverInput.element, 'files', { value: [fakeFile], configurable: true })
    await coverInput.trigger('change')

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(coverSpy).toHaveBeenCalledWith(fakePost.id, fakeFile)
  })

  it('renders existing body images', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const store = usePostsStore()
    vi.spyOn(store, 'fetchAdminPost').mockResolvedValue(fakePost)

    const wrapper = mountEdit()
    await flushPromises()

    const images = wrapper.findAll('[data-existing-image]')
    expect(images).toHaveLength(1)
  })

  it('calls deleteImage when delete image button is clicked', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const store = usePostsStore()
    vi.spyOn(store, 'fetchAdminPost').mockResolvedValue(fakePost)
    const deleteSpy = vi.spyOn(store, 'deleteImage').mockResolvedValue()

    const wrapper = mountEdit()
    await flushPromises()

    const deleteImgBtn = wrapper.find('[data-delete-image]')
    expect(deleteImgBtn.exists()).toBe(true)
    await deleteImgBtn.trigger('click')
    await flushPromises()

    expect(deleteSpy).toHaveBeenCalledWith(fakePost.id, 10)
  })

  it('TipTapEditor receives postId prop after post loads', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const store = usePostsStore()
    vi.spyOn(store, 'fetchAdminPost').mockResolvedValue(fakePost)

    const wrapper = mountEdit()
    await flushPromises()

    const editorWrapper = wrapper.find('[data-body-editor]')
    expect(editorWrapper.exists()).toBe(true)
    // postId should be set (the post id, as string or number)
    const postIdAttr = editorWrapper.attributes('data-post-id')
    expect(postIdAttr).toBe(String(fakePost.id))
  })

  it('does NOT call updatePost a second time while in-flight (double-submit guard)', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const store = usePostsStore()
    vi.spyOn(store, 'fetchAdminPost').mockResolvedValue(fakePost)

    let resolve
    const pending = new Promise((res) => { resolve = res })
    const updateSpy = vi.spyOn(store, 'updatePost').mockReturnValue(pending)

    const wrapper = mountEdit()
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await wrapper.find('form').trigger('submit.prevent')

    resolve(fakePost)
    await flushPromises()

    expect(updateSpy).toHaveBeenCalledTimes(1)
  })

  it('redirects to /admin/posts after successful update', async () => {
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const store = usePostsStore()
    vi.spyOn(store, 'fetchAdminPost').mockResolvedValue(fakePost)
    vi.spyOn(store, 'updatePost').mockResolvedValue(fakePost)

    const wrapper = mountEdit()
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/admin/posts')
  })
})
