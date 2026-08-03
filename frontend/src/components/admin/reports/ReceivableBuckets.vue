<script setup>
import { formatCurrency } from '../../../utils/money.js'

// The three receivable buckets (design D6) plus the two headline figures
// that MUST read as distinct, not interchangeable: Total = A+B+C is every
// unsettled non-cancelled appointment; Projection = B+C is the cash-flow
// projection, which deliberately excludes bucket A — a deposit that was
// never collected is not money the business can plan around yet. Rendering
// them as two look-alike totals would hide exactly the distinction the
// spec's "Receivable buckets are mutually exclusive and complete"
// requirement exists to make visible.
const props = defineProps({
  bucketA: { type: Object, required: true },
  bucketB: { type: Object, required: true },
  bucketC: { type: Object, required: true },
  totalReceivableCents: { type: Number, required: true },
  projectionCents: { type: Number, required: true },
})
</script>

<template>
  <div class="bg-surface rounded-2xl border border-blush-canvas/20 p-5">
    <h2 class="font-title-md text-title-md text-on-surface mb-4">Cuentas por cobrar</h2>

    <div v-if="totalReceivableCents <= 0" class="text-center py-10 font-body-md text-body-md text-on-surface-variant">
      No hay cuentas por cobrar pendientes
    </div>

    <template v-else>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <div data-receivable-bucket-a class="rounded-xl border border-blush-canvas/20 p-4">
          <p class="font-label-sm text-label-sm text-on-surface-variant">Reservas sin confirmar (A)</p>
          <p class="font-title-md text-title-md text-on-surface mt-1">{{ formatCurrency(bucketA.outstanding_cents) }}</p>
          <p class="font-label-sm text-label-sm text-on-surface-variant mt-1">
            {{ bucketA.count }} cita(s) · excluida de la proyección
          </p>
        </div>
        <div data-receivable-bucket-b class="rounded-xl border border-blush-canvas/20 p-4">
          <p class="font-label-sm text-label-sm text-on-surface-variant">Saldo agendado (B)</p>
          <p class="font-title-md text-title-md text-on-surface mt-1">{{ formatCurrency(bucketB.outstanding_cents) }}</p>
          <p class="font-label-sm text-label-sm text-on-surface-variant mt-1">{{ bucketB.count }} cita(s)</p>
        </div>
        <div data-receivable-bucket-c class="rounded-xl border border-blush-canvas/20 p-4">
          <p class="font-label-sm text-label-sm text-on-surface-variant">Saldo vencido (C)</p>
          <p class="font-title-md text-title-md text-on-surface mt-1">{{ formatCurrency(bucketC.outstanding_cents) }}</p>
          <p class="font-label-sm text-label-sm text-on-surface-variant mt-1">{{ bucketC.count }} cita(s)</p>
        </div>
      </div>

      <div class="flex flex-wrap gap-4 pt-4 border-t border-blush-canvas/20">
        <div data-receivable-total class="flex-1 min-w-[220px] rounded-xl bg-surface-container-low p-4">
          <p class="font-label-sm text-label-sm text-on-surface-variant">Total por cobrar (A + B + C)</p>
          <p class="font-headline-sm text-headline-sm text-deep-marsala mt-1">{{ formatCurrency(totalReceivableCents) }}</p>
        </div>
        <div data-receivable-projection class="flex-1 min-w-[220px] rounded-xl bg-apricot-glow/15 border border-apricot-glow/40 p-4">
          <p class="font-label-sm text-label-sm text-on-surface-variant">Proyección de flujo de caja (B + C)</p>
          <p class="font-headline-sm text-headline-sm text-deep-marsala mt-1">{{ formatCurrency(projectionCents) }}</p>
          <p class="font-label-sm text-label-sm text-on-surface-variant mt-2">
            No incluye reservas sin confirmar (A) — el anticipo todavía no fue cobrado
          </p>
        </div>
      </div>
    </template>
  </div>
</template>
