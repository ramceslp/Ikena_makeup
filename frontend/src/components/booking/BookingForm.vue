<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useBookingStore } from '../../stores/booking.js'
import { formatCurrency } from '../../utils/money.js'

const props = defineProps({
  selectedSlot: {
    type: Object,
    default: null,
  },
  service: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['booking-success'])

const router = useRouter()
const route = useRoute()
const bookingStore = useBookingStore()
const whatsapp = ref('')
const whatsappError = ref('')

// CLIENT-SIDE PREVIEW only — formula: price (in dollars) × deposit_percentage = cents.
// The backend DepositCalculator is the source of truth for the actual charged amount,
// returned as `deposit_amount_cents` on the 201 response.
const depositCents = computed(() => {
  if (!props.service) return 0
  const price = parseFloat(props.service.price) || 0
  const pct = props.service.deposit_percentage ?? 50
  return Math.round(price * pct)
})

const depositFormatted = computed(() => formatCurrency(depositCents.value, 'USD'))

const bookingError = computed(() => bookingStore.bookingError)
const isLoading = computed(() => bookingStore.isLoading)
const isDisabled = computed(() => !props.selectedSlot || isLoading.value)

async function submit() {
  if (isDisabled.value) return

  // Client-side whatsapp validation
  if (!whatsapp.value.trim()) {
    whatsappError.value = 'El número de WhatsApp es obligatorio'
    return
  }
  whatsappError.value = ''

  bookingStore.bookingError = null

  const result = await bookingStore.createBooking({
    service_id: props.service.id,
    scheduled_date: props.selectedSlot.scheduled_date,
    scheduled_time: props.selectedSlot.scheduled_time,
    whatsapp: whatsapp.value,
  })

  if (result === null && bookingStore.bookingError?.includes('sesión')) {
    // 401 — redirect to login with redirect-back query (mirrors router guard pattern)
    router.push({ name: 'Login', query: { redirect: route.fullPath } })
    return
  }

  if (result) {
    emit('booking-success', result)
    // Redirect to payment gateway URL when available
    const url = result.gateway_payload?.checkout_url
    if (url) {
      window.location.href = url
    }
  }
}
</script>

<template>
  <div class="flex flex-col gap-6 bg-surface rounded-2xl border border-blush-canvas/20 p-6">
    <h2 class="font-headline-sm text-headline-sm text-deep-marsala">Confirmar reserva</h2>

    <!-- Selected slot summary -->
    <div
      v-if="selectedSlot"
      class="bg-surface-container-low rounded-xl p-4 flex flex-col gap-1"
    >
      <p class="font-label-md text-label-md text-on-surface-variant">Horario seleccionado</p>
      <p class="font-title-sm text-title-sm text-on-surface">
        {{ selectedSlot.scheduled_date }} — {{ selectedSlot.scheduled_time }}
      </p>
    </div>
    <div v-else class="bg-surface-container-low rounded-xl p-4">
      <p class="font-body-sm text-body-sm text-on-surface-variant">
        Selecciona un horario arriba para continuar
      </p>
    </div>

    <!-- Whatsapp input -->
    <div class="flex flex-col gap-2">
      <label class="font-label-md text-label-md text-on-surface" for="booking-whatsapp">
        WhatsApp de contacto <span class="text-error">*</span>
      </label>
      <input
        id="booking-whatsapp"
        v-model="whatsapp"
        type="tel"
        data-whatsapp-input
        placeholder="+593 09 9999 9999"
        maxlength="20"
        required
        class="w-full rounded-xl border border-blush-canvas/30 bg-surface px-4 py-3 font-body-md text-body-md text-on-surface placeholder:text-outline focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all"
        :class="{ 'border-error': whatsappError }"
      />
      <p v-if="whatsappError" class="font-label-sm text-label-sm text-error" role="alert">
        {{ whatsappError }}
      </p>
    </div>

    <!-- Deposit line -->
    <div class="flex items-center justify-between bg-apricot-glow/10 rounded-xl px-4 py-3 border border-apricot-glow/30">
      <div class="flex flex-col gap-0.5">
        <p class="font-label-sm text-label-sm text-on-surface-variant">
          Depósito a pagar ({{ service.deposit_percentage ?? 50 }}% del total)
        </p>
        <p class="font-body-sm text-body-sm text-on-surface-variant line-through">
          Total: {{ formatCurrency(parseFloat(service.price || 0) * 100, 'USD') }}
        </p>
      </div>
      <span class="font-display-sm text-display-sm text-primary font-bold">
        {{ depositFormatted }}
      </span>
    </div>

    <!-- Inline error (409 or others) -->
    <div
      v-if="bookingError"
      class="bg-error-container rounded-xl px-4 py-3 font-body-md text-body-md text-on-error-container"
      role="alert"
    >
      {{ bookingError }}
    </div>

    <!-- Submit button -->
    <button
      type="button"
      data-submit-btn
      :disabled="isDisabled"
      @click="submit"
      class="w-full rounded-xl bg-deep-marsala text-on-primary font-label-lg text-label-lg py-4 hover:opacity-90 active:scale-[0.98] transition-all disabled:opacity-40 disabled:cursor-not-allowed"
    >
      <span v-if="isLoading" class="flex items-center justify-center gap-2">
        <span class="material-symbols-outlined animate-spin text-[20px]" aria-hidden="true">refresh</span>
        Procesando…
      </span>
      <span v-else>Confirmar y Pagar Depósito</span>
    </button>

    <p class="font-label-sm text-label-sm text-outline text-center">
      El saldo restante se paga en persona el día de la cita
    </p>
  </div>
</template>
