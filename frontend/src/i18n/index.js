import { createI18n } from 'vue-i18n'
import en from './en.json'
import km from './km.json'

export default createI18n({
  legacy: false,
  locale: localStorage.getItem('locale') || 'en',
  fallbackLocale: 'en',
  messages: { en, km },
})
