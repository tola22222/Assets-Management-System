import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import i18n from './i18n'
import vuetify from './plugins/vuetify'
import { confirm, notify, loading } from './composables'
import './composables/useThemeColor' // applies the persisted brand color before mount
import './assets/main.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(i18n)
app.use(vuetify)

// Options API access alongside the Composition API composables (useConfirm/useNotify/useLoading).
app.config.globalProperties.$confirm = confirm
app.config.globalProperties.$notify = notify
app.config.globalProperties.$loading = loading

app.mount('#app')
