/*
 * v-reveal — scroll-triggered entrance animation.
 *
 * Adds a hidden-state class (defined in style.css) and toggles `is-revealed`
 * when the element scrolls into view via IntersectionObserver.
 *
 * Usage:
 *   <div v-reveal>            → fades/slides in once, when it enters the viewport
 *   <div v-reveal="i">        → same, staggered by `i * STAGGER_STEP` ms (grids/lists)
 *   <h2 v-reveal.trazo>       → paints in left-to-right like a lipstick swipe
 *
 * Why `trazo` is triggered rather than scroll-scrubbed: a brush stroke has its
 * own tempo. Bound to `animation-timeline: view()` it would crawl on a slow
 * scroll and snap on a flick, so the gesture stops reading as a stroke. Being
 * time-based also keeps it working in browsers without scroll timelines, and
 * immune to the `overflow: hidden` trap that freezes those timelines (see the
 * "Velo" block in style.css).
 *
 * The cost of that choice is that the hidden state has to stay observable —
 * see the clip-vs-mask note on VARIANTS below.
 *
 * Accessibility: when the user prefers reduced motion — or the browser lacks
 * IntersectionObserver — the element renders immediately with no animation.
 * The hidden states in CSS are also gated behind `prefers-reduced-motion:
 * no-preference`, so content is never stuck invisible or clipped.
 */

const REVEALED_CLASS = 'is-revealed'
const STAGGER_STEP = 70 // ms of delay added per item index

/*
 * Each variant owns the class carrying its hidden state and the property whose
 * `transitionend` marks the entrance as finished. They must not share a class:
 * `reveal` would layer its translate/opacity entrance on top of the wipe.
 *
 * `settledProperty` is matched as a substring because engines both prefix and
 * decompose these longhands. Chromium 150 in the Android WebView reports
 * `-webkit-mask-position-x` — measured, not assumed — which matches neither the
 * unprefixed name nor a suffix test. The `::after` glow settles separately as
 * `background-position-x`, which correctly matches nothing here.
 *
 * ⚠️ `trazo` hides with a mask, never a clip. Chromium computes intersection
 * after clipping, so an element hidden with `clip-path: inset(0 100% 0 0)`
 * reports intersectionRatio 0 even mid-viewport: the observer below would never
 * fire, and the heading would stay invisible forever. See style.css.
 */
const VARIANTS = {
  reveal: { hiddenClass: 'reveal', settledProperty: 'transform' },
  trazo: { hiddenClass: 'trazo', settledProperty: 'mask-position' },
}

function prefersReducedMotion() {
  return (
    typeof window !== 'undefined' &&
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
  )
}

export default {
  mounted(el, binding) {
    // No animation: reduced-motion preference or no observer support.
    if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') {
      return
    }

    const variant = binding.modifiers.trazo ? VARIANTS.trazo : VARIANTS.reveal

    el.classList.add(variant.hiddenClass)

    // A custom property rather than `transition-delay` directly: custom
    // properties inherit into pseudo-elements, and `trazo`'s glow lives on
    // ::after. An inline transition-delay would reach the text and not the
    // pigment, and the two would drift apart by the stagger amount.
    const index = Number(binding.value) || 0
    if (index > 0) {
      el.style.setProperty('--reveal-delay', `${index * STAGGER_STEP}ms`)
    }

    const observer = new IntersectionObserver(
      (entries, obs) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return
          el.classList.add(REVEALED_CLASS)
          obs.unobserve(el)

          // Once the entrance finishes, strip the classes so the animated
          // property is free again. For `reveal` that matters because
          // `.is-revealed { transform: none }` would override hover lifts
          // (hover:-translate-y) on the same element; for `trazo` it releases
          // the clip-path, which otherwise keeps cutting descenders and the
          // brush glow for the life of the page.
          const cleanup = (event) => {
            if (!String(event.propertyName ?? '').includes(variant.settledProperty)) return
            el.classList.remove(variant.hiddenClass, REVEALED_CLASS)
            el.style.removeProperty('--reveal-delay')
            el.removeEventListener('transitionend', cleanup)
          }
          el.addEventListener('transitionend', cleanup)
        })
      },
      { threshold: 0.15, rootMargin: '0px 0px -10% 0px' },
    )

    observer.observe(el)
    el._revealObserver = observer
  },

  unmounted(el) {
    if (el._revealObserver) {
      el._revealObserver.disconnect()
      delete el._revealObserver
    }
  },
}
