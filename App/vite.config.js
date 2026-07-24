import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  // Capacitor loads the built app from a local WebView (file:// / capacitor://),
  // so asset URLs must be relative, not root-absolute.
  base: './',
  plugins: [vue(), tailwindcss()],

  test: {
    environment: 'jsdom',
    globals: true,
  },
})
