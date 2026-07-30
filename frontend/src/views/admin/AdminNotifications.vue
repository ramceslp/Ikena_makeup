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

// `destination` is a key from the server's catalogue, never a hand-typed path.
// It used to be a free-text `route` field, and that shipped a real bug: a
// course URL copied from THIS panel (/courses/{slug}) is not the app's route
// for the same screen (/cursos/{slug}), and vue-router resolves an unmatched
// path without complaining — so the notification arrived, the history said
// "sent", and tapping it opened a blank screen. A picker cannot express a
// destination that does not exist.
const form = ref({ title: '', body: '', destination: '', slug: '' })
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
  () =>
    form.value.title.trim() !== '' &&
    form.value.body.trim() !== '' &&
    !destinationIncomplete.value &&
    !sending.value,
)

const pushDisabled = computed(() => stats.value !== null && stats.value.push_enabled === false)

const destinations = computed(() => store.destinations)

const selectedDestination = computed(
  () => destinations.value.find((d) => d.key === form.value.destination) ?? null,
)

const needsSlug = computed(() => selectedDestination.value?.requires_slug === true)

/**
 * The exact path the notification will open, shown back to the admin before
 * they send. The server builds the real one — this is a preview, not the value
 * submitted — but seeing "/cursos/mi-curso" removes the last place a mistake
 * can hide, since the whole failure mode here is invisible until a user taps.
 */
const routePreview = computed(() => {
  if (selectedDestination.value === null) return ''

  const slug = form.value.slug.trim()

  if (!needsSlug.value) return selectedDestination.value.pattern
  if (slug === '') return ''

  return selectedDestination.value.pattern.replace('{slug}', slug)
})

// A destination that needs a slug and hasn't got one is the one incomplete
// state the form can be in — the server rejects it, so block it here first.
const destinationIncomplete = computed(() => needsSlug.value && form.value.slug.trim() === '')

function onDestinationChange() {
  // A stale slug left over from a previous selection would silently ride along
  // into a destination that does not take one.
  form.value.slug = ''
}

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
      destination: form.value.destination,
      slug: form.value.slug.trim(),
    })

    successMessage.value =
      created.status === 'skipped'
        ? 'La notificación quedó registrada, pero NO se envió: las notificaciones push están desactivadas en el servidor.'
        : 'Notificación encolada. El historial mostrará los envíos cuando FCM responda.'

    form.value = { title: '', body: '', destination: '', slug: '' }
    await loadLogs(1)
  } catch {
    // sendError is already set by the store and rendered below.
  }
}

onMounted(async () => {
  await Promise.all([loadLogs(), store.fetchStats(), store.fetchDestinations()])
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
          <label for="notif-destination" class="block font-label-md text-label-md text-on-surface-variant mb-1">
            Destino (opcional)
          </label>
          <select
            id="notif-destination"
            v-model="form.destination"
            data-destination-select
            class="w-full px-4 py-3 rounded-xl border border-blush-canvas/40 font-body-md text-body-md focus:outline-none focus:border-primary"
            @change="onDestinationChange"
          >
            <option value="">Ninguno — abre la app en el inicio</option>
            <option v-for="d in destinations" :key="d.key" :value="d.key">
              {{ d.label }}
            </option>
          </select>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
            Pantalla de la app que se abre al tocar la notificación.
          </p>
        </div>

        <!-- Only the destinations that point at one specific item need this. -->
        <div v-if="needsSlug">
          <label for="notif-slug" class="block font-label-md text-label-md text-on-surface-variant mb-1">
            ¿Cuál? — identificador (slug)
          </label>
          <input
            id="notif-slug"
            v-model="form.slug"
            data-slug-input
            type="text"
            placeholder="maquillaje-de-novias"
            class="w-full px-4 py-3 rounded-xl border border-blush-canvas/40 font-body-md text-body-md focus:outline-none focus:border-primary"
          />
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
            El identificador que aparece al final de la dirección del contenido. Si no existe o no
            está publicado, el envío se rechaza antes de salir.
          </p>
        </div>

        <!-- The whole failure mode here is invisible until someone taps the
             notification on their phone, so the resolved path is shown back
             before sending rather than only stored. -->
        <p
          v-if="routePreview"
          data-route-preview
          class="font-body-sm text-body-sm text-on-surface-variant"
        >
          Al tocarla, la app abre
          <code class="font-mono text-primary">{{ routePreview }}</code>
        </p>

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
