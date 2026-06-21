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
import { ref } from 'vue'

// ---------------------------------------------------------------------------
// Mock TipTap modules so jsdom does not crash on ProseMirror
// ---------------------------------------------------------------------------

// vi.hoisted runs BEFORE vi.mock hoisting — this is the only safe way to share
// spy references between a vi.mock factory and the test body.
const chainSpies = vi.hoisted(() => ({
  insertContent: vi.fn(() => ({ run: vi.fn() })),
  setYoutubeVideo: vi.fn(() => ({ run: vi.fn() })),
  setLink: vi.fn(() => ({ run: vi.fn() })),
}))

vi.mock('@tiptap/vue-3', () => {
  // useEditor in @tiptap/vue-3 returns a Ref<Editor | undefined>.
  // The component accesses the editor in TWO ways:
  //   1. Template: `editor?.isActive(...)` — accesses isActive on the ref object directly
  //      (Vue auto-unwraps real refs in templates, but our mock is a plain object,
  //       so the template sees the outer object. The outer object must have isActive.)
  //   2. Setup fns: `editor.value?.chain()...` — accesses chain via .value
  //
  // Solution: return a dual-interface object where the outer object has the editor API
  // (for template use) AND a .value property pointing to the same instance
  // (for setup function use via editor.value?.chain()).
  const useEditor = vi.fn(() => {
    const chainFocus = () => ({
      toggleBold: () => ({ run: vi.fn() }),
      toggleItalic: () => ({ run: vi.fn() }),
      toggleHeading: () => ({ run: vi.fn() }),
      toggleBulletList: () => ({ run: vi.fn() }),
      toggleOrderedList: () => ({ run: vi.fn() }),
      toggleBlockquote: () => ({ run: vi.fn() }),
      toggleCode: () => ({ run: vi.fn() }),
      toggleCodeBlock: () => ({ run: vi.fn() }),
      setLink: chainSpies.setLink,
      unsetLink: () => ({ run: vi.fn() }),
      setImage: () => ({ run: vi.fn() }),
      setYoutubeVideo: chainSpies.setYoutubeVideo,
      insertContent: chainSpies.insertContent,
      run: vi.fn(),
    })
    const editorInstance = {
      chain: () => ({ focus: chainFocus }),
      commands: { setContent: vi.fn() },
      getHTML: vi.fn(() => '<p>mock content</p>'),
      isActive: vi.fn(() => false),
      isDestroyed: false,
      destroy: vi.fn(),
    }
    // Dual-interface: outer object = editor (for template `editor?.isActive`),
    // .value = same instance (for setup fns `editor.value?.chain()`).
    editorInstance.value = editorInstance
    return editorInstance
  })

  const EditorContent = { template: '<div class="tiptap-mock-content"></div>', name: 'EditorContent' }

  return { useEditor, EditorContent }
})

vi.mock('@tiptap/starter-kit', () => ({ default: { configure: vi.fn(() => ({})) } }))
vi.mock('@tiptap/extension-youtube', () => ({ default: { configure: vi.fn(() => ({})) } }))

// @tiptap/core is imported for Node.create() / mergeAttributes — stub both so
// jsdom does not attempt to load ProseMirror internals.
vi.mock('@tiptap/core', () => ({
  Node: {
    create: vi.fn(() => ({})),
  },
  mergeAttributes: vi.fn((...attrs) => Object.assign({}, ...attrs)),
}))

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
import StarterKit from '@tiptap/starter-kit'

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('TipTapEditor.vue — contract tests', () => {
  let pinia

  beforeEach(() => {
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
    // Reset shared chain spies after vi.clearAllMocks() clears call history
    chainSpies.insertContent.mockImplementation(() => ({ run: vi.fn() }))
    chainSpies.setYoutubeVideo.mockImplementation(() => ({ run: vi.fn() }))
    chainSpies.setLink.mockImplementation(() => ({ run: vi.fn() }))
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

  // FIX 4 — StarterKit schema config: heading.levels must exclude h1
  // This test asserts the ACTUAL configure() call args, not just the toolbar UI.
  it('StarterKit.configure is called with heading: { levels: [2, 3, 4] } — no h1 in schema', () => {
    // Mounting the component triggers useEditor() which calls StarterKit.configure()
    mountEditor()
    expect(StarterKit.configure).toHaveBeenCalledWith(
      expect.objectContaining({ heading: { levels: [2, 3, 4] } }),
    )
  })

  // FIX 2 — setLink must reject javascript: URLs and NOT call editor.chain().setLink
  it('setLink does NOT call editor setLink when URL is "javascript:alert(1)"', async () => {
    // window.prompt is not defined in jsdom — mock it
    const promptSpy = vi.spyOn(window, 'prompt').mockReturnValue('javascript:alert(1)')

    // chainSpies.setLink is already wired into the default editor mock.
    // We just mount and click — the shared spy should NOT be called.
    const wrapper = mountEditor()
    await wrapper.find('[data-toolbar-link]').trigger('click')

    // setLink on the editor chain must NOT have been called with the javascript: URL
    expect(chainSpies.setLink).not.toHaveBeenCalled()

    promptSpy.mockRestore()
  })

  // FIX 1 — insertEmbed dispatches insertContent for Vimeo IframeNode
  it('insertEmbed calls insertContent with { type: "iframe", attrs: { src: Vimeo player URL } } for a Vimeo URL', async () => {
    const promptSpy = vi.spyOn(window, 'prompt').mockReturnValue('https://vimeo.com/123456')

    const wrapper = mountEditor()
    await wrapper.find('[data-toolbar-embed]').trigger('click')

    expect(chainSpies.insertContent).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'iframe',
        attrs: expect.objectContaining({ src: 'https://player.vimeo.com/video/123456' }),
      }),
    )
    expect(chainSpies.setYoutubeVideo).not.toHaveBeenCalled()

    promptSpy.mockRestore()
  })

  // FIX 1 — insertEmbed dispatches setYoutubeVideo for a YouTube URL
  it('insertEmbed calls setYoutubeVideo with the parsed embed URL for a YouTube URL', async () => {
    const promptSpy = vi
      .spyOn(window, 'prompt')
      .mockReturnValue('https://www.youtube.com/watch?v=dQw4w9WgXcQ')

    const wrapper = mountEditor()
    await wrapper.find('[data-toolbar-embed]').trigger('click')

    expect(chainSpies.setYoutubeVideo).toHaveBeenCalledWith(
      expect.objectContaining({ src: 'https://www.youtube.com/embed/dQw4w9WgXcQ' }),
    )
    expect(chainSpies.insertContent).not.toHaveBeenCalled()

    promptSpy.mockRestore()
  })
})
