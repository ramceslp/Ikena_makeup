<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '../../services/api.js'
import { formatCurrency } from '../../utils/money.js'
import { defaultReportDateRange } from '../../utils/reportsFormat.js'
import { getBusinessToday } from '../../utils/localDate.js'
import ReportFilters from '../../components/admin/reports/ReportFilters.vue'
import KpiCard from '../../components/admin/reports/KpiCard.vue'
import RevenueTimelineChart from '../../components/admin/reports/RevenueTimelineChart.vue'
import CompositionBars from '../../components/admin/reports/CompositionBars.vue'

// Control-panel container (Phase 4 / PR2b). Reads the three read-only
// endpoints wired in PR2a (`/admin/reports/{summary,timeline,composition}`)
// via the shared axios instance — NOT a bare fetch/<a href>, so the
// Sanctum bearer token attaches (see `services/api.js`'s request
// interceptor).
const defaults = defaultReportDateRange(getBusinessToday())

const from = ref(defaults.from)
const to = ref(defaults.to)
const granularity = ref('day')

const summary = ref(null)
const timeline = ref(null)
const composition = ref(null)
const isLoading = ref(false)
const loadError = ref('')

function queryParams() {
  return { from: from.value, to: to.value, granularity: granularity.value }
}

async function loadReports() {
  isLoading.value = true
  loadError.value = ''
  try {
    const params = queryParams()
    const [summaryRes, timelineRes, compositionRes] = await Promise.all([
      api.get('/admin/reports/summary', { params }),
      api.get('/admin/reports/timeline', { params }),
      api.get('/admin/reports/composition', { params }),
    ])
    summary.value = summaryRes.data.data
    timeline.value = timelineRes.data.data
    composition.value = compositionRes.data.data
  } catch (err) {
    loadError.value = err.response?.data?.message || 'Error al cargar los reportes'
  } finally {
    isLoading.value = false
  }
}

watch([from, to, granularity], loadReports)
onMounted(loadReports)

// The filter metadata is identical on all three responses (ReportController
// merges the same `filterMeta()` into each) — timeline is used as the
// canonical source since granularity is the field that actually varies.
const degraded = computed(() => timeline.value?.degraded ?? false)
const requestedGranularity = computed(() => timeline.value?.requested_granularity ?? granularity.value)
const effectiveGranularity = computed(() => timeline.value?.effective_granularity ?? granularity.value)
</script>

<template>
  <div class="max-w-container-max mx-auto px-gutter py-12">
    <div class="mb-8">
      <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Reportes</h1>
      <p class="font-body-md text-body-md text-on-surface-variant mt-1">
        Ingresos confirmados, línea de tiempo y composición por tipo de venta
      </p>
    </div>

    <ReportFilters
      class="mb-6"
      :from="from"
      :to="to"
      :granularity="granularity"
      :degraded="degraded"
      :requested-granularity="requestedGranularity"
      :effective-granularity="effectiveGranularity"
      @update:from="from = $event"
      @update:to="to = $event"
      @update:granularity="granularity = $event"
    />

    <div v-if="loadError" class="mb-6 p-4 bg-error-container rounded-xl font-body-md text-body-md text-on-error-container">
      {{ loadError }}
    </div>

    <div v-if="isLoading && !summary" class="text-center py-16">
      <span class="material-symbols-outlined text-5xl text-primary animate-spin" aria-hidden="true">refresh</span>
    </div>

    <template v-else-if="summary">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <KpiCard label="Ingresos confirmados" :value="formatCurrency(summary.confirmed_revenue_cents)" />
        <KpiCard
          label="Depósitos retenidos"
          :value="formatCurrency(summary.retained_deposits_cents)"
          hint="Por cancelaciones — no forma parte del ingreso por servicio entregado"
        />
        <KpiCard label="Órdenes" :value="String(summary.orders_count)" />
        <KpiCard
          label="Inscripciones gratuitas"
          :value="String(summary.free_enrollments_count)"
          hint="No cuenta como ingreso"
        />
      </div>

      <div class="bg-surface rounded-2xl border border-blush-canvas/20 p-5 mb-6">
        <h2 class="font-title-md text-title-md text-on-surface mb-4">Ingresos en el tiempo</h2>
        <RevenueTimelineChart
          v-if="timeline"
          :periods="timeline.periods"
          :effective-granularity="effectiveGranularity"
        />
      </div>

      <CompositionBars
        v-if="composition"
        :by-type="composition.by_type"
        :retained-deposits-cents="composition.retained_deposits_cents"
        :total-cents="composition.total_cents"
      />
    </template>
  </div>
</template>
