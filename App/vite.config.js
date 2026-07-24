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
    // Vitest runs in 'test' mode, which does NOT auto-load .env.development
    // (that file only applies to `vite`/`vite build --mode development`).
    // Provide the same safe emulator default here so `npx vitest run`
    // collects and runs without depending on an externally exported shell
    // env var. Production is unaffected: it never runs through Vitest and
    // still requires VITE_API_URL from the CI/CD build environment.
    env: {
      VITE_API_URL: 'http://10.0.2.2:8000/api',
    },
  },
})
