import { createApp } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { hydrate } from './services/storage.js'
import { usePushStore } from './stores/push.js'
import reveal from './directives/reveal.js'
import './style.css'

// @capacitor/preferences is async, so the cached auth session (token/user)
// must be warmed BEFORE mounting — the Axios request interceptor in
// services/api.js reads the token synchronously from this cache on every
// request and has no way to await Preferences itself.
async function bootstrap() {
  try {
    await hydrate()
  } catch (error) {
    // A failed/rejected Preferences read must not block app boot: fall back
    // to "no cached session" (the user will simply appear logged out) rather
    // than leaving the app on a permanent blank screen.
    console.error('Failed to hydrate cached auth session, continuing without it:', error)
  }

  const pinia = createPinia()
  setActivePinia(pinia) // lets usePushStore() below be called outside a component's setup()

  const app = createApp(App)
  app.use(pinia)
  app.use(router)
  // Registered so `v-reveal.trazo` can paint the section headings (Phase 4).
  // The matching `.reveal` CSS had been ported in Phase 2 and sat unused until
  // this directive arrived.
  app.directive('reveal', reveal)
  app.mount('#app')

  // Push registration (tasks 8.6-8.8): the other trigger point besides a
  // fresh login (see stores/auth.js's loginWithGoogle()) is app boot with
  // an already-cached session (the user is still logged in from a previous
  // run, but registration may not have succeeded yet — e.g. it failed last
  // time, or the app was reinstalled). Fired after mount, deliberately NOT
  // awaited: push.js's init() never throws (every internal failure is
  // caught and recorded on its own `error` state) and must never delay
  // rendering the app's first screen.
  usePushStore()
    .init()
    .catch((error) => {
      console.error('Push registration failed at app boot:', error)
    })
}

bootstrap()
