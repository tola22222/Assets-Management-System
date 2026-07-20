import { createI18n } from 'vue-i18n'
import en from './en.json'
import km from './km.json'

const locale = localStorage.getItem('locale') || 'en'

if (typeof document !== 'undefined') {
  document.documentElement.lang = locale
}

export default createI18n({
  legacy: false,
  locale,
  fallbackLocale: 'en',
  messages: { en, km },
})
