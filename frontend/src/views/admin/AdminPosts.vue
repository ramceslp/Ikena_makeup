<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { usePostsStore } from '../../stores/posts.js'
import BaseButton from '../../components/ui/BaseButton.vue'

const router = useRouter()
const postsStore = usePostsStore()

const posts = computed(() => postsStore.posts)
const loading = computed(() => postsStore.loading)
const error = computed(() => postsStore.error)

const deleting = ref(null)
const deleteError = ref('')

const typeLabel = {
  noticia: 'Noticia',
  nuevo_curso: 'Nuevo Curso',
  oferta: 'Oferta',
  evento: 'Evento',
  lanzamiento: 'Lanzamiento',
  certificacion: 'Certificación',
  contenido: 'Contenido',
}

async function loadPosts() {
  await postsStore.fetchAdminPosts()
}

async function handleDelete(id) {
  if (!window.confirm('¿Eliminar esta publicación? Esta acción no se puede deshacer.')) return
  deleting.value = id
  deleteError.value = ''
  try {
    await postsStore.deletePost(id)
    await loadPosts()
  } catch (err) {
    deleteError.value = err.response?.data?.message || 'Error al eliminar la publicación'
  } finally {
    deleting.value = null
  }
}

onMounted(loadPosts)
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter py-12">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Gestión de Noticias</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-1">
          Administra publicaciones, noticias, cursos y eventos (publicados y borradores).
        </p>
      </div>
      <BaseButton
        data-new-post-btn
        variant="primary"
        @click="router.push('/admin/posts/new')"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
        Nueva publicación
      </BaseButton>
    </div>

    <!-- Errors -->
    <div v-if="error" class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container">
      {{ error }}
    </div>
    <div v-if="deleteError" class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container">
      {{ deleteError }}
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <!-- Empty state -->
    <div v-else-if="!posts.length" data-empty-state class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-blush-canvas mb-4" aria-hidden="true">newspaper</span>
      <p class="font-body-lg text-body-lg text-on-surface-variant">No hay publicaciones aún</p>
      <BaseButton variant="primary" class="mt-6" @click="router.push('/admin/posts/new')">
        Crear primera publicación
      </BaseButton>
    </div>

    <!-- Posts table -->
    <div v-else class="bg-surface rounded-2xl border border-blush-canvas/20 overflow-hidden">
      <table class="w-full">
        <thead class="border-b border-blush-canvas/20 bg-surface-container-low">
          <tr>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">Publicación</th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant hidden md:table-cell">Tipo</th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">Estado</th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant hidden lg:table-cell">Destacado</th>
            <th class="text-right px-6 py-4 font-label-md text-label-md text-on-surface-variant">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-blush-canvas/10">
          <tr
            v-for="post in posts"
            :key="post.id"
            data-post-row
            class="hover:bg-surface-container-low transition-colors"
          >
            <!-- Title -->
            <td class="px-6 py-4">
              <span class="font-body-md text-body-md text-on-surface line-clamp-1">{{ post.title }}</span>
            </td>

            <!-- Type -->
            <td class="px-6 py-4 hidden md:table-cell">
              <span class="font-body-sm text-body-sm text-on-surface-variant">
                {{ typeLabel[post.type] ?? post.type }}
              </span>
            </td>

            <!-- Published state -->
            <td class="px-6 py-4">
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                :class="post.is_published
                  ? 'bg-green-100 text-green-700'
                  : 'bg-gray-100 text-gray-600'"
              >
                {{ post.is_published ? 'Publicado' : 'Borrador' }}
              </span>
            </td>

            <!-- Featured -->
            <td class="px-6 py-4 hidden lg:table-cell">
              <span
                v-if="post.is_featured"
                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700"
              >
                Destacado
              </span>
              <span v-else class="text-on-surface-variant font-body-sm text-body-sm">—</span>
            </td>

            <!-- Actions -->
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <button
                  type="button"
                  data-edit-btn
                  @click="router.push(`/admin/posts/${post.id}/edit`)"
                  class="p-2 rounded-lg hover:bg-surface-container transition-colors text-on-surface-variant hover:text-primary"
                  aria-label="Editar publicación"
                >
                  <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                </button>
                <button
                  type="button"
                  data-delete-btn
                  @click="handleDelete(post.id)"
                  :disabled="deleting === post.id"
                  class="p-2 rounded-lg hover:bg-error-container transition-colors text-on-surface-variant hover:text-error"
                  aria-label="Eliminar publicación"
                >
                  <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
