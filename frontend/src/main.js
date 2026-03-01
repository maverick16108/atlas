import './style.css'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import { MotionPlugin } from '@vueuse/motion'
import i18n from './i18n'
import App from './App.vue'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(MotionPlugin)
app.use(i18n)

// Global Layouts
// Global Layouts
import PublicLayout from './layouts/PublicLayout.vue'
import ClientLayout from './layouts/ClientLayout.vue'
import AdminLayout from './layouts/AdminLayout.vue'
app.component('PublicLayout', PublicLayout)
app.component('ClientLayout', ClientLayout)
app.component('AdminLayout', AdminLayout)

// Apply theme immediately before mount (prevents flash)
const savedTheme = localStorage.getItem('theme')
if (savedTheme === 'dark' || (!savedTheme && window.matchMedia?.('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark')
} else {
    document.documentElement.classList.remove('dark')
}

app.mount('#app')
