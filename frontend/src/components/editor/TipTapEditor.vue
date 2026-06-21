<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Youtube from '@tiptap/extension-youtube'
import { usePostsStore } from '../../stores/posts.js'

// ---------------------------------------------------------------------------
// Props & Emits
// ---------------------------------------------------------------------------
const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  postId: {
    type: Number,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue'])

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------
const postsStore = usePostsStore()

// ---------------------------------------------------------------------------
// Hidden file input ref for image upload
// ---------------------------------------------------------------------------
const imageFileInputRef = ref(null)

// ---------------------------------------------------------------------------
// Editor setup
// ---------------------------------------------------------------------------
const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({
      // CRITICAL: NO h1 — h1 is the page title; backend purifier strips h1
      heading: { levels: [2, 3, 4] },
    }),
    Youtube.configure({ controls: false }),
  ],
  onUpdate({ editor }) {
    emit('update:modelValue', editor.getHTML())
  },
})

// Sync external model value changes into the editor
watch(
  () => props.modelValue,
  (value) => {
    if (editor.value && editor.value.getHTML() !== value) {
      editor.value.commands.setContent(value, false)
    }
  },
)

onBeforeUnmount(() => {
  editor.value?.destroy()
})

// ---------------------------------------------------------------------------
// Toolbar actions
// ---------------------------------------------------------------------------
function toggleBold() {
  editor.value?.chain().focus().toggleBold().run()
}

function toggleItalic() {
  editor.value?.chain().focus().toggleItalic().run()
}

function toggleHeading(level) {
  editor.value?.chain().focus().toggleHeading({ level }).run()
}

function toggleBulletList() {
  editor.value?.chain().focus().toggleBulletList().run()
}

function toggleOrderedList() {
  editor.value?.chain().focus().toggleOrderedList().run()
}

function toggleBlockquote() {
  editor.value?.chain().focus().toggleBlockquote().run()
}

function toggleCode() {
  editor.value?.chain().focus().toggleCode().run()
}

function setLink() {
  const url = window.prompt('Ingresa la URL del enlace')
  if (!url) {
    editor.value?.chain().focus().unsetLink().run()
    return
  }
  editor.value?.chain().focus().setLink({ href: url }).run()
}

// ---------------------------------------------------------------------------
// Image upload (draft-first: only enabled when postId is set)
// ---------------------------------------------------------------------------
function triggerImageUpload() {
  if (!props.postId) return
  imageFileInputRef.value?.click()
}

async function onImageFileChange(event) {
  const files = Array.from(event.target.files || [])
  if (!files.length || !props.postId) return

  try {
    const uploaded = await postsStore.uploadImages(props.postId, files)
    if (uploaded && uploaded.length > 0) {
      // Insert all uploaded images into the editor as image nodes
      for (const img of uploaded) {
        editor.value?.chain().focus().setImage({ src: img.url }).run()
      }
    }
  } catch {
    // Silent — upload error should surface in parent component
  } finally {
    // Reset input so the same file can be selected again
    if (imageFileInputRef.value) imageFileInputRef.value.value = ''
  }
}

// ---------------------------------------------------------------------------
// Embed (YouTube / Vimeo) — URL dialog
// ---------------------------------------------------------------------------
function insertEmbed() {
  const url = window.prompt('Ingresa la URL del video (YouTube o Vimeo)')
  if (!url) return

  if (/youtube\.com|youtu\.be/.test(url)) {
    editor.value?.chain().focus().setYoutubeVideo({ src: url }).run()
  } else if (/vimeo\.com/.test(url)) {
    // Vimeo: extract ID and build embed URL, then insert as raw iframe via insertContent
    const vimeoMatch = url.match(/vimeo\.com\/(\d+)/)
    if (vimeoMatch) {
      const embedUrl = `https://player.vimeo.com/video/${vimeoMatch[1]}`
      editor.value?.chain().focus().insertContent(
        `<iframe src="${embedUrl}" width="640" height="360" frameborder="0" allowfullscreen></iframe>`,
      ).run()
    }
  }
}
</script>

<template>
  <div data-tiptap-editor class="rounded-xl border border-blush-canvas/40 overflow-hidden">
    <!-- Toolbar -->
    <div class="flex flex-wrap items-center gap-1 p-2 border-b border-blush-canvas/20 bg-surface-container-low">
      <!-- Bold -->
      <button
        type="button"
        data-toolbar-bold
        @click="toggleBold"
        title="Negrita"
        class="p-1.5 rounded hover:bg-surface-container transition-colors"
        :class="{ 'bg-surface-container': editor?.isActive('bold') }"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">format_bold</span>
      </button>

      <!-- Italic -->
      <button
        type="button"
        data-toolbar-italic
        @click="toggleItalic"
        title="Cursiva"
        class="p-1.5 rounded hover:bg-surface-container transition-colors"
        :class="{ 'bg-surface-container': editor?.isActive('italic') }"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">format_italic</span>
      </button>

      <div class="w-px h-5 bg-blush-canvas/30 mx-0.5" />

      <!-- H2 -->
      <button
        type="button"
        data-toolbar-h2
        @click="toggleHeading(2)"
        title="Título H2"
        class="p-1.5 rounded text-xs font-semibold hover:bg-surface-container transition-colors"
        :class="{ 'bg-surface-container': editor?.isActive('heading', { level: 2 }) }"
      >
        H2
      </button>

      <!-- H3 -->
      <button
        type="button"
        data-toolbar-h3
        @click="toggleHeading(3)"
        title="Título H3"
        class="p-1.5 rounded text-xs font-semibold hover:bg-surface-container transition-colors"
        :class="{ 'bg-surface-container': editor?.isActive('heading', { level: 3 }) }"
      >
        H3
      </button>

      <div class="w-px h-5 bg-blush-canvas/30 mx-0.5" />

      <!-- Bullet list -->
      <button
        type="button"
        data-toolbar-bullet
        @click="toggleBulletList"
        title="Lista sin orden"
        class="p-1.5 rounded hover:bg-surface-container transition-colors"
        :class="{ 'bg-surface-container': editor?.isActive('bulletList') }"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">format_list_bulleted</span>
      </button>

      <!-- Ordered list -->
      <button
        type="button"
        data-toolbar-ordered
        @click="toggleOrderedList"
        title="Lista numerada"
        class="p-1.5 rounded hover:bg-surface-container transition-colors"
        :class="{ 'bg-surface-container': editor?.isActive('orderedList') }"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">format_list_numbered</span>
      </button>

      <div class="w-px h-5 bg-blush-canvas/30 mx-0.5" />

      <!-- Blockquote -->
      <button
        type="button"
        data-toolbar-blockquote
        @click="toggleBlockquote"
        title="Cita"
        class="p-1.5 rounded hover:bg-surface-container transition-colors"
        :class="{ 'bg-surface-container': editor?.isActive('blockquote') }"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">format_quote</span>
      </button>

      <!-- Code -->
      <button
        type="button"
        data-toolbar-code
        @click="toggleCode"
        title="Código en línea"
        class="p-1.5 rounded hover:bg-surface-container transition-colors"
        :class="{ 'bg-surface-container': editor?.isActive('code') }"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">code</span>
      </button>

      <div class="w-px h-5 bg-blush-canvas/30 mx-0.5" />

      <!-- Link -->
      <button
        type="button"
        data-toolbar-link
        @click="setLink"
        title="Insertar enlace"
        class="p-1.5 rounded hover:bg-surface-container transition-colors"
        :class="{ 'bg-surface-container': editor?.isActive('link') }"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">link</span>
      </button>

      <!-- Embed (YouTube / Vimeo) -->
      <button
        type="button"
        data-toolbar-embed
        @click="insertEmbed"
        title="Insertar video (YouTube o Vimeo)"
        class="p-1.5 rounded hover:bg-surface-container transition-colors"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">smart_display</span>
      </button>

      <!-- Image upload (draft-first: disabled without postId) -->
      <button
        type="button"
        data-toolbar-image
        @click="triggerImageUpload"
        :disabled="!postId"
        :title="postId ? 'Subir imagen' : 'Guarda el post primero para poder insertar imágenes'"
        class="p-1.5 rounded hover:bg-surface-container transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">image</span>
      </button>

      <!-- Hidden file input for image upload -->
      <input
        ref="imageFileInputRef"
        data-image-file-input
        type="file"
        accept="image/*"
        multiple
        class="hidden"
        @change="onImageFileChange"
      />
    </div>

    <!-- Editor content area -->
    <EditorContent
      :editor="editor"
      class="min-h-[200px] px-4 py-3 font-body-md text-body-md text-on-surface focus-within:outline-none [&_.ProseMirror]:outline-none [&_.ProseMirror]:min-h-[180px]"
    />
  </div>
</template>
