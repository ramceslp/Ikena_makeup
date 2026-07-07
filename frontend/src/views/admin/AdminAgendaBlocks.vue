<script setup>
import { ref, computed, onMounted } from 'vue'
import { useBookingStore } from '../../stores/booking.js'
import BaseButton from '../../components/ui/BaseButton.vue'

const bookingStore = useBookingStore()

const blocks = computed(() => bookingStore.agendaBlocks)
const isLoading = computed(() => bookingStore.isLoading)

const DAY_LABELS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']

// ── Form state ──────────────────────────────────────────────────────────────

const showForm = ref(false)
const editingBlock = ref(null)
const formError = ref('')
const deleting = ref(null)
// 'weekly' | 'specific' — mutually exclusive by construction, so the payload
// built in handleSubmit can never violate the backend's XOR invariant (VAGA-002).
const recurrenceMode = ref('weekly')

const form = ref({
  day_of_week: 0,
  specific_date: '',
  open_time: '',
  close_time: '',
  concurrency_limit: 1,
  soft_threshold: '',
  is_blocked: false,
})

function dayLabel(block) {
  if (block.specific_date) return `Fecha específica: ${block.specific_date}`
  if (block.day_of_week !== null && block.day_of_week !== undefined) {
    return `Semanal — ${DAY_LABELS[block.day_of_week]}`
  }
  return '—'
}

function resetForm() {
  recurrenceMode.value = 'weekly'
  form.value = {
    day_of_week: 0,
    specific_date: '',
    open_time: '',
    close_time: '',
    concurrency_limit: 1,
    soft_threshold: '',
    is_blocked: false,
  }
  formError.value = ''
}

function openCreateForm() {
  editingBlock.value = null
  resetForm()
  showForm.value = true
}

function openEditForm(block) {
  editingBlock.value = block
  recurrenceMode.value = block.specific_date ? 'specific' : 'weekly'
  form.value = {
    day_of_week: block.day_of_week ?? 0,
    specific_date: block.specific_date ?? '',
    open_time: block.open_time,
    close_time: block.close_time,
    concurrency_limit: block.concurrency_limit ?? 1,
    soft_threshold: block.soft_threshold ?? '',
    is_blocked: block.is_blocked,
  }
  formError.value = ''
  showForm.value = true
}

function cancelForm() {
  showForm.value = false
  editingBlock.value = null
  formError.value = ''
}

async function handleDelete(block) {
  if (!confirm('¿Eliminar este bloque de agenda? Esta acción no se puede deshacer.')) return
  deleting.value = block.id
  try {
    await bookingStore.deleteAgendaBlock(block.id)
    await bookingStore.fetchAgendaBlocks()
  } catch (err) {
    alert(err.response?.data?.message || 'Error al eliminar el bloque de agenda')
  } finally {
    deleting.value = null
  }
}

async function handleSubmit() {
  formError.value = ''

  if (recurrenceMode.value === 'specific' && !form.value.specific_date) {
    formError.value = 'Selecciona una fecha específica.'
    return
  }

  const payload = {
    day_of_week: recurrenceMode.value === 'weekly' ? Number(form.value.day_of_week) : null,
    specific_date: recurrenceMode.value === 'specific' ? form.value.specific_date : null,
    open_time: form.value.open_time,
    close_time: form.value.close_time,
    concurrency_limit: Number(form.value.concurrency_limit),
    soft_threshold: form.value.soft_threshold === '' ? null : Number(form.value.soft_threshold),
    is_blocked: form.value.is_blocked,
  }

  try {
    if (editingBlock.value) {
      await bookingStore.updateAgendaBlock(editingBlock.value.id, payload)
    } else {
      await bookingStore.createAgendaBlock(payload)
    }
    showForm.value = false
    editingBlock.value = null
    await bookingStore.fetchAgendaBlocks()
  } catch (err) {
    const errors = err.response?.data?.errors
    if (errors) {
      formError.value = Object.values(errors).flat().join(' ')
    } else {
      formError.value = err.response?.data?.message || 'Error al guardar el bloque de agenda'
    }
  }
}

onMounted(async () => {
  await bookingStore.fetchAgendaBlocks()
})
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter py-12">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Agenda del local</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-1">
          Configura las ventanas de disponibilidad y el cupo simultáneo del local
        </p>
      </div>
      <BaseButton
        variant="primary"
        data-add-agenda-btn
        @click="openCreateForm"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
        Nuevo bloque
      </BaseButton>
    </div>

    <!-- Loading -->
    <div v-if="isLoading && !blocks.length" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <!-- Create/Edit Form -->
    <div
      v-if="showForm"
      data-agenda-form
      class="mb-8 bg-surface rounded-2xl border border-blush-canvas/20 p-6"
    >
      <h2 class="font-title-md text-title-md text-on-surface mb-4">
        {{ editingBlock ? 'Editar bloque de agenda' : 'Nuevo bloque de agenda' }}
      </h2>

      <!-- Error -->
      <div v-if="formError" class="mb-4 p-4 bg-error-container rounded-xl font-body-sm text-body-sm text-on-error-container">
        {{ formError }}
      </div>

      <!-- Recurrence mode toggle (XOR by construction — VAGA-002) -->
      <div class="flex items-center gap-6 mb-4">
        <label class="flex items-center gap-2 font-body-md text-body-md text-on-surface">
          <input
            type="radio"
            data-recurrence-weekly
            value="weekly"
            v-model="recurrenceMode"
            class="h-4 w-4 text-primary focus:ring-primary"
          />
          Recurrente semanal
        </label>
        <label class="flex items-center gap-2 font-body-md text-body-md text-on-surface">
          <input
            type="radio"
            data-recurrence-specific
            value="specific"
            v-model="recurrenceMode"
            class="h-4 w-4 text-primary focus:ring-primary"
          />
          Fecha específica
        </label>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Day of week -->
        <div v-if="recurrenceMode === 'weekly'" class="flex flex-col gap-1">
          <label class="font-label-md text-label-md text-on-surface">Día de la semana *</label>
          <select
            v-model="form.day_of_week"
            data-day-of-week-select
            class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none"
          >
            <option v-for="(day, idx) in DAY_LABELS" :key="idx" :value="idx">{{ day }}</option>
          </select>
        </div>

        <!-- Specific date -->
        <div v-else class="flex flex-col gap-1">
          <label class="font-label-md text-label-md text-on-surface">Fecha específica *</label>
          <input
            v-model="form.specific_date"
            type="date"
            data-specific-date-input
            class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none"
          />
        </div>

        <!-- Open time -->
        <div class="flex flex-col gap-1">
          <label class="font-label-md text-label-md text-on-surface">Hora de apertura *</label>
          <input
            v-model="form.open_time"
            type="time"
            data-open-time-input
            required
            class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none"
          />
        </div>

        <!-- Close time -->
        <div class="flex flex-col gap-1">
          <label class="font-label-md text-label-md text-on-surface">Hora de cierre *</label>
          <input
            v-model="form.close_time"
            type="time"
            data-close-time-input
            required
            class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none"
          />
        </div>

        <!-- Concurrency limit -->
        <div class="flex flex-col gap-1">
          <label class="font-label-md text-label-md text-on-surface">Cupo simultáneo (cupo máximo)</label>
          <input
            v-model.number="form.concurrency_limit"
            type="number"
            min="1"
            data-concurrency-limit-input
            class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none"
          />
        </div>

        <!-- Soft threshold -->
        <div class="flex flex-col gap-1">
          <label class="font-label-md text-label-md text-on-surface">Umbral de alta demanda (opcional)</label>
          <input
            v-model="form.soft_threshold"
            type="number"
            min="1"
            data-soft-threshold-input
            class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none"
          />
        </div>

        <!-- Is blocked -->
        <div class="flex items-center gap-3 sm:col-span-2">
          <input
            id="is_blocked"
            v-model="form.is_blocked"
            type="checkbox"
            data-is-blocked-checkbox
            class="h-4 w-4 rounded border-blush-canvas/30 text-primary focus:ring-primary"
          />
          <label for="is_blocked" class="font-body-md text-body-md text-on-surface">
            Bloquear este bloque (no disponible para reservas)
          </label>
        </div>
      </div>

      <div class="flex gap-3 mt-6">
        <BaseButton variant="primary" data-agenda-form-submit @click="handleSubmit">
          {{ editingBlock ? 'Guardar cambios' : 'Crear bloque' }}
        </BaseButton>
        <BaseButton variant="outline" @click="cancelForm">
          Cancelar
        </BaseButton>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!blocks.length && !isLoading" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-blush-canvas mb-4" aria-hidden="true">calendar_month</span>
      <p class="font-body-lg text-body-lg text-on-surface-variant">No hay bloques de agenda configurados</p>
      <BaseButton variant="primary" class="mt-6" data-add-agenda-btn @click="openCreateForm">
        Crear primer bloque
      </BaseButton>
    </div>

    <!-- Blocks table -->
    <div v-else-if="blocks.length" class="bg-surface rounded-2xl border border-blush-canvas/20 overflow-hidden">
      <table class="w-full">
        <thead class="border-b border-blush-canvas/20 bg-surface-container-low">
          <tr>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">Recurrencia</th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">Horario</th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant hidden sm:table-cell">Cupo</th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">Estado</th>
            <th class="text-right px-6 py-4 font-label-md text-label-md text-on-surface-variant">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-blush-canvas/10">
          <tr
            v-for="block in blocks"
            :key="block.id"
            data-agenda-row
            class="hover:bg-surface-container-low transition-colors"
          >
            <td class="px-6 py-4 font-body-md text-body-md text-on-surface">
              {{ dayLabel(block) }}
            </td>
            <td class="px-6 py-4 font-body-md text-body-md text-on-surface">
              {{ block.open_time }} – {{ block.close_time }}
            </td>
            <td class="px-6 py-4 font-body-md text-body-md text-on-surface hidden sm:table-cell">
              {{ block.concurrency_limit }}<span v-if="block.soft_threshold"> (aviso desde {{ block.soft_threshold }})</span>
            </td>
            <td class="px-6 py-4">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded font-label-sm text-label-sm"
                :class="block.is_blocked
                  ? 'bg-surface-container text-on-surface-variant'
                  : 'bg-primary/10 text-primary'"
              >
                {{ block.is_blocked ? 'Bloqueado' : 'Activo' }}
              </span>
            </td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <button
                  type="button"
                  data-edit-agenda-btn
                  @click="openEditForm(block)"
                  class="p-2 rounded-lg hover:bg-surface-container transition-colors text-on-surface-variant hover:text-primary"
                  aria-label="Editar bloque de agenda"
                >
                  <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                </button>
                <button
                  type="button"
                  data-delete-agenda-btn
                  :disabled="deleting === block.id"
                  @click="handleDelete(block)"
                  class="p-2 rounded-lg hover:bg-error-container transition-colors text-on-surface-variant hover:text-error"
                  aria-label="Eliminar bloque de agenda"
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
