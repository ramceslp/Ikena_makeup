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
    plugins: [vue(), tailwindcss()],

    server: {
      // Named hosts only. `allowedHosts: true` disables Host-header validation
      // entirely, and that check is Vite's DNS-rebinding defence: while
      // `npm run dev` runs, a domain an attacker controls pointing at
      // 127.0.0.1 could otherwise read this dev server's source, source maps
      // and VITE_* values.
      //
      // ikena.ramceslp.click is the Cloudflare tunnel that exposes this dev
      // server on a real domain. It is listed here rather than left to a
      // gitignored .env so a fresh clone works without a setup step.
      // Ephemeral tunnels (ngrok, trycloudflare) change hostname per run —
      // add those via VITE_DEV_ALLOWED_HOSTS=host-a,host-b in frontend/.env.
      allowedHosts: [
        'localhost',
        '127.0.0.1',
        'ikena.ramceslp.click',
        ...tunnelHosts,
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
  }
})
