<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useBookingStore } from '../../stores/booking.js'
import BaseButton from '../../components/ui/BaseButton.vue'

const route = useRoute()
const bookingStore = useBookingStore()

const serviceId = computed(() => Number(route.params.id))
const slots = computed(() => bookingStore.slots)
const isLoading = computed(() => bookingStore.isLoading)

// Create/edit form state
const showForm = ref(false)
const editingSlot = ref(null)
const formError = ref('')
const deleting = ref(null)

const form = ref({
  day_of_week: '',
  specific_date: '',
  start_time: '',
  capacity: 1,
  is_blocked: false,
})

const DAY_LABELS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']

function dayLabel(slot) {
  if (slot.specific_date) return `Fecha específica: ${slot.specific_date}`
  if (slot.day_of_week !== null && slot.day_of_week !== undefined) {
    return `Semanal — ${DAY_LABELS[slot.day_of_week]}`
  }
  return '—'
}

function openCreateForm() {
  editingSlot.value = null
  form.value = { day_of_week: '', specific_date: '', start_time: '', capacity: 1, is_blocked: false }
  formError.value = ''
  showForm.value = true
}

function openEditForm(slot) {
  editingSlot.value = slot
  form.value = {
    day_of_week: slot.day_of_week ?? '',
    specific_date: slot.specific_date ?? '',
    start_time: slot.start_time,
    capacity: slot.capacity,
    is_blocked: slot.is_blocked,
  }
  formError.value = ''
  showForm.value = true
}

function cancelForm() {
  showForm.value = false
  editingSlot.value = null
  formError.value = ''
}

async function handleDelete(slot) {
  if (!confirm('¿Eliminar este horario? Esta acción no se puede deshacer.')) return
  deleting.value = slot.id
  try {
    await bookingStore.deleteSlot(serviceId.value, slot.id)
    await bookingStore.fetchSlots(serviceId.value)
  } catch (err) {
    alert(err.response?.data?.message || 'Error al eliminar el horario')
  } finally {
    deleting.value = null
  }
}

async function handleSubmit() {
  formError.value = ''
  // Build payload — strip empty strings to null
  const payload = {
    start_time: form.value.start_time,
    capacity: Number(form.value.capacity),
    is_blocked: form.value.is_blocked,
    day_of_week: form.value.day_of_week !== '' ? Number(form.value.day_of_week) : null,
    specific_date: form.value.specific_date || null,
  }

  try {
    if (editingSlot.value) {
      await bookingStore.updateSlot(serviceId.value, editingSlot.value.id, payload)
    } else {
      await bookingStore.createSlot(serviceId.value, payload)
    }
    showForm.value = false
    editingSlot.value = null
    await bookingStore.fetchSlots(serviceId.value)
  } catch (err) {
    const errors = err.response?.data?.errors
    if (errors) {
      formError.value = Object.values(errors).flat().join(' ')
    } else {
      formError.value = err.response?.data?.message || 'Error al guardar el horario'
    }
  }
}

onMounted(async () => {
  await bookingStore.fetchSlots(serviceId.value)
})
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter py-12">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Gestión de Horarios</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-1">
          Configura los slots de disponibilidad para este servicio
        </p>
      </div>
      <BaseButton
        variant="primary"
        data-add-slot-btn
        @click="openCreateForm"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
        Nuevo horario
      </BaseButton>
    </div>

    <!-- Loading -->
    <div v-if="isLoading && !slots.length" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <!-- Create/Edit Form -->
    <div
      v-if="showForm"
      data-slot-form
      class="mb-8 bg-surface rounded-2xl border border-blush-canvas/20 p-6"
    >
      <h2 class="font-title-md text-title-md text-on-surface mb-4">
        {{ editingSlot ? 'Editar horario' : 'Nuevo horario' }}
      </h2>

      <!-- Error -->
      <div v-if="formError" class="mb-4 p-4 bg-error-container rounded-xl font-body-sm text-body-sm text-on-error-container">
        {{ formError }}
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Day of week -->
        <div class="flex flex-col gap-1">
          <label class="font-label-md text-label-md text-on-surface">Día de la semana (recurrente)</label>
          <select
            v-model="form.day_of_week"
            class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none"
          >
            <option value="">— Sin día recurrente —</option>
            <option v-for="(day, idx) in DAY_LABELS" :key="idx" :value="idx">{{ day }}</option>
          </select>
        </div>

        <!-- Specific date -->
        <div class="flex flex-col gap-1">
          <label class="font-label-md text-label-md text-on-surface">Fecha específica (una vez)</label>
          <input
            v-model="form.specific_date"
            type="date"
            class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none"
          />
        </div>

        <!-- Start time -->
        <div class="flex flex-col gap-1">
          <label class="font-label-md text-label-md text-on-surface">Hora de inicio *</label>
          <input
            v-model="form.start_time"
            type="time"
            required
            class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none"
          />
        </div>

        <!-- Capacity -->
        <div class="flex flex-col gap-1">
          <label class="font-label-md text-label-md text-on-surface">Capacidad</label>
          <input
            v-model.number="form.capacity"
            type="number"
            min="1"
            max="99"
            class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none"
          />
        </div>

        <!-- Is blocked -->
        <div class="flex items-center gap-3 sm:col-span-2">
          <input
            id="is_blocked"
            v-model="form.is_blocked"
            type="checkbox"
            class="h-4 w-4 rounded border-blush-canvas/30 text-primary focus:ring-primary"
          />
          <label for="is_blocked" class="font-body-md text-body-md text-on-surface">
            Bloquear este horario (no disponible para reservas)
          </label>
        </div>
      </div>

      <div class="flex gap-3 mt-6">
        <BaseButton variant="primary" @click="handleSubmit">
          {{ editingSlot ? 'Guardar cambios' : 'Crear horario' }}
        </BaseButton>
        <BaseButton variant="outline" @click="cancelForm">
          Cancelar
        </BaseButton>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!slots.length && !isLoading" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-blush-canvas mb-4" aria-hidden="true">calendar_month</span>
      <p class="font-body-lg text-body-lg text-on-surface-variant">No hay horarios configurados</p>
      <BaseButton variant="primary" class="mt-6" data-add-slot-btn @click="openCreateForm">
        Crear primer horario
      </BaseButton>
    </div>

    <!-- Slots table -->
    <div v-else-if="slots.length" class="bg-surface rounded-2xl border border-blush-canvas/20 overflow-hidden">
      <table class="w-full">
        <thead class="border-b border-blush-canvas/20 bg-surface-container-low">
          <tr>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">Recurrencia</th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">Hora</th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant hidden sm:table-cell">Capacidad</th>
            <th class="text-left px-6 py-4 font-label-md text-label-md text-on-surface-variant">Estado</th>
            <th class="text-right px-6 py-4 font-label-md text-label-md text-on-surface-variant">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-blush-canvas/10">
          <tr
            v-for="slot in slots"
            :key="slot.id"
            data-slot-row
            class="hover:bg-surface-container-low transition-colors"
          >
            <td class="px-6 py-4 font-body-md text-body-md text-on-surface">
              {{ dayLabel(slot) }}
            </td>
            <td class="px-6 py-4 font-body-md text-body-md text-on-surface">
              {{ slot.start_time }}
            </td>
            <td class="px-6 py-4 font-body-md text-body-md text-on-surface hidden sm:table-cell">
              {{ slot.capacity }}
            </td>
            <td class="px-6 py-4">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded font-label-sm text-label-sm"
                :class="slot.is_blocked
                  ? 'bg-surface-container text-on-surface-variant'
                  : 'bg-primary/10 text-primary'"
              >
                {{ slot.is_blocked ? 'Bloqueado' : 'Activo' }}
              </span>
            </td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <button
                  type="button"
                  @click="openEditForm(slot)"
                  class="p-2 rounded-lg hover:bg-surface-container transition-colors text-on-surface-variant hover:text-primary"
                  aria-label="Editar horario"
                >
                  <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                </button>
                <button
                  type="button"
                  data-delete-slot-btn
                  :disabled="deleting === slot.id"
                  @click="handleDelete(slot)"
                  class="p-2 rounded-lg hover:bg-error-container transition-colors text-on-surface-variant hover:text-error"
                  aria-label="Eliminar horario"
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
