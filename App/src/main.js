import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { hydrate } from './services/storage.js'
import './style.css'

// @capacitor/preferences is async, so the cached auth session (token/user)
// must be warmed BEFORE mounting — the Axios request interceptor in
// services/api.js reads the token synchronously from this cache on every
// request and has no way to await Preferences itself.
async function bootstrap() {
  await hydrate()

  const app = createApp(App)
  app.use(createPinia())
  app.use(router)
  app.mount('#app')
}

bootstrap()
