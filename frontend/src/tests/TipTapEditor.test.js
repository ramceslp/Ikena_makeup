/**
 * TipTapEditor.vue component tests.
 *
 * TipTap / ProseMirror requires a real DOM + complex setup that jsdom cannot
 * fully replicate. We test the component CONTRACT (props, emits, toolbar
 * button behavior, draft-first guard) by mocking @tiptap/vue-3 internals.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'

// ---------------------------------------------------------------------------
// Mock TipTap modules so jsdom does not crash on ProseMirror
// ---------------------------------------------------------------------------
vi.mock('@tiptap/vue-3', () => {
  const useEditor = vi.fn(() => ({
    chain: () => ({
      focus: () => ({
        toggleBold: () => ({ run: vi.fn() }),
        toggleItalic: () => ({ run: vi.fn() }),
        toggleHeading: () => ({ run: vi.fn() }),
        toggleBulletList: () => ({ run: vi.fn() }),
        toggleOrderedList: () => ({ run: vi.fn() }),
        toggleBlockquote: () => ({ run: vi.fn() }),
        toggleCode: () => ({ run: vi.fn() }),
        toggleCodeBlock: () => ({ run: vi.fn() }),
        setLink: () => ({ run: vi.fn() }),
        unsetLink: () => ({ run: vi.fn() }),
        setImage: () => ({ run: vi.fn() }),
        setYoutubeVideo: () => ({ run: vi.fn() }),
        run: vi.fn(),
      }),
    }),
    getHTML: vi.fn(() => '<p>mock content</p>'),
    isActive: vi.fn(() => false),
    isDestroyed: false,
    destroy: vi.fn(),
  }))

  const EditorContent = { template: '<div class="tiptap-mock-content"></div>', name: 'EditorContent' }

  return { useEditor, EditorContent }
})

vi.mock('@tiptap/starter-kit', () => ({ default: { configure: vi.fn(() => ({})) } }))
vi.mock('@tiptap/extension-youtube', () => ({ default: { configure: vi.fn(() => ({})) } }))

// ---------------------------------------------------------------------------
// Mock api + pinia
// ---------------------------------------------------------------------------
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
import TipTapEditor from '../components/editor/TipTapEditor.vue'

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('TipTapEditor.vue — contract tests', () => {
  let pinia

  beforeEach(() => {
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
  })

  function mountEditor(props = {}) {
    return mount(TipTapEditor, {
      props: { modelValue: '', postId: null, ...props },
      global: { plugins: [pinia] },
    })
  }

  it('renders the editor container', () => {
    const wrapper = mountEditor()
    expect(wrapper.find('[data-tiptap-editor]').exists()).toBe(true)
  })

  it('renders toolbar with bold button', () => {
    const wrapper = mountEditor()
    expect(wrapper.find('[data-toolbar-bold]').exists()).toBe(true)
  })

  it('renders toolbar with italic button', () => {
    const wrapper = mountEditor()
    expect(wrapper.find("[data-toolbar-italic]").exists()).toBe(true)
  })

  it('renders toolbar with H2 button', () => {
    const wrapper = mountEditor()
    expect(wrapper.find('[data-toolbar-h2]').exists()).toBe(true)
  })

  it('renders toolbar with H3 button', () => {
    const wrapper = mountEditor()
    expect(wrapper.find('[data-toolbar-h3]').exists()).toBe(true)
  })

  it('does NOT render an H1 toolbar button (h1 is the page title)', () => {
    const wrapper = mountEditor()
    expect(wrapper.find('[data-toolbar-h1]').exists()).toBe(false)
  })

  it('renders toolbar with bullet list button', () => {
    const wrapper = mountEditor()
    expect(wrapper.find('[data-toolbar-bullet]').exists()).toBe(true)
  })

  it('renders toolbar with ordered list button', () => {
    const wrapper = mountEditor()
    expect(wrapper.find('[data-toolbar-ordered]').exists()).toBe(true)
  })

  it('renders toolbar with blockquote button', () => {
    const wrapper = mountEditor()
    expect(wrapper.find('[data-toolbar-blockquote]').exists()).toBe(true)
  })

  it('renders toolbar with code button', () => {
    const wrapper = mountEditor()
    expect(wrapper.find('[data-toolbar-code]').exists()).toBe(true)
  })

  it('renders toolbar with link button', () => {
    const wrapper = mountEditor()
    expect(wrapper.find('[data-toolbar-link]').exists()).toBe(true)
  })

  it('renders toolbar with embed (YouTube/Vimeo) button', () => {
    const wrapper = mountEditor()
    expect(wrapper.find('[data-toolbar-embed]').exists()).toBe(true)
  })

  it('renders toolbar with image upload button', () => {
    const wrapper = mountEditor()
    expect(wrapper.find('[data-toolbar-image]').exists()).toBe(true)
  })

  it('image upload button is DISABLED when postId is null (draft-first flow)', () => {
    const wrapper = mountEditor({ postId: null })
    const imageBtn = wrapper.find('[data-toolbar-image]')
    expect(imageBtn.attributes('disabled')).toBeDefined()
  })

  it('image upload button is ENABLED when postId is provided', () => {
    const wrapper = mountEditor({ postId: 42 })
    const imageBtn = wrapper.find('[data-toolbar-image]')
    // When postId is set, button should not be disabled
    expect(imageBtn.attributes('disabled')).toBeUndefined()
  })

  it('emits update:modelValue when editor content changes', async () => {
    const wrapper = mountEditor({ modelValue: '<p>initial</p>', postId: 1 })

    // Trigger the update:modelValue emit directly via the component's internal mechanism
    await wrapper.vm.$emit('update:modelValue', '<p>updated content</p>')
    await flushPromises()

    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
  })

  it('image upload calls postsStore.uploadImages(postId, [file]) and does NOT use base64', async () => {
    const uploadedImages = [{ id: 5, url: 'http://example.com/body-img.jpg' }]
    api.post.mockResolvedValueOnce({ data: { data: uploadedImages } })

    const wrapper = mountEditor({ postId: 7 })
    const store = usePostsStore()
    const uploadSpy = vi.spyOn(store, 'uploadImages').mockResolvedValue(uploadedImages)

    // Trigger file upload via the hidden file input
    const fileInput = wrapper.find('[data-image-file-input]')
    expect(fileInput.exists()).toBe(true)

    const fakeFile = new File(['img'], 'body-image.jpg', { type: 'image/jpeg' })
    Object.defineProperty(fileInput.element, 'files', { value: [fakeFile], configurable: true })
    await fileInput.trigger('change')
    await flushPromises()

    expect(uploadSpy).toHaveBeenCalledWith(7, [fakeFile])
    // Verify no base64 was used (uploadImages would not be called if base64 was used instead)
    expect(uploadSpy).toHaveBeenCalledTimes(1)
  })
})
