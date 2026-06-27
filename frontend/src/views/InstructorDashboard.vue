<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useInstructorStore } from '../stores/instructor.js'
import { formatCurrency } from '../utils/money.js'
import MetricCard from '../components/instructor/MetricCard.vue'
import SalesChart from '../components/instructor/SalesChart.vue'

const store = useInstructorStore()

const loading = computed(() => store.loading)
const error = computed(() => store.error)
const kpis = computed(() => store.dashboard?.kpis ?? null)
const salesOverTime = computed(() => store.dashboard?.sales_over_time ?? [])

onMounted(() => {
  store.fetchDashboard()
})
</script>

<template>
  <section class="py-16 bg-background min-h-screen">
    <div class="max-w-container-max mx-auto px-gutter">

      <!-- Page header + nav tabs -->
      <div v-reveal class="mb-10 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
          <h1 class="font-headline-lg text-headline-lg text-deep-marsala">Panel del Instructor</h1>
          <p class="font-body-md text-body-md text-on-surface-variant mt-1">
            Resumen de tu actividad y ventas
          </p>
        </div>

        <!-- Cross-navigation links -->
        <div class="flex items-center gap-3">
          <RouterLink
            to="/instructor/submissions"
            class="inline-flex items-center gap-2 font-label-md text-label-md text-primary border border-primary px-4 py-2 rounded-xl hover:bg-primary hover:text-on-primary transition-colors self-start sm:self-auto"
          >
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">assignment</span>
            Entregas
          </RouterLink>
          <RouterLink
            to="/instructor"
            class="inline-flex items-center gap-2 font-label-md text-label-md text-primary border border-primary px-4 py-2 rounded-xl hover:bg-primary hover:text-on-primary transition-colors self-start sm:self-auto"
          >
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">menu_book</span>
            Mis Cursos
          </RouterLink>
        </div>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loading" class="space-y-6">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
          <div
            v-for="i in 5"
            :key="i"
            class="bg-surface-muted rounded-2xl border border-outline-variant p-6 animate-pulse space-y-3"
          >
            <div class="h-3 bg-surface-container rounded w-2/3" />
            <div class="h-8 bg-surface-container rounded w-1/2" />
            <div class="h-2 bg-surface-container rounded w-1/3" />
          </div>
        </div>
        <div class="bg-surface-muted rounded-2xl border border-outline-variant p-6 animate-pulse">
          <div class="h-4 bg-surface-container rounded w-1/4 mb-6" />
          <div class="h-40 bg-surface-container rounded" />
        </div>
      </div>

      <!-- Error state -->
      <div v-else-if="error" class="text-center py-20">
        <span
          class="material-symbols-outlined text-error text-5xl mb-4"
          aria-hidden="true"
        >error</span>
        <p class="font-body-lg text-body-lg text-on-surface mb-4">{{ error }}</p>
        <button
          @click="store.fetchDashboard()"
          class="font-label-md text-label-md text-primary border border-primary px-5 py-2 rounded-xl hover:bg-primary hover:text-on-primary transition-colors"
        >
          Intentar de nuevo
        </button>
      </div>

      <!-- Dashboard content (including zero state) -->
      <template v-else-if="kpis">

        <!-- Friendly hint when instructor has no courses yet -->
        <div
          v-if="kpis.total_courses === 0"
          class="mb-6 bg-surface-container-low border border-blush-canvas/30 rounded-2xl p-5 flex items-start gap-3"
        >
          <span class="material-symbols-outlined text-apricot-glow text-2xl shrink-0" aria-hidden="true">lightbulb</span>
          <div>
            <p class="font-label-md text-label-md text-deep-marsala">
              Aún no tienes cursos creados.
            </p>
            <p class="font-body-md text-body-md text-on-surface-variant mt-0.5">
              Crea tu primer curso para empezar a generar ingresos.
              <RouterLink
                to="/instructor/courses/new"
                class="text-primary underline hover:no-underline ml-1"
              >Crear curso</RouterLink>
            </p>
          </div>
        </div>

        <!-- KPI metric cards grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
          <MetricCard
            v-reveal="0"
            label="Ingresos"
            :value="formatCurrency(kpis.total_revenue_cents, kpis.currency)"
            icon="payments"
          />
          <MetricCard
            v-reveal="1"
            label="Ventas"
            :value="kpis.total_sales"
            icon="shopping_cart"
          />
          <MetricCard
            v-reveal="2"
            label="Estudiantes"
            :value="kpis.total_students"
            icon="group"
          />
          <MetricCard
            v-reveal="3"
            label="Cursos"
            :value="kpis.total_courses"
            icon="menu_book"
            :hint="`${kpis.published_courses} publicado${kpis.published_courses !== 1 ? 's' : ''}`"
          />
          <MetricCard
            v-reveal="4"
            label="Valoración media"
            :value="kpis.average_rating ?? '—'"
            icon="star"
            :hint="kpis.average_rating === null ? 'Sin valoraciones aún' : 'sobre 5'"
          />
        </div>

        <!-- Sales chart card -->
        <div v-reveal class="bg-surface-container-low rounded-2xl border border-outline-variant shadow-md shadow-primary/5 p-6">
          <h2 class="font-title-md text-title-md text-deep-marsala mb-6">
            Ventas (últimos 6 meses)
          </h2>
          <SalesChart :data="salesOverTime" />
        </div>

      </template>

    </div>
  </section>
</template>
