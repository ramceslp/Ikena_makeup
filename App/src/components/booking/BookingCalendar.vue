<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import {
  parseLocalDate,
  formatDateKey,
  addDaysToDateKey,
  formatDayHeading,
  formatMonthYearHeading,
  WEEKDAY_HEADER_LABELS,
} from '../../utils/localDate.js'

const props = defineProps({
  // [{ date: 'YYYY-MM-DD', available_count: number }] — days absent from
  // this array are CLOSED (no agenda block); days present with
  // available_count === 0 are FULLY BOOKED.
  days: {
    type: Array,
    default: () => [],
  },
  selectedDate: {
    type: String,
    default: null,
  },
  // Caller supplies "today" (local date key) so this component stays pure
  // and deterministic — no hidden `new Date()` inside the calendar itself.
  today: {
    type: String,
    required: true,
  },
  // Booking window is [today, today + windowDays). Mirrors the backend's
  // 60-day look-ahead.
  windowDays: {
    type: Number,
    default: 60,
  },
  // True while a booking submission is in flight elsewhere in the tree (see
  // SlotPicker's `isSubmitting`). Locks ALL day-cell and month-nav
  // interaction so the user can't switch day/month mid-submit and race the
  // two cascading refreshes a 409 triggers. Deliberately NOT tied to
  // `daysLoading`/background refreshes — those are non-blocking and must
  // stay interactive; only an in-flight booking POST locks the calendar.
  locked: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['day-selected'])

function pad2(n) {
  return String(n).padStart(2, '0')
}

function monthKeyOf(date) {
  return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}`
}

const maxDateKey = computed(() => addDaysToDateKey(props.today, props.windowDays - 1))
const daysMap = computed(() => new Map(props.days.map((d) => [d.date, d])))

// Viewed month state — initialized from the selected day (if any) else today.
const initial = parseLocalDate(props.selectedDate || props.today)
const viewedYear = ref(initial.getFullYear())
const viewedMonth = ref(initial.getMonth())

const minMonthKey = computed(() => monthKeyOf(parseLocalDate(props.today)))
const maxMonthKey = computed(() => monthKeyOf(parseLocalDate(maxDateKey.value)))
const viewedMonthKey = computed(() => `${viewedYear.value}-${pad2(viewedMonth.value + 1)}`)

const canGoPrev = computed(() => viewedMonthKey.value > minMonthKey.value)
const canGoNext = computed(() => viewedMonthKey.value < maxMonthKey.value)

const headingText = computed(() => formatMonthYearHeading(viewedYear.value, viewedMonth.value))

function prevMonth() {
  if (!canGoPrev.value || props.locked) return
  if (viewedMonth.value === 0) {
    viewedMonth.value = 11
    viewedYear.value -= 1
  } else {
    viewedMonth.value -= 1
  }
}

function nextMonth() {
  if (!canGoNext.value || props.locked) return
  if (viewedMonth.value === 11) {
    viewedMonth.value = 0
    viewedYear.value += 1
  } else {
    viewedMonth.value += 1
  }
}

// Builds a Monday-first flat cell list for the viewed month, padded with
// adjacent-month days so the grid always spans full weeks. Uses the
// (year, month, day) Date constructor throughout, which is always
// local-time-safe — never parses bare ISO strings.
function buildMonthGrid(year, monthIndex) {
  const firstOfMonth = new Date(year, monthIndex, 1)
  const startDow = (firstOfMonth.getDay() + 6) % 7 // Monday = 0 ... Sunday = 6
  const daysInMonth = new Date(year, monthIndex + 1, 0).getDate()
  const cells = []

  for (let i = startDow; i > 0; i--) {
    cells.push({ date: new Date(year, monthIndex, 1 - i), inCurrentMonth: false })
  }
  for (let day = 1; day <= daysInMonth; day++) {
    cells.push({ date: new Date(year, monthIndex, day), inCurrentMonth: true })
  }
  while (cells.length % 7 !== 0) {
    const lastDate = cells[cells.length - 1].date
    cells.push({
      date: new Date(lastDate.getFullYear(), lastDate.getMonth(), lastDate.getDate() + 1),
      inCurrentMonth: false,
    })
  }
  return cells
}

function cellInfo(cell) {
  const key = formatDateKey(cell.date)
  const entry = daysMap.value.get(key)
  let state
  if (!cell.inCurrentMonth || key < props.today || key > maxDateKey.value) {
    state = 'outside'
  } else if (!entry) {
    state = 'closed'
  } else {
    state = entry.available_count > 0 ? 'available' : 'full'
  }
  return { key, date: cell.date, state, availableCount: entry?.available_count }
}

const weeks = computed(() => {
  const cells = buildMonthGrid(viewedYear.value, viewedMonth.value).map(cellInfo)
  const result = []
  for (let i = 0; i < cells.length; i += 7) result.push(cells.slice(i, i + 7))
  return result
})

const flatCells = computed(() => weeks.value.flat())

function ariaLabelFor(info) {
  const label = `${formatDayHeading(info.key)} de ${info.date.getFullYear()}`
  if (info.state === 'available') {
    // Locked ≠ unavailable — tell assistive-tech users this is a temporary
    // wait, not that the day itself has no openings.
    if (props.locked) return `${label}, reserva en proceso, espera un momento`
    const n = info.availableCount
    return `${label}, ${n} horario${n === 1 ? '' : 's'} disponible${n === 1 ? '' : 's'}`
  }
  if (info.state === 'full') return `${label}, completo`
  return `${label}, no disponible`
}

// ── Roving tabindex + selection ─────────────────────────────────────────
// Only AVAILABLE cells participate in the roving-tabindex scheme. Full and
// closed cells use the native `disabled` attribute, which already removes
// them from the tab order — no custom trap-avoidance logic needed for them.

// Focus targets are looked up by querying within the component root rather
// than tracked via a per-key `ref` Map populated from inline `v-for` ref
// callbacks. Those callbacks are a brand-new function every render, which
// makes Vue treat the ref as "changed" on every re-render — even for cells
// whose availability didn't actually change — so a stale unmount can race a
// same-tick remount and silently null out an entry that was just correctly
// (re-)set, breaking any `.focus()` call immediately afterwards. Querying
// the live DOM by its stable `data-date` attribute avoids that entirely.
const rootEl = ref(null)
function focusDayCell(key) {
  rootEl.value?.querySelector(`[data-calendar-day][data-date="${key}"]`)?.focus()
}
function domFocusedDayKey() {
  return rootEl.value?.querySelector('[data-calendar-day]:focus')?.dataset.date ?? null
}

function firstAvailableKeyInView() {
  const found = flatCells.value.find((c) => c.state === 'available')
  return found ? found.key : null
}

// Finds the earliest day, anywhere in the booking window (not just the
// initially-viewed month), that is actually available. Used to auto-advance
// the view when the venue has no openings left this month but does have
// some later within the window — bounded by `maxDateKey`, so this never
// navigates the view past the end of the booking window.
function firstAvailableEntryInWindow() {
  return props.days
    .filter((d) => d.available_count > 0 && d.date >= props.today && d.date <= maxDateKey.value)
    .sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0))[0]
}

// If the viewed month contains zero available days, no cell would ever get
// `tabindex="0"` and a keyboard-only user could not Tab into the day grid at
// all. Jump to the first month in the window that has an available day
// instead. If none of the whole window has availability, stay put — the
// grid legitimately has no entry point, communicated via each cell's own
// aria-label.
//
// Called ONLY at setup time (immediately below), not reactively from the
// `days` watcher — see that watcher's comment for why a background refresh
// must never move the user's viewport.
function jumpToFirstAvailableMonthInWindow() {
  if (flatCells.value.some((c) => c.state === 'available')) return
  const firstAvailable = firstAvailableEntryInWindow()
  if (firstAvailable) {
    const target = parseLocalDate(firstAvailable.date)
    viewedYear.value = target.getFullYear()
    viewedMonth.value = target.getMonth()
  }
}

jumpToFirstAvailableMonthInWindow()

const focusedKey = ref(
  props.selectedDate && flatCells.value.some((c) => c.key === props.selectedDate && c.state === 'available')
    ? props.selectedDate
    : firstAvailableKeyInView(),
)

watch([viewedYear, viewedMonth], () => {
  if (!flatCells.value.some((c) => c.key === focusedKey.value && c.state === 'available')) {
    focusedKey.value = firstAvailableKeyInView()
  }
})

// `props.days` can change without a month change — most notably after a
// post-409 `fetchAvailableDays` refresh, where the currently-focused day can
// flip from 'available' to 'full'. Without this watcher every cell's
// tabindex falls to -1 (see the template binding below), stranding keyboard
// and screen-reader users outside the grid entirely.
watch(
  () => props.days,
  async () => {
    if (!flatCells.value.some((c) => c.key === focusedKey.value && c.state === 'available')) {
      // This callback runs on Vue's "pre" flush — BEFORE the template
      // re-renders with the new (now-unavailable) day state, so
      // `document.activeElement` here still reflects DOM focus as it was
      // prior to this update. Once the template re-renders, the `:disabled`
      // binding natively blurs the element to `document.body` — capture
      // whether that's about to happen to the currently-focused cell so we
      // can restore focus ourselves afterwards, mirroring `moveFocus()`.
      // Only re-focus when focus was actually inside the grid; otherwise
      // every background refresh would yank focus from unrelated page
      // content. `domFocusedDayKey() === focusedKey.value` alone is NOT
      // enough: both sides can independently be `null` (no cell holds DOM
      // focus, and/or the viewed month has zero available cells), and
      // `null === null` would wrongly read as "focus was inside the grid" —
      // stealing real focus from wherever the user actually was (e.g.
      // mid-typing in a sibling form field) the next time a cell becomes
      // available. Require an actual focused cell.
      const hadDomFocus = domFocusedDayKey() !== null && domFocusedDayKey() === focusedKey.value
      // Deliberately NOT calling `jumpToFirstAvailableMonthInWindow()` here
      // (unlike the setup-time call above, right below that function).
      // Auto-advancing the VIEWED MONTH out from under a user who is
      // actively looking at it — mid-session, triggered by a background
      // refresh they didn't ask for — was found to teleport them away from
      // a month they deliberately navigated to. Interactive navigation is
      // now locked for the entire duration of a booking submit (see the
      // `locked` prop), so the only way `days` changes reactively is a
      // background refresh or a post-409 re-fetch — neither should ever
      // move the user's viewport.
      //
      // ACCEPTED TRADE-OFF (documented, not an oversight): if this leaves
      // the currently-viewed month with zero available cells, no day cell
      // will hold `tabindex="0"` and a keyboard-only user cannot Tab
      // directly into the day grid from this month. The month-nav buttons
      // remain in the tab order, so the user can still reach an available
      // month manually — this is acceptable degraded behavior, not a trap.
      focusedKey.value = firstAvailableKeyInView()
      if (hadDomFocus && focusedKey.value) {
        await nextTick()
        focusDayCell(focusedKey.value)
      }
    }
  },
)

watch(
  () => props.selectedDate,
  (val) => {
    if (val && flatCells.value.some((c) => c.key === val && c.state === 'available')) {
      focusedKey.value = val
    }
  },
)

function selectDay(info) {
  if (info.state !== 'available' || props.locked) return
  focusedKey.value = info.key
  emit('day-selected', info.key)
}

async function moveFocus(rowDelta, colDelta) {
  if (props.locked) return
  const rows = weeks.value
  let row = rows.findIndex((r) => r.some((c) => c.key === focusedKey.value))
  let col = row >= 0 ? rows[row].findIndex((c) => c.key === focusedKey.value) : -1
  if (row < 0 || col < 0) return

  const targetRow = row + rowDelta
  const targetCol = col + colDelta
  if (targetRow < 0 || targetRow >= rows.length) return
  const targetRowCells = rows[targetRow]
  if (targetCol < 0 || targetCol >= targetRowCells.length) return

  const target = targetRowCells[targetCol]
  if (target.state !== 'available') return

  focusedKey.value = target.key
  await nextTick()
  focusDayCell(target.key)
}

function onKeydown(event, info) {
  switch (event.key) {
    case 'ArrowRight':
      event.preventDefault()
      moveFocus(0, 1)
      break
    case 'ArrowLeft':
      event.preventDefault()
      moveFocus(0, -1)
      break
    case 'ArrowDown':
      event.preventDefault()
      moveFocus(1, 0)
      break
    case 'ArrowUp':
      event.preventDefault()
      moveFocus(-1, 0)
      break
    case 'Enter':
    case ' ':
      event.preventDefault()
      selectDay(info)
      break
    default:
      break
  }
}
</script>

<template>
  <div
    ref="rootEl"
    class="w-full flex flex-col gap-3"
    role="group"
    :aria-label="`Calendario de disponibilidad, ${headingText}`"
    :aria-disabled="locked ? 'true' : undefined"
    :data-calendar-locked="locked ? 'true' : undefined"
  >
    <!-- Month navigation -->
    <div class="flex items-center justify-between">
      <button
        type="button"
        data-calendar-prev
        aria-label="Mes anterior"
        :disabled="!canGoPrev || locked"
        @click="prevMonth"
        class="flex items-center justify-center w-10 h-10 rounded-full text-on-surface-variant transition-all disabled:opacity-30 disabled:cursor-not-allowed hover:enabled:bg-surface-container hover:enabled:text-primary cursor-pointer"
      >
        <span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>
      </button>
      <span data-calendar-heading class="font-title-md text-title-md text-on-surface capitalize">
        {{ headingText }}
      </span>
      <button
        type="button"
        data-calendar-next
        aria-label="Mes siguiente"
        :disabled="!canGoNext || locked"
        @click="nextMonth"
        class="flex items-center justify-center w-10 h-10 rounded-full text-on-surface-variant transition-all disabled:opacity-30 disabled:cursor-not-allowed hover:enabled:bg-surface-container hover:enabled:text-primary cursor-pointer"
      >
        <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
      </button>
    </div>

    <!-- Weekday header (decorative; each day cell carries the full label via aria-label) -->
    <div class="grid grid-cols-7 gap-1" aria-hidden="true">
      <span
        v-for="label in WEEKDAY_HEADER_LABELS"
        :key="label"
        class="font-label-sm text-label-sm text-on-surface-variant text-center py-1"
      >
        {{ label }}
      </span>
    </div>

    <!-- Weeks grid -->
    <div class="flex flex-col gap-1">
      <div v-for="(week, wIdx) in weeks" :key="wIdx" class="grid grid-cols-7 gap-1">
        <button
          v-for="info in week"
          :key="info.key"
          type="button"
          data-calendar-day
          :data-date="info.key"
          :data-day-state="info.state"
          :data-day-selected="selectedDate === info.key ? 'true' : undefined"
          :disabled="info.state !== 'available' || locked"
          :tabindex="info.state === 'available' ? (focusedKey === info.key ? 0 : -1) : -1"
          :aria-label="ariaLabelFor(info)"
          :aria-pressed="info.state === 'available' ? (selectedDate === info.key ? 'true' : 'false') : undefined"
          @click="selectDay(info)"
          @keydown="onKeydown($event, info)"
          class="flex flex-col items-center justify-center gap-0.5 aspect-square rounded-lg border text-center transition-all"
          :class="[
            locked && info.state === 'available'
              ? 'border-blush-canvas/20 bg-surface-container text-on-surface-variant opacity-50 cursor-wait'
              : info.state === 'available'
                ? selectedDate === info.key
                  ? 'border-primary bg-primary/10 text-primary ring-2 ring-primary cursor-pointer'
                  : 'border-blush-canvas/30 bg-surface text-on-surface hover:border-primary hover:bg-primary/5 cursor-pointer'
                : info.state === 'full'
                  ? 'border-blush-canvas/20 bg-surface-container text-on-surface-variant opacity-60 cursor-not-allowed'
                  : 'border-transparent text-outline/50 cursor-not-allowed',
          ]"
        >
          <span class="font-label-md text-label-md">{{ info.date.getDate() }}</span>
          <span
            v-if="info.state === 'full'"
            class="w-1 h-1 rounded-full bg-outline"
            aria-hidden="true"
          />
        </button>
      </div>
    </div>
  </div>
</template>
