import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue(), tailwindcss()],

  server: {
    // Named hosts only. `allowedHosts: true` disables Host-header validation
    // entirely, which is Vite's DNS-rebinding defence: while `npm run dev`
    // runs, an attacker-controlled domain resolving to 127.0.0.1 could then
    // read this dev server's source, source maps and VITE_* env values.
    // Add tunnel/ngrok hostnames via VITE_DEV_ALLOWED_HOSTS (comma-separated).
    allowedHosts: [
      'localhost',
      '127.0.0.1',
      ...(process.env.VITE_DEV_ALLOWED_HOSTS?.split(',').map((h) => h.trim()).filter(Boolean) ?? []),
    ],
  },

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
