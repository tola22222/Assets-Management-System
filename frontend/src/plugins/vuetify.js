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
  // Vuetify's built-in 'light'/'dark' themes ship a full variables block
  // (border-opacity, *-emphasis-opacity, hover/focus/selected/etc. opacity) —
  // defining custom `themes` replaces those defaults rather than merging with
  // them, so every one of these has to be restated or things like table row
  // gridlines (rgba(var(--v-border-color), var(--v-border-opacity))) silently
  // resolve to an invalid color and vanish. Values below match Vuetify's
  // built-in 'light' theme, only 'border-color' is customized.
  variables: {
    'border-color': '#e5e0d1',
    'border-opacity': 0.12,
    'high-emphasis-opacity': 0.87,
    'medium-emphasis-opacity': 0.60,
    'disabled-opacity': 0.38,
    'idle-opacity': 0.04,
    'hover-opacity': 0.04,
    'focus-opacity': 0.12,
    'selected-opacity': 0.08,
    'activated-opacity': 0.12,
    'pressed-opacity': 0.12,
    'dragged-opacity': 0.08,
    'theme-kbd': '#EEEEEE',
    'theme-on-kbd': '#000000',
    'theme-code': '#F5F5F5',
    'theme-on-code': '#000000',
    'theme-on-dark': '#FFF',
    'theme-on-light': '#000',
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
  // See pepyLight above — values here match Vuetify's built-in 'dark' theme,
  // only 'border-color' is customized.
  variables: {
    'border-color': '#283a2f',
    'border-opacity': 0.12,
    'high-emphasis-opacity': 1,
    'medium-emphasis-opacity': 0.70,
    'disabled-opacity': 0.50,
    'idle-opacity': 0.10,
    'hover-opacity': 0.04,
    'focus-opacity': 0.12,
    'selected-opacity': 0.08,
    'activated-opacity': 0.12,
    'pressed-opacity': 0.16,
    'dragged-opacity': 0.08,
    'theme-kbd': '#424242',
    'theme-on-kbd': '#FFFFFF',
    'theme-code': '#343434',
    'theme-on-code': '#CCCCCC',
    'theme-on-dark': '#FFF',
    'theme-on-light': '#000',
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
