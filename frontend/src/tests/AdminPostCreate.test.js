/**
 * AdminPostCreate.vue component tests.
 * TipTap is mocked to avoid jsdom limitations.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'

// Mock TipTap (same as TipTapEditor test)
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
vi.mock('@tiptap/core', () => ({
  Node: { create: vi.fn(() => ({})) },
  mergeAttributes: vi.fn((...attrs) => Object.assign({}, ...attrs)),
}))

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
import AdminPostCreate from '../views/admin/AdminPostCreate.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/admin/noticias/new', component: AdminPostCreate, name: 'AdminPostCreate' },
    { path: '/admin/noticias', component: { template: '<div/>' }, name: 'AdminPosts' },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

describe('AdminPostCreate.vue — create form with draft-first flow', () => {
  let pinia

  beforeEach(async () => {
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
    await router.push('/admin/noticias/new')
  })

  function mountCreate() {
    return mount(AdminPostCreate, { global: { plugins: [pinia, router] } })
  }

  it('renders title input field', () => {
    const wrapper = mountCreate()
    expect(wrapper.find('input[name="title"]').exists()).toBe(true)
  })

  it('renders slug input field', () => {
    const wrapper = mountCreate()
    expect(wrapper.find('input[name="slug"]').exists()).toBe(true)
  })

  it('renders excerpt textarea', () => {
    const wrapper = mountCreate()
    expect(wrapper.find('textarea[name="excerpt"]').exists()).toBe(true)
  })

  it('renders type select field', () => {
    const wrapper = mountCreate()
    expect(wrapper.find('select[name="type"]').exists()).toBe(true)
  })

  it('renders is_featured checkbox', () => {
    const wrapper = mountCreate()
    expect(wrapper.find('input[name="is_featured"]').exists()).toBe(true)
  })

  it('renders cta_label input', () => {
    const wrapper = mountCreate()
    expect(wrapper.find('input[name="cta_label"]').exists()).toBe(true)
  })

  it('renders cta_url input', () => {
    const wrapper = mountCreate()
    expect(wrapper.find('input[name="cta_url"]').exists()).toBe(true)
  })

  it('renders is_published checkbox', () => {
    const wrapper = mountCreate()
    expect(wrapper.find('input[name="is_published"]').exists()).toBe(true)
  })

  it('renders TipTapEditor (or its container)', () => {
    const wrapper = mountCreate()
    // TipTapEditor is async-loaded; the wrapper or its slot should exist
    expect(wrapper.find('[data-body-editor]').exists()).toBe(true)
  })

  it('calls createPost on form submit', async () => {
    const created = { id: 5, title: 'Test Post', slug: 'test-post' }
    const wrapper = mountCreate()

    const store = usePostsStore()
    const createSpy = vi.spyOn(store, 'createPost').mockResolvedValue(created)

    await wrapper.find('input[name="title"]').setValue('Test Post')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(createSpy).toHaveBeenCalledTimes(1)
    const [calledFd] = createSpy.mock.calls[0]
    expect(calledFd).toBeInstanceOf(FormData)
  })

  it('includes _method=PATCH ONLY on update, not on create', async () => {
    const created = { id: 5, title: 'Test', slug: 'test' }
    const wrapper = mountCreate()

    const store = usePostsStore()
    const createSpy = vi.spyOn(store, 'createPost').mockResolvedValue(created)

    await wrapper.find('input[name="title"]').setValue('Test')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    const [calledFd] = createSpy.mock.calls[0]
    // Create should NOT include _method spoofing
    expect(calledFd.get('_method')).toBeNull()
  })

  it('does NOT call createPost a second time while in-flight (double-submit guard)', async () => {
    const wrapper = mountCreate()

    const store = usePostsStore()
    let resolve
    const pending = new Promise((res) => { resolve = res })
    const createSpy = vi.spyOn(store, 'createPost').mockReturnValue(pending)

    await wrapper.find('input[name="title"]').setValue('Double Submit')
    await wrapper.find('form').trigger('submit.prevent')
    await wrapper.find('form').trigger('submit.prevent')

    resolve({ id: 1 })
    await flushPromises()

    expect(createSpy).toHaveBeenCalledTimes(1)
  })

  it('redirects to /admin/noticias after successful create', async () => {
    const created = { id: 5, title: 'Test', slug: 'test' }
    const wrapper = mountCreate()

    const store = usePostsStore()
    vi.spyOn(store, 'createPost').mockResolvedValue(created)

    await wrapper.find('input[name="title"]').setValue('Test')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/admin/noticias')
  })

  it('TipTapEditor postId is null before first save (draft-first)', () => {
    const wrapper = mountCreate()

    // Before any save, postId should be null — image upload disabled in editor
    const editorEl = wrapper.find('[data-body-editor]')
    expect(editorEl.exists()).toBe(true)
    // The postId prop passed to the editor wrapper should be null initially
    const postIdAttr = editorEl.attributes('data-post-id')
    expect(postIdAttr === undefined || postIdAttr === 'null' || postIdAttr === '').toBeTruthy()
  })
})
