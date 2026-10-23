import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { router } from './router'
import '@fontsource-variable/dm-sans'
import '@fontsource-variable/manrope'
import './styles/main.css'
import './styles/usability.css'

createApp(App).use(createPinia()).use(router).mount('#app')
