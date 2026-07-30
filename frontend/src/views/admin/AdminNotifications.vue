<script setup>
import { ref, computed, onMounted } from 'vue'
import { useNotificationsStore } from '../../stores/notifications.js'
import BaseButton from '../../components/ui/BaseButton.vue'

// Admin push-notification centre (push-notifications Slice 4).
// Compose form on top, unified send history below — automatic triggers
// (a news post published, a course made available) and custom sends in one
// timeline, which is what lets an admin answer "did the course notification
// actually go out?" without leaving the panel.

const store = useNotificationsStore()

const logs = computed(() => store.logs)
const meta = computed(() => store.meta)
const stats = computed(() => store.stats)
const loading = computed(() => store.loading)
const sending = computed(() => store.sending)
const error = computed(() => store.error)
const sendError = computed(() => store.sendError)

const form = ref({ title: '', body: '', route: '' })
const successMessage = ref('')
const typeFilter = ref('')
const currentPage = ref(1)

const TITLE_MAX = 100
const BODY_MAX = 500

const typeLabel = {
  'post.published': 'Noticia',
  'course.published': 'Curso',
  custom: 'Personalizada',
}

const statusLabel = {
  pending: 'En cola',
  sent: 'Enviada',
  failed: 'Falló',
  skipped: 'No enviada',
}

// 'skipped' is styled as a warning, not a success: it means Firebase is not
// configured on the server and NOBODY was reached. Showing it in the same
// colour as a delivered send is exactly the confusion the history exists to
// prevent.
const statusClass = {
  pending: 'bg-surface-container-low text-on-surface-variant',
  sent: 'bg-primary/10 text-primary',
  failed: 'bg-error-container text-on-error-container',
  skipped: 'bg-error-container/50 text-on-error-container',
}

const canSubmit = computed(
  () => form.value.title.trim() !== '' && form.value.body.trim() !== '' && !sending.value,
)

const pushDisabled = computed(() => stats.value !== null && stats.value.push_enabled === false)

function formatDate(iso) {
  if (!iso) return '—'
  // The API already returns ISO-8601 in the venue timezone (America/Guayaquil),
  // so this only formats — it must not re-interpret the offset.
  return new Date(iso).toLocaleString('es-EC', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function loadLogs(page = 1) {
  currentPage.value = page
  await store.fetchLogs({ type: typeFilter.value, page })
}

async function applyFilter() {
  await loadLogs(1)
}

async function handleSubmit() {
  successMessage.value = ''
  try {
    const created = await store.send({
      title: form.value.title.trim(),
      body: form.value.body.trim(),
      route: form.value.route.trim(),
    })

    successMessage.value =
      created.status === 'skipped'
        ? 'La notificación quedó registrada, pero NO se envió: las notificaciones push están desactivadas en el servidor.'
        : 'Notificación encolada. El historial mostrará los envíos cuando FCM responda.'

    form.value = { title: '', body: '', route: '' }
    await loadLogs(1)
  } catch {
    // sendError is already set by the store and rendered below.
  }
}

onMounted(async () => {
  await Promise.all([loadLogs(), store.fetchStats()])
})
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter py-12">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Notificaciones</h1>
      <p class="font-body-md text-body-md text-on-surface-variant mt-1">
        Enviá notificaciones a la app móvil y revisá todo lo que se envió, incluyendo las
        automáticas de noticias y cursos nuevos.
      </p>
    </div>

    <!-- Push disabled warning -->
    <div
      v-if="pushDisabled"
      data-push-disabled-warning
      class="mb-6 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container"
    >
      Las notificaciones push están desactivadas en el servidor (Firebase no está configurado).
      Todo lo que envíes va a quedar registrado en el historial, pero no va a llegar a ningún
      dispositivo.
    </div>

    <!-- Compose -->
    <section class="mb-10 bg-surface rounded-2xl border border-blush-canvas/20 p-6">
      <h2 class="font-title-lg text-title-lg text-deep-marsala mb-1">Nueva notificación</h2>
      <p v-if="stats" data-device-count class="font-body-sm text-body-sm text-on-surface-variant mb-6">
        Se enviará a {{ stats.device_count }}
        {{ stats.device_count === 1 ? 'dispositivo registrado' : 'dispositivos registrados' }}.
      </p>

      <div
        v-if="sendError"
        data-send-error
        class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container"
      >
        {{ sendError }}
      </div>
      <div
        v-if="successMessage"
        data-send-success
        class="mb-4 p-4 bg-primary/10 rounded-xl font-body-md text-body-md text-primary"
      >
        {{ successMessage }}
      </div>

      <form class="space-y-4" @submit.prevent="handleSubmit">
        <div>
          <label for="notif-title" class="block font-label-md text-label-md text-on-surface-variant mb-1">
            Título
          </label>
          <input
            id="notif-title"
            v-model="form.title"
            data-title-input
            type="text"
            :maxlength="TITLE_MAX"
            required
            class="w-full px-4 py-3 rounded-xl border border-blush-canvas/40 font-body-md text-body-md focus:outline-none focus:border-primary"
          />
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
            {{ form.title.length }}/{{ TITLE_MAX }}
          </p>
        </div>

        <div>
          <label for="notif-body" class="block font-label-md text-label-md text-on-surface-variant mb-1">
            Mensaje
          </label>
          <textarea
            id="notif-body"
            v-model="form.body"
            data-body-input
            rows="3"
            :maxlength="BODY_MAX"
            required
            class="w-full px-4 py-3 rounded-xl border border-blush-canvas/40 font-body-md text-body-md focus:outline-none focus:border-primary"
          />
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
            {{ form.body.length }}/{{ BODY_MAX }}
          </p>
        </div>

        <div>
          <label for="notif-route" class="block font-label-md text-label-md text-on-surface-variant mb-1">
            Destino (opcional)
          </label>
          <input
            id="notif-route"
            v-model="form.route"
            data-route-input
            type="text"
            placeholder="/cursos/maquillaje-de-novias"
            class="w-full px-4 py-3 rounded-xl border border-blush-canvas/40 font-body-md text-body-md focus:outline-none focus:border-primary"
          />
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
            Ruta interna de la app a la que se abre al tocar la notificación. Tiene que empezar
            con “/”. Si lo dejás vacío, la notificación abre la app en el inicio.
          </p>
        </div>

        <BaseButton
          data-send-btn
          type="submit"
          variant="primary"
          :loading="sending"
          :disabled="!canSubmit"
        >
          Enviar notificación
        </BaseButton>
      </form>
    </section>

    <!-- History -->
    <section>
      <div class="flex items-center justify-between mb-4 gap-4 flex-wrap">
        <h2 class="font-title-lg text-title-lg text-deep-marsala">Historial</h2>
        <select
          v-model="typeFilter"
          data-type-filter
          class="px-4 py-2 rounded-xl border border-blush-canvas/40 font-body-md text-body-md"
          @change="applyFilter"
        >
          <option value="">Todas</option>
          <option value="custom">Personalizadas</option>
          <option value="post.published">Noticias</option>
          <option value="course.published">Cursos</option>
        </select>
      </div>

      <div
        v-if="error"
        data-history-error
        class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container"
      >
        {{ error }}
      </div>

      <div v-if="loading" class="text-center py-16">
        <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">
          refresh
        </span>
      </div>

      <div v-else-if="!logs.length" data-empty-state class="text-center py-16">
        <span class="material-symbols-outlined text-5xl text-blush-canvas mb-4" aria-hidden="true">
          notifications_off
        </span>
        <p class="font-body-lg text-body-lg text-on-surface-variant">
          Todavía no se envió ninguna notificación
        </p>
      </div>

      <div v-else class="bg-surface rounded-2xl border border-blush-canvas/20 overflow-x-auto">
        <table class="w-full">
          <thead class="border-b border-blush-canvas/20 bg-surface-container-low">
            <tr>
              <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">Fecha</th>
              <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant hidden md:table-cell">Tipo</th>
              <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">Notificación</th>
              <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">Estado</th>
              <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant hidden lg:table-cell">Entregas</th>
              <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant hidden lg:table-cell">Enviada por</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-blush-canvas/10">
            <tr v-for="log in logs" :key="log.id" data-log-row class="hover:bg-surface-container-low transition-colors">
              <td class="px-6 py-4 font-body-sm text-body-sm text-on-surface-variant whitespace-nowrap">
                {{ formatDate(log.sent_at || log.created_at) }}
              </td>
              <td class="px-6 py-4 font-body-md text-body-md hidden md:table-cell">
                {{ typeLabel[log.type] || log.type }}
              </td>
              <td class="px-6 py-4">
                <p class="font-label-lg text-label-lg text-on-surface">{{ log.title }}</p>
                <p class="font-body-sm text-body-sm text-on-surface-variant line-clamp-2">{{ log.body }}</p>
                <p v-if="log.route" class="font-body-sm text-body-sm text-primary mt-1">{{ log.route }}</p>
              </td>
              <td class="px-6 py-4">
                <span
                  data-status-badge
                  class="inline-block px-3 py-1 rounded-full font-label-sm text-label-sm whitespace-nowrap"
                  :class="statusClass[log.status]"
                >
                  {{ statusLabel[log.status] || log.status }}
                </span>
              </td>
              <td class="px-6 py-4 font-body-sm text-body-sm text-on-surface-variant hidden lg:table-cell whitespace-nowrap">
                <span v-if="log.status === 'sent'">
                  {{ log.success_count }} / {{ log.recipients_count }}
                  <span v-if="log.failure_count" class="text-on-error-container">
                    ({{ log.failure_count }} fallaron)
                  </span>
                </span>
                <span v-else>—</span>
              </td>
              <td class="px-6 py-4 font-body-sm text-body-sm text-on-surface-variant hidden lg:table-cell">
                {{ log.sent_by ? log.sent_by.name : 'Sistema' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-4 mt-6">
        <BaseButton
          data-prev-page
          variant="secondary"
          :disabled="currentPage <= 1"
          @click="loadLogs(currentPage - 1)"
        >
          Anterior
        </BaseButton>
        <span class="font-body-md text-body-md text-on-surface-variant">
          Página {{ meta.current_page }} de {{ meta.last_page }}
        </span>
        <BaseButton
          data-next-page
          variant="secondary"
          :disabled="currentPage >= meta.last_page"
          @click="loadLogs(currentPage + 1)"
        >
          Siguiente
        </BaseButton>
      </div>
    </section>
  </div>
</template>
