<script setup>
import { ref } from 'vue'

// Ported unchanged from frontend/src/components/home/NewsletterCta.vue.
// BaseButton (a styled wrapper around <button>) is not ported -- replaced
// with a plain <button> to avoid pulling in the web design-system component
// tree; the visual/design-token parity gap is documented at the Home.vue
// level (see views/Home.vue's port notes) rather than repeated per file.
const emit = defineEmits(['subscribe'])

const email = ref('')
const submitted = ref(false)

function onSubmit() {
  if (!email.value) return
  emit('subscribe', email.value)
  submitted.value = true
  email.value = ''
}
</script>

<template>
  <section class="section-y bg-surface-container-low">
    <div class="max-w-4xl mx-auto px-gutter text-center space-y-6">
      <h2 class="font-headline-lg text-headline-lg text-deep-marsala">
        Únete a nuestra comunidad artística
      </h2>
      <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto">
        Recibe masterclasses gratuitas, descuentos exclusivos y noticias de la academia
        directamente en tu correo.
      </p>

      <p
        v-if="submitted"
        class="font-body-lg text-body-lg text-primary"
        role="status"
      >
        ¡Gracias por suscribirte! Pronto tendrás noticias nuestras.
      </p>
      <form
        v-else
        class="flex flex-col sm:flex-row gap-4 max-w-lg mx-auto"
        @submit.prevent="onSubmit"
      >
        <input
          v-model="email"
          type="email"
          required
          placeholder="Tu correo electrónico"
          class="flex-grow px-6 py-4 rounded-xl border border-blush-canvas bg-surface focus:ring-1 focus:ring-primary outline-none font-body-md text-body-md"
          aria-label="Correo electrónico"
        />
        <button
          type="submit"
          class="btn-gloss px-8 py-4 rounded-xl bg-apricot-glow text-deep-marsala font-bold whitespace-nowrap"
        >
          Suscribirme
        </button>
      </form>
    </div>
  </section>
</template>
