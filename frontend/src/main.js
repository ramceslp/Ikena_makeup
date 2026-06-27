import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router/index.js'
import reveal from './directives/reveal.js'
import './style.css'
import App from './App.vue'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.use(router)
app.directive('reveal', reveal)
app.mount('#app')
