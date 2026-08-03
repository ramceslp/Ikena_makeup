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
import LedgerTable from '../../components/admin/reports/LedgerTable.vue'
import RankingTable from '../../components/admin/reports/RankingTable.vue'
import FunnelChart from '../../components/admin/reports/FunnelChart.vue'
import ReceivableBuckets from '../../components/admin/reports/ReceivableBuckets.vue'

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
const topProducts = ref(null)
const topServices = ref(null)
const topCourses = ref(null)
const funnel = ref(null)
const receivables = ref(null)
const ledger = ref(null)
const ledgerPage = ref(1)
const ledgerStream = ref('')
const isLoading = ref(false)
const loadError = ref('')

function queryParams() {
  return { from: from.value, to: to.value, granularity: granularity.value }
}

function ledgerParams() {
  const params = { from: from.value, to: to.value, page: ledgerPage.value }
  if (ledgerStream.value) params.stream = [ledgerStream.value]
  return params
}

async function loadAggregates() {
  const params = queryParams()
  const [summaryRes, timelineRes, compositionRes, productsRes, servicesRes, coursesRes, funnelRes] = await Promise.all([
    api.get('/admin/reports/summary', { params }),
    api.get('/admin/reports/timeline', { params }),
    api.get('/admin/reports/composition', { params }),
    api.get('/admin/reports/rankings/products', { params }),
    api.get('/admin/reports/rankings/services', { params }),
    api.get('/admin/reports/rankings/courses', { params }),
    api.get('/admin/reports/funnel', { params }),
  ])
  summary.value = summaryRes.data.data
  timeline.value = timelineRes.data.data
  composition.value = compositionRes.data.data
  topProducts.value = productsRes.data.data
  topServices.value = servicesRes.data.data
  topCourses.value = coursesRes.data.data
  funnel.value = funnelRes.data.data
}

async function loadLedger() {
  const { data } = await api.get('/admin/reports/ledger', { params: ledgerParams() })
  ledger.value = data
}

// Receivables are a snapshot of "now" (design D6) — they carry no
// ReportFilter and must NOT be re-fetched on every range change, unlike
// rankings/funnel above which are range-scoped and live in loadAggregates.
// Loaded once on mount only; deliberately absent from both watchers below.
async function loadReceivables() {
  const { data } = await api.get('/admin/reports/receivables')
  receivables.value = data.data
}

async function run(loaders) {
  isLoading.value = true
  loadError.value = ''
  try {
    await Promise.all(loaders.map((load) => load()))
  } catch (err) {
    loadError.value = err.response?.data?.message || 'Error al cargar los reportes'
  } finally {
    isLoading.value = false
  }
}

// The aggregates depend on the range and granularity only; the ledger also
// pages and filters by stream. Split watchers keep a page click to ONE round
// trip instead of four, on the one screen built for browsing.
watch([from, to, granularity], () => {
  // A new range invalidates the current page — asking for page 3 of a result
  // set that now has one page returns an empty table. Resetting fires the
  // ledger watcher below, so only load the ledger here when it will not.
  const alreadyFirstPage = ledgerPage.value === 1
  ledgerPage.value = 1
  run(alreadyFirstPage ? [loadAggregates, loadLedger] : [loadAggregates])
})
watch([ledgerPage, ledgerStream], () => run([loadLedger]))
onMounted(() => run([loadAggregates, loadLedger, loadReceivables]))

function onLedgerPageChange(page) {
  ledgerPage.value = page
}

function onLedgerStreamChange(stream) {
  ledgerPage.value = 1
  ledgerStream.value = stream
}

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
      class="no-print mb-6"
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
      <div class="no-print grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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

      <div class="no-print bg-surface rounded-2xl border border-blush-canvas/20 p-5 mb-6">
        <h2 class="font-title-md text-title-md text-on-surface mb-4">Ingresos en el tiempo</h2>
        <RevenueTimelineChart
          v-if="timeline"
          :periods="timeline.periods"
          :effective-granularity="effectiveGranularity"
        />
      </div>

      <CompositionBars
        v-if="composition"
        class="no-print"
        :by-type="composition.by_type"
        :retained-deposits-cents="composition.retained_deposits_cents"
        :total-cents="composition.total_cents"
      />

      <LedgerTable
        v-if="ledger"
        class="mt-6"
        :rows="ledger.data"
        :meta="ledger.meta"
        :stream-filter="ledgerStream"
        :from="from"
        :to="to"
        @update:page="onLedgerPageChange"
        @update:stream="onLedgerStreamChange"
      />

      <RankingTable
        v-if="topProducts"
        class="no-print mt-6"
        :products="topProducts"
        :services="topServices"
        :courses="topCourses"
      />

      <FunnelChart v-if="funnel" class="no-print mt-6" :orders="funnel.orders" :appointments="funnel.appointments" />

      <ReceivableBuckets
        v-if="receivables"
        class="no-print mt-6"
        :bucket-a="receivables.bucket_a"
        :bucket-b="receivables.bucket_b"
        :bucket-c="receivables.bucket_c"
        :total-receivable-cents="receivables.total_receivable_cents"
        :projection-cents="receivables.projection_cents"
      />
    </template>
  </div>
</template>
