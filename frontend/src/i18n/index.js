import { createI18n } from 'vue-i18n'
import en from './en.json'
import km from './km.json'
import roleWording from './roleWording'

const locale = localStorage.getItem('locale') || 'en'

if (typeof document !== 'undefined') {
  document.documentElement.lang = locale
}

// Untouched copies, so switching from a staff account back to any other role
// restores the base text instead of keeping the staff wording.
const base = { en: structuredClone(en), km: structuredClone(km) }

const i18n = createI18n({
  legacy: false,
  locale,
  fallbackLocale: 'en',
  messages: { en, km },
})

/**
 * Role-specific wording for "register" (see ./roleWording.js): "assigned" for
 * staff, "add asset" for HR and the Accountant, base text for anyone else.
 * Call whenever the signed-in user changes.
 */
export function applyRoleWording(role) {
  const wording = roleWording[role]
  for (const loc of Object.keys(base)) {
    i18n.global.setLocaleMessage(loc, structuredClone(base[loc]))
    if (wording?.[loc]) {
      i18n.global.mergeLocaleMessage(loc, wording[loc])
    }
  }
}

// Whoever is already signed in when the app boots.
try {
  applyRoleWording(JSON.parse(localStorage.getItem('user') || 'null')?.role)
} catch {
  // A corrupt cached user just means base wording until the next sign-in.
}

export default i18n
