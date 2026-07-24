<script setup>
// Two-way bound to the container's filter state. Purely presentational.
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
  'px-4 py-2 bg-surface-container-low border border-blush-canvas/30 rounded-xl ' +
  'focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all ' +
  'font-body-md text-body-md'
</script>

<template>
  <section class="bg-surface border-y border-blush-canvas/20 sticky top-0 z-40">
    <div class="max-w-container-max mx-auto px-gutter py-6 flex flex-col gap-4">
      <!-- Row 1: Search + price + sort + stock -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Search -->
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

        <!-- Price range + stock + sort -->
        <div class="flex flex-wrap items-center gap-3">
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

      <!-- Row 2: Category pills -->
      <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filtrar por categoría">
        <!-- "Todas" pill -->
        <button
          data-category-pill
          type="button"
          @click="category = ''"
          class="px-3 py-1 rounded-full font-label-sm text-label-sm transition-colors"
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
          data-category-pill
          type="button"
          @click="category = cat.slug"
          class="px-3 py-1 rounded-full font-label-sm text-label-sm transition-colors"
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
