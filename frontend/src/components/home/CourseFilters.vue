<script setup>
// Two-way bound to the container's filter state. Purely presentational:
// it knows nothing about the store or how filters are applied.
const search = defineModel('search', { type: String, default: '' })
const minPrice = defineModel('minPrice', { type: [String, Number], default: '' })
const maxPrice = defineModel('maxPrice', { type: [String, Number], default: '' })
const sort = defineModel('sort', { type: String, default: 'newest' })

const inputClass =
  'px-4 py-2 bg-surface-container-low border border-blush-canvas/30 rounded-xl ' +
  'focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all ' +
  'font-body-md text-body-md'
</script>

<template>
  <section class="bg-surface border-y border-blush-canvas/20 sticky top-0 z-40">
    <div class="max-w-container-max mx-auto px-gutter py-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <!-- Search -->
      <div class="relative flex-grow max-w-md">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline" aria-hidden="true">
          search
        </span>
        <input
          v-model="search"
          type="text"
          placeholder="Buscar curso o técnica..."
          :class="[inputClass, 'pl-10 w-full']"
          aria-label="Buscar cursos"
        />
      </div>

      <!-- Price range + sort -->
      <div class="flex flex-wrap items-center gap-3">
        <input
          v-model="minPrice"
          type="number"
          min="0"
          placeholder="Precio mín"
          :class="[inputClass, 'w-32']"
          aria-label="Precio mínimo"
        />
        <input
          v-model="maxPrice"
          type="number"
          min="0"
          placeholder="Precio máx"
          :class="[inputClass, 'w-32']"
          aria-label="Precio máximo"
        />
        <select v-model="sort" :class="[inputClass, 'bg-surface-container-low']" aria-label="Ordenar">
          <option value="newest">Más recientes</option>
          <option value="price_asc">Precio: menor a mayor</option>
          <option value="price_desc">Precio: mayor a menor</option>
        </select>
      </div>
    </div>
  </section>
</template>
