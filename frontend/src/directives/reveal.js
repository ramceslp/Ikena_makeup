/*
 * v-reveal — scroll-triggered entrance animation.
 *
 * Adds a hidden-state class (defined in style.css) and toggles `is-revealed`
 * when the element scrolls into view via IntersectionObserver.
 *
 * Usage:
 *   <div v-reveal>            → fades/slides in once, when it enters the viewport
 *   <div v-reveal="i">        → same, staggered by `i * STAGGER_STEP` ms (grids/lists)
 *
 * Section headings used to ride a `.trazo` modifier here. They are now pure CSS
 * (`animation-timeline: view()` — see the "Trazo" block in style.css), because
 * the stroke is scrubbed by the scroll rather than triggered by it and so has
 * nothing for JavaScript to do.
 *
 * Accessibility: when the user prefers reduced motion — or the browser lacks
 * IntersectionObserver — the element renders immediately with no animation.
 * The hidden state in CSS is also gated behind `prefers-reduced-motion:
 * no-preference`, so content is never stuck invisible.
 */

const HIDDEN_CLASS = 'reveal'
const REVEALED_CLASS = 'is-revealed'
const STAGGER_STEP = 70 // ms of delay added per item index

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

    el.classList.add(HIDDEN_CLASS)

    // A custom property rather than an inline `transition-delay`, so the
    // transition stays declared entirely in style.css and this only supplies a
    // number.
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
          // property is free again: `.is-revealed { transform: none }` would
          // otherwise override hover lifts (hover:-translate-y) on the same
          // element.
          const cleanup = (event) => {
            if (event.propertyName !== 'transform') return
            el.classList.remove(HIDDEN_CLASS, REVEALED_CLASS)
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
