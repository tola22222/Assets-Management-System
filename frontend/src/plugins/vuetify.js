import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'

// PEPY brand palette v2 ("forest & gold" style guide). Primary is overwritten
// at runtime by useThemeColor.js's applyThemeColor() when the user picks a
// custom brand color in Settings — these are just the shipped defaults.
const brand = {
  forest: '#1F4B43',
  forestDeep: '#163832',
  forestMid: '#2E6358',
  gold: '#C79A46',
  goldDark: '#AE8C43',
  terracotta: '#B5573D',
  terracottaSoft: '#C27F68',
}

const pepyLight = {
  dark: false,
  colors: {
    primary: brand.forest,
    'primary-darken-1': brand.forestDeep,
    'primary-lighten-1': brand.forestMid,
    secondary: brand.gold,
    'secondary-darken-1': brand.goldDark,
    error: brand.terracotta,
    success: '#2E6358',
    warning: '#C27F68',
    info: '#2563eb',
    background: '#F6F1E6',
    surface: '#FBF8F1',
    'surface-variant': '#F3EEE1',
    'on-surface-variant': '#616A62',
    'terracotta-soft': brand.terracottaSoft,
    tan: '#C9BB98',
    'tan-dark': '#9B8A62',
    ink: '#1C231F',
    'ink-2': '#303632',
    muted: '#616A62',
    'gold-tint': '#F3E0C9',
    'mint-tint': '#CFE6DB',
  },
  // Vuetify's built-in 'light'/'dark' themes ship a full variables block
  // (border-opacity, *-emphasis-opacity, hover/focus/selected/etc. opacity) —
  // defining custom `themes` replaces those defaults rather than merging with
  // them, so every one of these has to be restated or things like table row
  // gridlines (rgba(var(--v-border-color), var(--v-border-opacity))) silently
  // resolve to an invalid color and vanish. Values below match Vuetify's
  // built-in 'light' theme, only 'border-color' is customized.
  variables: {
    'border-color': '#DDD5C3',
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
    primary: brand.forestMid,
    'primary-darken-1': brand.forest,
    'primary-lighten-1': '#7fa992',
    secondary: brand.gold,
    'secondary-darken-1': brand.goldDark,
    error: brand.terracottaSoft,
    success: '#10b981',
    warning: '#f59e0b',
    info: '#3b82f6',
    background: '#0e1411',
    surface: '#16201a',
    'surface-variant': '#1c2a22',
    'on-surface-variant': '#9db0a4',
    'terracotta-soft': brand.terracottaSoft,
    tan: '#8a7a5c',
    'tan-dark': '#6b5d45',
    'gold-tint': '#4a3f26',
    'mint-tint': '#1f3a30',
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
    VTextField: { variant: 'outlined', density: 'comfortable', bgColor: 'background' },
    VSelect: { variant: 'outlined', density: 'comfortable', bgColor: 'background' },
    VTextarea: { variant: 'outlined', density: 'comfortable', bgColor: 'background' },
    VCard: { rounded: 'lg' },
    VChip: { rounded: 'pill' },
  },
})

export default vuetify
