import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue(), tailwindcss()],

  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: [],
    server: {
      deps: {
        // TipTap packages use subpath exports not compatible with vitest's
        // default module resolution; inlining lets vite transform them correctly.
        inline: ['@tiptap/vue-3', '@tiptap/starter-kit', '@tiptap/extension-youtube', '@tiptap/core', '@tiptap/pm'],
      },
    },
  },
})
