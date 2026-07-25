import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'

// Full PEPY brand palette, ported from the retired Tailwind design system
// (assets/main.css's @theme block) so the app keeps its identity once Tailwind
// utility classes are gone. Primary is overwritten at runtime by
// useThemeColor.js's applyThemeColor() when the user picks a custom brand
// color in Settings — these are just the shipped defaults (brand-700/-800/-500
// etc.), matching the exact hex the old CSS tokens used.
const brand = {
  300: '#7fa992',
  500: '#35634c',
  600: '#274c3a',
  700: '#1f3d2e',
  800: '#16281e',
}
const accent = { DEFAULT: '#c9a24b', dark: '#a9822f' }

const pepyLight = {
  dark: false,
  colors: {
    primary: brand[700],
    'primary-darken-1': brand[800],
    'primary-lighten-1': brand[500],
    secondary: accent.DEFAULT,
    'secondary-darken-1': accent.dark,
    error: '#dc2626',
    success: '#059669',
    warning: '#d97706',
    info: '#2563eb',
    background: '#f5f2ea',
    surface: '#fdfcf9',
    'surface-variant': '#f6f3ec',
    'on-surface-variant': '#6b6656',
  },
  variables: {
    'border-color': '#e5e0d1',
  },
}

const pepyDark = {
  dark: true,
  colors: {
    primary: brand[500],
    'primary-darken-1': brand[600],
    'primary-lighten-1': brand[300],
    secondary: accent.DEFAULT,
    'secondary-darken-1': accent.dark,
    error: '#ef4444',
    success: '#10b981',
    warning: '#f59e0b',
    info: '#3b82f6',
    background: '#0e1411',
    surface: '#16201a',
    'surface-variant': '#1c2a22',
    'on-surface-variant': '#9db0a4',
  },
  variables: {
    'border-color': '#283a2f',
  },
}

const vuetify = createVuetify({
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
    VTextarea: { variant: 'outlined', density: 'comfortable' },
    VCard: { rounded: 'lg' },
    VChip: { rounded: 'lg' },
  },
})

export default vuetify
