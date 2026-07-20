import { ref } from 'vue'

// Shared reactive theme state. The initial <html class="dark"> is set by the
// inline no-flash script in index.html; here we just mirror and control it.
const isDark = ref(
  typeof document !== 'undefined' && document.documentElement.classList.contains('dark')
)

function apply(dark) {
  isDark.value = dark
  const el = document.documentElement
  el.classList.toggle('dark', dark)
  try {
    localStorage.setItem('theme', dark ? 'dark' : 'light')
  } catch (e) {
    // ignore storage failures (private mode, etc.)
  }
}

export function useTheme() {
  return {
    isDark,
    toggle: () => apply(!isDark.value),
    setDark: () => apply(true),
    setLight: () => apply(false),
  }
}
