/*
 * v-reveal — scroll-triggered entrance animation.
 *
 * Adds the `reveal` class (initial hidden state lives in style.css) and toggles
 * `is-revealed` when the element scrolls into view via IntersectionObserver.
 *
 * Usage:
 *   <div v-reveal>            → fades/slides in once, when it enters the viewport
 *   <div v-reveal="i">        → same, staggered by `i * STAGGER_STEP` ms (grids/lists)
 *
 * Accessibility: when the user prefers reduced motion — or the browser lacks
 * IntersectionObserver — the element renders immediately with no animation.
 * The hidden state in CSS is also gated behind `prefers-reduced-motion:
 * no-preference`, so content is never stuck invisible.
 */

const REVEAL_CLASS = 'reveal'
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

    el.classList.add(REVEAL_CLASS)

    const index = Number(binding.value) || 0
    if (index > 0) {
      el.style.transitionDelay = `${index * STAGGER_STEP}ms`
    }

    const observer = new IntersectionObserver(
      (entries, obs) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return
          el.classList.add(REVEALED_CLASS)
          obs.unobserve(el)

          // Once the entrance finishes, strip the reveal classes so the
          // element's `transform` is free again — otherwise
          // `.is-revealed { transform: none }` would override hover lifts
          // (hover:-translate-y) that live on the same element.
          const cleanup = (event) => {
            if (event.propertyName !== 'transform') return
            el.classList.remove(REVEAL_CLASS, REVEALED_CLASS)
            el.style.transitionDelay = ''
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
