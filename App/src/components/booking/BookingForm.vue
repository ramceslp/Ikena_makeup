<script setup>
import { ref, computed } from 'vue'
import { useBookingStore } from '../../stores/booking.js'
import { formatCurrency } from '../../utils/money.js'

// Ported from frontend/src/components/booking/BookingForm.vue.
//
// This form used to call POST /bookings directly: it created a `pending`
// appointment on the spot and then told the customer "te contactaremos por
// WhatsApp para completar el pago del depósito". The deposit was never
// collectable from the app at all — rendering the gateway would breach the
// spec's Mobile App Boundaries ("payment never renders in the app's own
// WebView"), and the in-app-safe path was explicitly deferred and then never
// built. The result was an app that silently filled the venue's agenda with
// unpaid holds.
//
// It now submits through bookingStore.payDeposit(), which snapshots the
// selection into POST /checkout/handoff and opens the web checkout in the
// system browser. The appointment itself is created server-side at redeem,
// by the same CreateBookingAction the web uses — so the slot is claimed at
// the moment of payment, not at the moment of intent.
//
// Retained from the original port: no local 401 -> router.push('/login')
// branch. App's global services/api.js response interceptor already clears
// the session and redirects on ANY 401 with a redirect-loop guard; a second
// local redirect would be redundant and could race it. The store's
// bookingError still carries the Spanish "please sign in" text as a fallback
// for when that redirect itself fails.
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

const bookingStore = useBookingStore()
const whatsapp = ref('')
const whatsappError = ref('')
// True once the system browser has been handed the checkout URL. Swaps the
// form for a "finish in your browser" panel so a second tap cannot mint a
// second handoff token for the same slot.
const handedOff = ref(false)

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

  const opened = await bookingStore.payDeposit({
    service_id: props.service.id,
    scheduled_date: props.selectedSlot.scheduled_date,
    scheduled_time: props.selectedSlot.scheduled_time,
    whatsapp: whatsapp.value,
  })

  if (opened) {
    handedOff.value = true
    emit('booking-success', { scheduled_date: props.selectedSlot.scheduled_date, scheduled_time: props.selectedSlot.scheduled_time })
  }
}

// Lets the customer take another run at it without leaving the screen — e.g.
// they closed the browser tab by accident, or the 10-minute token expired.
function retry() {
  handedOff.value = false
  bookingStore.handoffOpened = false
  bookingStore.bookingError = null
}
</script>

<template>
  <div class="flex flex-col gap-6 bg-surface rounded-2xl border border-blush-canvas/20 p-6">
    <!-- Handed-off state — replaces the form once the browser has been opened.
         Deliberately NOT worded as "reserva confirmada": nothing is booked
         until the deposit is paid in the browser, and claiming otherwise here
         is exactly the promise the old flow made and could not keep. -->
    <div v-if="handedOff" data-booking-handed-off class="flex flex-col items-center gap-3 text-center py-4">
      <span class="material-symbols-outlined text-5xl text-primary" aria-hidden="true">open_in_new</span>
      <h2 class="font-headline-sm text-headline-sm text-deep-marsala">Continúa en tu navegador</h2>
      <p class="font-body-md text-body-md text-on-surface-variant">
        Abrimos el pago del depósito en tu navegador. Tu cita queda reservada una vez que completes el pago;
        el enlace vence en 10 minutos.
      </p>
      <button
        type="button"
        data-booking-retry
        class="font-label-md text-label-md text-primary underline min-h-11 px-4"
        @click="retry"
      >
        Volver a intentar
      </button>
    </div>

    <template v-else>
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
        data-booking-error
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
          Abriendo el pago…
        </span>
        <span v-else class="flex items-center justify-center gap-2">
          <span class="material-symbols-outlined text-[20px]" aria-hidden="true">open_in_new</span>
          Pagar depósito en el navegador
        </span>
      </button>

      <p class="font-label-sm text-label-sm text-outline text-center">
        Tu cita se reserva al completar el pago. El saldo restante se paga en persona el día de la cita.
      </p>
    </template>
  </div>
</template>
