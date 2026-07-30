import { ref } from 'vue'

let uid = 0

// Shared collapse/expand state for the three catalog filter bars
// (ProductFilters.vue, ServiceFilters.vue, CourseFilters.vue). On a 375px
// phone the full filter stack (search + price range + a domain dropdown +
// sort + category chips) consumed the entire first viewport with zero
// product/service/course cards visible -- see the DEFECT 1 fix. All three
// bars now hide price range / domain dropdown / sort behind a "Filtros"
// toggle, keeping only search + category chips always visible.
//
// Only the open/closed state + a stable aria-controls id are shared here.
// The collapsible *content* differs per catalog (Product has a stock
// dropdown, Service an availability dropdown, Course has neither), so the
// markup itself stays in each Filters.vue rather than being abstracted into
// a generic wrapper component -- three call sites don't justify a config-
// driven abstraction, and keeping the markup local keeps each filter bar
// readable on its own.
export function useFilterCollapse(prefix = 'filters') {
  // Collapsed by default on every mount (Skill: progressive-disclosure).
  const isOpen = ref(false)
  const contentId = `${prefix}-collapsible-${++uid}`

  function toggle() {
    isOpen.value = !isOpen.value
  }

  return { isOpen, toggle, contentId }
}
