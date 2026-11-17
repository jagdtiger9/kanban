import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import App from './App.vue'
import router from './router'
import { preset } from './primevue/preset'
import { themePreset } from './primevue/primeTheme'
import './style.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(ConfirmationService)
app.use(PrimeVue, {
    theme: {
        preset: themePreset,
        options: {
            prefix: 'p',
            darkModeSelector: '.dark',
            cssLayer: { name: 'primevue', order: 'theme, base, primevue' },
        },
    },
    pt: preset,
})

app.mount('#app')
