import i18n from '../i18n'

// i18n.global.locale is a ref in Composition API mode (legacy: false), so it's
// already shared/reactive across every component that reads it — this
// composable just adds the persistence + <html lang> side effects.
const locale = i18n.global.locale

function setLocale(value) {
  if (!value || value === locale.value) return
  locale.value = value
  try {
    localStorage.setItem('locale', value)
  } catch (e) {
    // ignore storage failures (private mode, etc.)
  }
  document.documentElement.lang = value
}

export function useLocale() {
  return { locale, setLocale }
}
