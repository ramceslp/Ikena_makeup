<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useBookingStore } from '../../stores/booking.js'
import { formatCurrency } from '../../utils/money.js'

const bookingStore = useBookingStore()

const appointments = computed(() => bookingStore.appointments)
const isLoading = computed(() => bookingStore.isLoading)

// Filters
const statusFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')

const actionError = ref('')
const markingPaid = ref(null)
const cancelling = ref(null)

function appointmentStatusConfig(status) {
  const map = {
    pending:   { label: 'Pendiente',   cls: 'bg-surface-container text-on-surface-variant' },
    confirmed: { label: 'Confirmado',  cls: 'bg-primary/10 text-primary' },
    paid:      { label: 'Pagado',      cls: 'bg-primary/10 text-primary' },
    cancelled: { label: 'Cancelado',   cls: 'bg-error-container text-on-error-container' },
  }
  return map[status] ?? { label: status, cls: 'bg-surface-container text-on-surface-variant' }
}

function buildFilters() {
  const f = {}
  if (statusFilter.value) f.status = statusFilter.value
  if (dateFrom.value) f.date_from = dateFrom.value
  if (dateTo.value) f.date_to = dateTo.value
  return f
}

async function loadAppointments() {
  actionError.value = ''
  await bookingStore.fetchAppointments(buildFilters())
}

async function handleMarkPaid(appt) {
  markingPaid.value = appt.id
  actionError.value = ''
  try {
    await bookingStore.markAppointmentPaid(appt.id)
    await loadAppointments()
  } catch (err) {
    actionError.value = err.response?.data?.message || 'Error al marcar como pagado'
  } finally {
    markingPaid.value = null
  }
}

async function handleCancel(appt) {
  if (!confirm('¿Cancelar esta cita? El cliente deberá reservar nuevamente.')) return
  cancelling.value = appt.id
  actionError.value = ''
  try {
    await bookingStore.cancelAppointment(appt.id)
    await loadAppointments()
  } catch (err) {
    actionError.value = err.response?.data?.message || 'Error al cancelar la cita'
  } finally {
    cancelling.value = null
  }
}

// Watch filters and re-fetch
watch(statusFilter, loadAppointments)
watch(dateFrom, loadAppointments)
watch(dateTo, loadAppointments)

onMounted(loadAppointments)
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter py-12">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Gestión de Citas</h1>
      <p class="font-body-md text-body-md text-on-surface-variant mt-1">
        Administra todas las citas y reservas del sistema
      </p>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-4 mb-6 bg-surface rounded-2xl border border-blush-canvas/20 p-4">
      <div class="flex flex-col gap-1">
        <label class="font-label-sm text-label-sm text-on-surface-variant">Estado</label>
        <select
          v-model="statusFilter"
          data-status-filter
          class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none min-w-[140px]"
        >
          <option value="">Todos</option>
          <option value="pending">Pendiente</option>
          <option value="confirmed">Confirmado</option>
          <option value="paid">Pagado</option>
          <option value="cancelled">Cancelado</option>
        </select>
      </div>

      <div class="flex flex-col gap-1">
        <label class="font-label-sm text-label-sm text-on-surface-variant">Desde</label>
        <input
          v-model="dateFrom"
          type="date"
          class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="font-label-sm text-label-sm text-on-surface-variant">Hasta</label>
        <input
          v-model="dateTo"
          type="date"
          class="rounded-xl border border-blush-canvas/30 bg-surface px-4 py-2 font-body-sm text-body-sm text-on-surface focus:border-primary focus:outline-none"
        />
      </div>
    </div>

    <!-- Action error -->
    <div
      v-if="actionError"
      class="mb-4 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container"
    >
      {{ actionError }}
    </div>

    <!-- Loading -->
    <div v-if="isLoading && !appointments.length" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <!-- Empty state -->
    <div v-else-if="!appointments.length" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-blush-canvas mb-4" aria-hidden="true">event_busy</span>
      <p class="font-body-lg text-body-lg text-on-surface-variant">No hay citas registradas</p>
    </div>

    <!-- Appointments table -->
    <div v-else class="bg-surface rounded-2xl border border-blush-canvas/20 overflow-hidden overflow-x-auto">
      <table class="w-full min-w-[700px]">
        <thead class="border-b border-blush-canvas/20 bg-surface-container-low">
          <tr>
            <th class="text-left px-4 py-4 font-label-md text-label-md text-on-surface-variant">Servicio / Cliente</th>
            <th class="text-left px-4 py-4 font-label-md text-label-md text-on-surface-variant">Fecha y Hora</th>
            <th class="text-left px-4 py-4 font-label-md text-label-md text-on-surface-variant">WhatsApp</th>
            <th class="text-left px-4 py-4 font-label-md text-label-md text-on-surface-variant">Depósito</th>
            <th class="text-left px-4 py-4 font-label-md text-label-md text-on-surface-variant">Estado</th>
            <th class="text-right px-4 py-4 font-label-md text-label-md text-on-surface-variant">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-blush-canvas/10">
          <tr
            v-for="appt in appointments"
            :key="appt.id"
            data-appointment-row
            class="hover:bg-surface-container-low transition-colors"
          >
            <!-- Service + user -->
            <td class="px-4 py-4">
              <p class="font-body-md text-body-md text-on-surface">{{ appt.service?.title }}</p>
              <p class="font-label-sm text-label-sm text-on-surface-variant mt-0.5">{{ appt.user?.name }}</p>
              <p class="font-label-sm text-label-sm text-outline">{{ appt.user?.email }}</p>
            </td>

            <!-- Date + time -->
            <td class="px-4 py-4">
              <p class="font-body-md text-body-md text-on-surface">{{ appt.scheduled_date }}</p>
              <p class="font-label-sm text-label-sm text-on-surface-variant">{{ appt.scheduled_time }}</p>
            </td>

            <!-- WhatsApp -->
            <td class="px-4 py-4 font-body-sm text-body-sm text-on-surface">
              {{ appt.whatsapp }}
            </td>

            <!-- Deposit -->
            <td class="px-4 py-4 font-body-md text-body-md text-on-surface">
              {{ formatCurrency(appt.deposit_amount_cents ?? 0, 'USD') }}
            </td>

            <!-- Status -->
            <td class="px-4 py-4">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded font-label-sm text-label-sm"
                :class="appointmentStatusConfig(appt.status).cls"
              >
                {{ appointmentStatusConfig(appt.status).label }}
              </span>
            </td>

            <!-- Actions -->
            <td class="px-4 py-4 text-right">
              <div class="flex items-center justify-end gap-1">
                <!-- Mark paid — only for pending/confirmed -->
                <button
                  v-if="appt.status === 'pending' || appt.status === 'confirmed'"
                  type="button"
                  data-mark-paid-btn
                  :disabled="markingPaid === appt.id"
                  @click="handleMarkPaid(appt)"
                  class="px-3 py-1.5 rounded-lg text-[12px] font-medium bg-primary/10 text-primary hover:bg-primary/20 transition-colors disabled:opacity-40"
                  :title="`Marcar como pagado`"
                >
                  <span class="material-symbols-outlined text-[14px] align-middle" aria-hidden="true">payments</span>
                  Pagado
                </button>

                <!-- Cancel — only for non-cancelled -->
                <button
                  v-if="appt.status !== 'cancelled'"
                  type="button"
                  data-cancel-btn
                  :disabled="cancelling === appt.id"
                  @click="handleCancel(appt)"
                  class="px-3 py-1.5 rounded-lg text-[12px] font-medium bg-error-container text-on-error-container hover:opacity-80 transition-colors disabled:opacity-40"
                  title="Cancelar cita"
                >
                  <span class="material-symbols-outlined text-[14px] align-middle" aria-hidden="true">cancel</span>
                  Cancelar
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
