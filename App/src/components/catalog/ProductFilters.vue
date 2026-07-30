<script setup>
// Two-way bound to the container's filter state. Purely presentational.
import { computed } from 'vue'
import { useFilterCollapse } from '../filters/useFilterCollapse.js'

const search = defineModel('search', { type: String, default: '' })
const minPrice = defineModel('minPrice', { type: String, default: '' })
const maxPrice = defineModel('maxPrice', { type: String, default: '' })
const sort = defineModel('sort', { type: String, default: 'newest' })
const category = defineModel('category', { type: String, default: '' })
const stockState = defineModel('stockState', { type: String, default: '' })

const props = defineProps({
  categories: { type: Array, default: () => [] },
})

const inputClass =
  // min-h-11 (44px) guarantees the touch/tap-target minimum for every input
  // and select in this bar (Skill: touch-target-size, touch-friendly-input).
  'px-4 py-2 min-h-11 bg-surface-container-low border border-blush-canvas/30 rounded-xl ' +
  'focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all ' +
  'font-body-md text-body-md'

// [DEFECT 1 fix] Price range, stock state, and sort collapse behind a
// "Filtros" toggle -- see useFilterCollapse.js's header comment for why the
// full stack (search + price + stock + sort + category chips) filled the
// entire first viewport on a 375px phone.
const { isOpen, toggle, contentId } = useFilterCollapse('product-filters')

// Active-filter count for the toggle badge (Skill: progressive-disclosure --
// hidden state must never be invisible state). Counts only controls that
// actually narrow results:
//   - price range counts as ONE filter if either bound is set (min/max read
//     as a single "price" concept to the user, not two separate filters)
//   - stock state counts if it deviates from "" ("Todo el stock", the default)
//   - sort counts only if it deviates from "newest" ("Más recientes", the
//     default) -- a sort order reorders results, it does not narrow them
const activeFilterCount = computed(() => {
  let count = 0
  if (minPrice.value || maxPrice.value) count += 1
  if (stockState.value) count += 1
  if (sort.value !== 'newest') count += 1
  return count
})
</script>

<template>
  <!-- Sticky, but offset below the AppShell's own sticky top bar (also
       top:0) instead of colliding with it, and kept at z-20 — the two fixed
       nav bars own z-40 and nothing in this screen's scope may reach that
       (Hard constraint: overlays here stay <= z-20). -->
  <section class="bg-surface border-y border-blush-canvas/20 sticky top-[calc(var(--app-topbar-h)+var(--safe-area-top))] z-20">
    <div class="max-w-container-max mx-auto px-gutter py-6 flex flex-col gap-4">
      <!-- Always-visible row: search + filter toggle -->
      <div class="flex items-center gap-3">
        <div class="relative flex-grow max-w-md">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline" aria-hidden="true">
            search
          </span>
          <input
            v-model="search"
            type="text"
            placeholder="Buscar producto..."
            :class="[inputClass, 'pl-10 w-full']"
            aria-label="Buscar productos"
          />
        </div>

        <!-- h-12 w-12 (48dp) minimum touch target even though the label
             makes the button wider (Skill: touch-target-size). The active
             count keeps collapsed state from being invisible state. -->
        <button
          type="button"
          data-filters-toggle
          :aria-expanded="isOpen"
          :aria-controls="contentId"
          class="inline-flex items-center justify-center gap-1.5 shrink-0 min-h-12 px-4 rounded-xl border border-blush-canvas/30 text-on-surface-variant font-label-sm text-label-sm transition-colors active:bg-surface-container-high"
          @click="toggle"
        >
          <span class="material-symbols-outlined text-[20px]" aria-hidden="true">tune</span>
          <span>Filtros<span v-if="activeFilterCount > 0"> · {{ activeFilterCount }}</span></span>
        </button>
      </div>

      <!-- Collapsible: price range + stock + sort. Animated via
           grid-template-rows (0fr -> 1fr) rather than height/max-height:
           this is the modern, JS-measurement-free way to animate a
           variable-height region, and it is the explicitly sanctioned
           exception to the transform/opacity-only rule for this specific
           case. motion-safe: keeps the transition out entirely under
           prefers-reduced-motion, so the state still changes instantly
           (never stuck, never animated) when motion is disabled.
           :inert prevents keyboard/AT focus from landing on filters that
           are visually collapsed. -->
      <div
        :id="contentId"
        data-filters-panel
        class="grid motion-safe:transition-[grid-template-rows] motion-safe:duration-200 motion-safe:ease-out"
        :class="isOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
        :inert="!isOpen"
      >
        <div class="overflow-hidden">
          <div class="flex flex-wrap items-center gap-3 pt-3">
            <input
              :value="minPrice"
              type="number"
              min="0"
              placeholder="Precio mín"
              :class="[inputClass, 'w-32']"
              aria-label="Precio mínimo"
              @input="minPrice = $event.target.value"
            />
            <input
              :value="maxPrice"
              type="number"
              min="0"
              placeholder="Precio máx"
              :class="[inputClass, 'w-32']"
              aria-label="Precio máximo"
              @input="maxPrice = $event.target.value"
            />

            <!-- Stock state filter -->
            <select
              v-model="stockState"
              data-stock-filter
              :class="[inputClass, 'bg-surface-container-low']"
              aria-label="Disponibilidad de stock"
            >
              <option value="">Todo el stock</option>
              <option value="in_stock">En Stock</option>
              <option value="out_of_stock">Agotados</option>
            </select>

            <select v-model="sort" :class="[inputClass, 'bg-surface-container-low']" aria-label="Ordenar">
              <option value="newest">Más recientes</option>
              <option value="price_asc">Precio: menor a mayor</option>
              <option value="price_desc">Precio: mayor a menor</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Always-visible row: category pills -->
      <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filtrar por categoría">
        <!-- "Todas" pill -->
        <button
          data-category-pill="all"
          type="button"
          @click="category = ''"
          class="inline-flex items-center justify-center min-h-11 px-4 rounded-full font-label-sm text-label-sm transition-colors"
          :class="category === ''
            ? 'bg-primary text-on-primary'
            : 'border border-blush-canvas/30 text-on-surface-variant hover:bg-surface-container-low'"
          :aria-pressed="category === ''"
        >
          Todas
        </button>

        <!-- One pill per category -->
        <button
          v-for="cat in props.categories"
          :key="cat.id"
          :data-category-pill="cat.slug"
          type="button"
          @click="category = cat.slug"
          class="inline-flex items-center justify-center min-h-11 px-4 rounded-full font-label-sm text-label-sm transition-colors"
          :class="category === cat.slug
            ? 'bg-primary text-on-primary'
            : 'border border-blush-canvas/30 text-on-surface-variant hover:bg-surface-container-low'"
          :aria-pressed="category === cat.slug"
        >
          {{ cat.name }}
        </button>
      </div>
    </div>
  </section>
</template>
