<script setup>
import { ref, onMounted, computed, defineAsyncComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePostsStore } from '../../stores/posts.js'
import BaseButton from '../../components/ui/BaseButton.vue'

// Lazy-load TipTapEditor so ProseMirror stays out of the public bundle
const TipTapEditor = defineAsyncComponent(() =>
  import('../../components/editor/TipTapEditor.vue'),
)

const route = useRoute()
const router = useRouter()
const postsStore = usePostsStore()

const postId = computed(() => route.params.id)
const loading = ref(false)
const fetchError = ref('')
const saveError = ref('')
const loaded = ref(false)

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

// Images & cover
const images = ref([])
const coverFile = ref(null)
const deletingImageId = ref(null)

async function loadData() {
  loading.value = true
  fetchError.value = ''
  try {
    const post = await postsStore.fetchAdminPost(postId.value)
    form.value = {
      title: post.title ?? '',
      slug: post.slug ?? '',
      excerpt: post.excerpt ?? '',
      body: post.body ?? '',
      type: post.type ?? 'noticia',
      is_featured: !!post.is_featured,
      cta_label: post.cta_label ?? '',
      cta_url: post.cta_url ?? '',
      is_published: !!post.is_published,
    }
    images.value = post.images ?? []
    loaded.value = true
  } catch (err) {
    fetchError.value = err.response?.data?.message || 'Error al cargar la publicación'
  } finally {
    loading.value = false
  }
}

function onCoverChange(e) {
  coverFile.value = e.target.files?.[0] ?? null
}

function buildFormData() {
  const fd = new FormData()
  // The admin update route is registered as POST (not PATCH); spoofing
  // _method=PATCH makes the router reject the request with a 405. Plain POST.
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
  saveError.value = ''
  try {
    await postsStore.updatePost(postId.value, buildFormData())

    // Upload cover image if one was selected
    if (coverFile.value) {
      await postsStore.uploadCover(Number(postId.value), coverFile.value)
    }

    router.push('/admin/noticias')
  } catch (err) {
    saveError.value = err.response?.data?.message || 'Error al guardar la publicación'
  } finally {
    loading.value = false
  }
}

async function handleDeleteImage(imageId) {
  if (deletingImageId.value === imageId) return
  deletingImageId.value = imageId
  try {
    await postsStore.deleteImage(Number(postId.value), imageId)
    images.value = images.value.filter((img) => img.id !== imageId)
  } catch (err) {
    saveError.value = err.response?.data?.message || 'Error al eliminar la imagen'
  } finally {
    deletingImageId.value = null
  }
}

onMounted(loadData)
</script>

<template>
  <div class="max-w-3xl mx-auto px-gutter py-12">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Editar Publicación</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-1">
          Modifica la información de la publicación.
        </p>
      </div>
      <BaseButton variant="outline" @click="router.push('/admin/noticias')">
        Cancelar
      </BaseButton>
    </div>

    <!-- Loading -->
    <div v-if="loading && !loaded" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <!-- Fetch error -->
    <div v-else-if="fetchError" class="text-center py-16">
      <p class="font-body-lg text-body-lg text-on-surface">{{ fetchError }}</p>
    </div>

    <!-- Edit form -->
    <div v-else-if="loaded">
      <div v-if="saveError" class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container">
        {{ saveError }}
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
              class="w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
            />
          </div>

          <!-- Body editor -->
          <div>
            <label class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Contenido</label>
            <div data-body-editor :data-post-id="postId">
              <Suspense>
                <TipTapEditor
                  v-model="form.body"
                  :postId="Number(postId)"
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
            <label class="block font-label-md text-label-md text-on-surface-variant mb-1.5">
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

          <!-- Existing body images -->
          <div v-if="images.length">
            <label class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Imágenes del contenido</label>
            <div class="flex flex-wrap gap-3">
              <div
                v-for="img in images"
                :key="img.id"
                data-existing-image
                class="relative w-20 h-20 rounded-lg overflow-hidden border border-blush-canvas/30 group"
              >
                <img :src="img.url" alt="" class="w-full h-full object-cover" />
                <button
                  type="button"
                  data-delete-image
                  @click="handleDeleteImage(img.id)"
                  :disabled="deletingImageId === img.id"
                  class="absolute top-0.5 right-0.5 w-6 h-6 rounded-full bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                  aria-label="Eliminar imagen"
                >
                  <span class="material-symbols-outlined text-[16px]" aria-hidden="true">close</span>
                </button>
              </div>
            </div>
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
              <span class="font-body-md text-body-md text-on-surface">Publicado</span>
            </label>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-3 pt-2">
            <BaseButton type="button" variant="outline" @click="router.push('/admin/noticias')">
              Cancelar
            </BaseButton>
            <BaseButton type="submit" variant="primary" :disabled="loading">
              {{ loading ? 'Guardando...' : 'Guardar cambios' }}
            </BaseButton>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
