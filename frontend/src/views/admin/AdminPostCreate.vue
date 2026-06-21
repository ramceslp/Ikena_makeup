<script setup>
import { ref, defineAsyncComponent } from 'vue'
import { useRouter } from 'vue-router'
import { usePostsStore } from '../../stores/posts.js'
import BaseButton from '../../components/ui/BaseButton.vue'

// Lazy-load TipTapEditor so ProseMirror stays out of the public bundle
const TipTapEditor = defineAsyncComponent(() =>
  import('../../components/editor/TipTapEditor.vue'),
)

const router = useRouter()
const postsStore = usePostsStore()

// Draft-first: postId is null until the post is created for the first time.
// The TipTapEditor image button is disabled while postId is null.
const postId = ref(null)

const loading = ref(false)
const error = ref('')

const form = ref({
  title: '',
  slug: '',
  excerpt: '',
  body: '',
  type: 'noticia',
  is_featured: false,
  cta_label: '',
  cta_url: '',
  is_published: false,
})

// Cover image file
const coverFile = ref(null)

function onCoverChange(e) {
  coverFile.value = e.target.files?.[0] ?? null
}

function buildFormData() {
  const fd = new FormData()
  fd.append('title', form.value.title)
  fd.append('slug', form.value.slug)
  fd.append('excerpt', form.value.excerpt ?? '')
  fd.append('body', form.value.body ?? '')
  fd.append('type', form.value.type)
  fd.append('is_featured', form.value.is_featured ? '1' : '0')
  fd.append('cta_label', form.value.cta_label ?? '')
  fd.append('cta_url', form.value.cta_url ?? '')
  fd.append('is_published', form.value.is_published ? '1' : '0')
  return fd
}

async function handleSubmit() {
  if (loading.value) return
  loading.value = true
  error.value = ''
  try {
    const fd = buildFormData()
    const created = await postsStore.createPost(fd)
    postId.value = created.id

    // Upload cover image if one was selected
    if (coverFile.value) {
      await postsStore.uploadCover(created.id, coverFile.value)
    }

    router.push('/admin/posts')
  } catch (err) {
    error.value = err.response?.data?.message || 'Error al crear la publicación'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-gutter py-12">
    <div class="mb-8">
      <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Nueva Publicación</h1>
      <p class="font-body-md text-body-md text-on-surface-variant mt-1">
        Completa la información para crear una nueva publicación.
      </p>
    </div>

    <div v-if="error" class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container">
      {{ error }}
    </div>

    <div class="bg-surface rounded-2xl border border-blush-canvas/20 p-8">
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Title -->
        <div>
          <label for="title" class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Título *</label>
          <input
            id="title"
            name="title"
            v-model="form.title"
            type="text"
            required
            class="w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
          />
        </div>

        <!-- Slug -->
        <div>
          <label for="slug" class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Slug (URL)</label>
          <input
            id="slug"
            name="slug"
            v-model="form.slug"
            type="text"
            placeholder="Se genera automáticamente si se deja vacío"
            class="w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
          />
        </div>

        <!-- Type -->
        <div>
          <label for="type" class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Tipo</label>
          <select
            id="type"
            name="type"
            v-model="form.type"
            class="w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
          >
            <option value="noticia">Noticia</option>
            <option value="nuevo_curso">Nuevo Curso</option>
            <option value="oferta">Oferta</option>
            <option value="evento">Evento</option>
            <option value="lanzamiento">Lanzamiento</option>
            <option value="certificacion">Certificación</option>
            <option value="contenido">Contenido</option>
          </select>
        </div>

        <!-- Excerpt -->
        <div>
          <label for="excerpt" class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Extracto</label>
          <textarea
            id="excerpt"
            name="excerpt"
            v-model="form.excerpt"
            rows="3"
            placeholder="Breve descripción que aparece en el listado"
            class="w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
          />
        </div>

        <!-- Body editor -->
        <div>
          <label class="block font-label-md text-label-md text-on-surface-variant mb-1.5">
            Contenido
            <span v-if="!postId" class="text-xs text-amber-600 ml-2">
              (Guarda el post primero para habilitar la subida de imágenes)
            </span>
          </label>
          <div data-body-editor :data-post-id="postId">
            <Suspense>
              <TipTapEditor
                v-model="form.body"
                :postId="postId"
              />
              <template #fallback>
                <div class="min-h-[200px] rounded-xl border border-blush-canvas/40 flex items-center justify-center">
                  <span class="font-body-sm text-body-sm text-on-surface-variant">Cargando editor...</span>
                </div>
              </template>
            </Suspense>
          </div>
        </div>

        <!-- Cover image -->
        <div>
          <label for="cover_image" class="block font-label-md text-label-md text-on-surface-variant mb-1.5">
            Imagen de portada
          </label>
          <input
            id="cover_image"
            name="cover_image"
            type="file"
            accept="image/*"
            @change="onCoverChange"
            class="block w-full font-body-sm text-body-sm text-on-surface-variant file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-apricot-glow file:text-deep-marsala file:font-label-md"
          />
        </div>

        <!-- CTA -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="cta_label" class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Texto del CTA</label>
            <input
              id="cta_label"
              name="cta_label"
              v-model="form.cta_label"
              type="text"
              placeholder="Ej: Ver más, Inscribirme..."
              class="w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
            />
          </div>
          <div>
            <label for="cta_url" class="block font-label-md text-label-md text-on-surface-variant mb-1.5">URL del CTA</label>
            <input
              id="cta_url"
              name="cta_url"
              v-model="form.cta_url"
              type="url"
              placeholder="https://..."
              class="w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
            />
          </div>
        </div>

        <!-- Toggles -->
        <div class="space-y-3">
          <label for="is_featured" class="flex items-center gap-3 cursor-pointer">
            <input
              id="is_featured"
              name="is_featured"
              v-model="form.is_featured"
              type="checkbox"
              class="w-4 h-4 rounded accent-primary"
            />
            <span class="font-body-md text-body-md text-on-surface">Publicación destacada (Hero)</span>
          </label>

          <label for="is_published" class="flex items-center gap-3 cursor-pointer">
            <input
              id="is_published"
              name="is_published"
              v-model="form.is_published"
              type="checkbox"
              class="w-4 h-4 rounded accent-primary"
            />
            <span class="font-body-md text-body-md text-on-surface">Publicar inmediatamente</span>
          </label>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3 pt-2">
          <BaseButton type="button" variant="outline" @click="router.push('/admin/posts')">
            Cancelar
          </BaseButton>
          <BaseButton type="submit" variant="primary" :disabled="loading">
            {{ loading ? 'Guardando...' : 'Crear publicación' }}
          </BaseButton>
        </div>
      </form>
    </div>
  </div>
</template>
