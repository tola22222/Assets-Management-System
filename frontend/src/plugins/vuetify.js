import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'

// Mirrors the brand palette from src/assets/main.css (--color-brand-*, --color-accent)
// so Vuetify components (used in the parallel Vuetify architecture) read as part of
// the same app rather than a visibly different design system.
const brand = {
  50: '#eef4f0',
  100: '#d6e5dc',
  500: '#35634c',
  600: '#274c3a',
  700: '#1f3d2e',
  800: '#16281e',
}

const accent = '#c9a24b'

const pepyLight = {
  dark: false,
  colors: {
    primary: brand[700],
    'primary-darken-1': brand[800],
    secondary: accent,
    error: '#b3261e',
    info: '#0288d1',
    success: '#2e7d32',
    warning: '#ed6c02',
    background: '#f5f2ea',
    surface: '#ffffff',
  },
}

const pepyDark = {
  dark: true,
  colors: {
    primary: brand[500],
    'primary-darken-1': brand[600],
    secondary: accent,
    error: '#e46962',
    info: '#4fc3f7',
    success: '#66bb6a',
    warning: '#ffa726',
    background: '#16281e',
    surface: '#1f3d2e',
  },
}

export default createVuetify({
  theme: {
    defaultTheme: 'pepyLight',
    themes: {
      pepyLight,
      pepyDark,
    },
  },
  defaults: {
    VBtn: { rounded: 'lg' },
    VTextField: { variant: 'outlined', density: 'comfortable' },
    VSelect: { variant: 'outlined', density: 'comfortable' },
    VCard: { rounded: 'lg' },
  },
})
