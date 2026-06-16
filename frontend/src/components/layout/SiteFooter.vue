<script setup>
// Footer links point to pages that don't exist yet (Privacidad, Términos, etc.).
// Kept as placeholders until those routes/pages are built — see migration backlog.
const links = [
  { label: 'Privacidad', href: '#' },
  { label: 'Términos de Uso', href: '#' },
  { label: 'Contacto', href: '#' },
  { label: 'Ayuda', href: '#' },
]
</script>

<template>
  <footer class="bg-surface-container-lowest border-t border-blush-canvas/20">
    <div class="max-w-container-max mx-auto px-gutter py-12 flex flex-col md:flex-row justify-between items-center gap-8">
      <!-- Brand -->
      <div class="flex flex-col items-center md:items-start gap-4">
        <div class="font-headline-lg-mobile text-headline-lg-mobile font-bold text-deep-marsala">
          Ikena
        </div>
        <p class="font-body-md text-body-md text-on-surface-variant text-center md:text-left max-w-xs">
          Elevando el estándar del arte visual a través de la educación de excelencia.
        </p>
      </div>

      <!-- Links -->
      <nav class="flex flex-wrap justify-center gap-x-8 gap-y-4">
        <a
          v-for="link in links"
          :key="link.label"
          :href="link.href"
          class="font-body-md text-body-md text-on-surface-variant hover:text-primary hover:underline transition-all"
        >
          {{ link.label }}
        </a>
      </nav>

      <!-- Social + copyright -->
      <div class="flex flex-col items-center md:items-end gap-4">
        <div class="flex gap-4">
          <button class="p-2 text-on-surface-variant hover:text-primary transition-all" aria-label="Sitio web">
            <span class="material-symbols-outlined" aria-hidden="true">public</span>
          </button>
          <button class="p-2 text-on-surface-variant hover:text-primary transition-all" aria-label="Compartir">
            <span class="material-symbols-outlined" aria-hidden="true">share</span>
          </button>
        </div>
        <div class="font-body-md text-body-md text-on-surface-variant opacity-80">
          © {{ new Date().getFullYear() }} Ikena Makeup Academy. Todos los derechos reservados.
        </div>
      </div>
    </div>
  </footer>
</template>
