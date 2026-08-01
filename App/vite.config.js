import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  // Vite does NOT load .env files into process.env — inside this config file
  // loadEnv() is the only way to read them. It also picks up real shell/CI
  // environment variables with the same prefix, so both sources work.
  const env = loadEnv(mode, process.cwd(), 'VITE_')

  const tunnelHosts = (env.VITE_DEV_ALLOWED_HOSTS ?? '')
    .split(',')
    .map((host) => host.trim())
    .filter(Boolean)

  return {
    // Capacitor loads the built app from a local WebView (file:// / capacitor://),
    // so asset URLs must be relative, not root-absolute.
    base: './',
    plugins: [vue(), tailwindcss()],

    server: {
      // Named hosts only. `allowedHosts: true` disables Host-header validation
      // entirely, and that check is Vite's DNS-rebinding defence: while
      // `npm run dev` runs, a domain an attacker controls pointing at
      // 127.0.0.1 could otherwise read this dev server's source, source maps
      // and VITE_* values.
      //
      // 10.0.2.2 is the Android emulator's loopback alias to the host machine
      // (see .env.development). ikena.ramceslp.click is the Cloudflare tunnel
      // used for local development on a real domain. Ephemeral tunnels
      // (ngrok, trycloudflare) change hostname per run — add those via
      // VITE_DEV_ALLOWED_HOSTS=host-a,host-b in App/.env.development.local.
      allowedHosts: [
        'localhost',
        '127.0.0.1',
        '10.0.2.2',
        'ikena.ramceslp.click',
        ...tunnelHosts,
      ],
    },

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
  }
})
