<script setup>
import { ref, computed } from 'vue'
import { useBookingStore } from '../../stores/booking.js'
import { formatCurrency } from '../../utils/money.js'

// Ported from frontend/src/components/booking/BookingForm.vue with two
// deliberate, spec-driven deviations (mobile-capacitor-setup Phase 7):
//
// 1. Dropped the post-success `window.location.href =
//    result.gateway_payload?.checkout_url` redirect. Confirmed dead code
//    even on the web: `POST /bookings`'s actual response shape
//    (BookingController::store) is `{ order_id, provider, config,
//    is_near_capacity, warning_message }` -- there is no `gateway_payload`
//    field, so that branch never fires today. More importantly, this app
//    has a hard "payment never renders in the app's own WebView" constraint
//    (spec's Mobile App Boundaries) -- `window.location.href` to a gateway
//    URL would violate that outright if the field were ever populated.
//    Booking confirmation here just shows a local success state, matching
//    the spec's literal wording ("POST bookings succeeds and the app shows
//    confirmation"). In-app-safe deposit payment (via the checkout-handoff
//    endpoint's `type: 'appointment'`, opened through @capacitor/browser)
//    is deferred to Phase 8, same as the cart flow.
// 2. Dropped the local 401 -> `router.push({ name: 'Login' })` branch.
//    App's global `services/api.js` response interceptor (Phase 5/6)
//    already clears the session and redirects to /login on ANY 401 with a
//    redirect-loop guard; a second, local redirect here would be redundant
//    and could race the global one. `bookingStore.bookingError` still
//    surfaces the Spanish "please sign in" message via the inline error
//    banner below as a fallback for when that redirect itself fails — on
//    the success path the interceptor's `await router.push('/login')`
//    completes before this component's local `catch` ever runs, so the
//    banner is not normally visible during a successful in-flight redirect.
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
const confirmedResult = ref(null)

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

  if (result) {
    confirmedResult.value = result.data
    emit('booking-success', result)
  }
}
</script>

<template>
  <div class="flex flex-col gap-6 bg-surface rounded-2xl border border-blush-canvas/20 p-6">
    <!-- Confirmation state — replaces the form once a booking succeeds -->
    <div v-if="confirmedResult" data-booking-confirmed class="flex flex-col items-center gap-3 text-center py-4">
      <span class="material-symbols-outlined text-5xl text-primary" aria-hidden="true">check_circle</span>
      <h2 class="font-headline-sm text-headline-sm text-deep-marsala">Reserva confirmada</h2>
      <p class="font-body-md text-body-md text-on-surface-variant">
        Código de orden #{{ confirmedResult.order_id }}. Te contactaremos por WhatsApp para completar el pago del depósito.
      </p>
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
          Procesando…
        </span>
        <span v-else>Confirmar y Pagar Depósito</span>
      </button>

      <p class="font-label-sm text-label-sm text-outline text-center">
        El saldo restante se paga en persona el día de la cita
      </p>
    </template>
  </div>
</template>
