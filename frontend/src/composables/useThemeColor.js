import { ref } from 'vue'
import vuetify from '../plugins/vuetify'

// Converts one picked hex into Vuetify's live theme `primary` color (read by
// every v-* component), mutated directly on the vuetify instance — no
// re-render/rebuild needed. This is what makes the Settings "Theme Color"
// picker work app-wide.
const themeColor = ref(localStorage.getItem('themeColor') || '#1F4B43')

// Lightness stops used to derive Vuetify's primary/darken-1/lighten-1 shades.
const SHADE_LIGHTNESS = { 300: 60, 500: 34, 600: 26, 700: 20, 800: 14 }

function hexToHsl(hex) {
  const r = parseInt(hex.slice(1, 3), 16) / 255
  const g = parseInt(hex.slice(3, 5), 16) / 255
  const b = parseInt(hex.slice(5, 7), 16) / 255
  const max = Math.max(r, g, b), min = Math.min(r, g, b)
  let h = 0
  const l = (max + min) / 2
  const d = max - min
  const s = d === 0 ? 0 : d / (1 - Math.abs(2 * l - 1))
  if (d !== 0) {
    switch (max) {
      case r: h = ((g - b) / d) % 6; break
      case g: h = (b - r) / d + 2; break
      default: h = (r - g) / d + 4
    }
    h *= 60
    if (h < 0) h += 360
  }
  return [h, s * 100, l * 100]
}

function hslToHex(h, s, l) {
  s /= 100
  l /= 100
  const k = (n) => (n + h / 30) % 12
  const a = s * Math.min(l, 1 - l)
  const f = (n) => l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)))
  const toHex = (x) => Math.round(255 * x).toString(16).padStart(2, '0')
  return `#${toHex(f(0))}${toHex(f(8))}${toHex(f(4))}`
}

function applyThemeColor(hex) {
  if (!hex || !/^#[0-9a-fA-F]{6}$/.test(hex)) return
  themeColor.value = hex
  try {
    localStorage.setItem('themeColor', hex)
  } catch (e) {
    // ignore storage failures (private mode, etc.)
  }

  const [h, s] = hexToHsl(hex)

  // Same hue/saturation curve, applied to Vuetify's live theme colors —
  // lightness stops chosen to match the brand-700/800/500/600/300 shades
  // pepyLight/pepyDark map `primary`/`primary-darken-1`/`primary-lighten-1` to.
  const light = vuetify.theme.themes.value.pepyLight.colors
  light.primary = hslToHex(h, s, SHADE_LIGHTNESS[700])
  light['primary-darken-1'] = hslToHex(h, s, SHADE_LIGHTNESS[800])
  light['primary-lighten-1'] = hslToHex(h, s, SHADE_LIGHTNESS[500])

  const dark = vuetify.theme.themes.value.pepyDark.colors
  dark.primary = hslToHex(h, s, SHADE_LIGHTNESS[500])
  dark['primary-darken-1'] = hslToHex(h, s, SHADE_LIGHTNESS[600])
  dark['primary-lighten-1'] = hslToHex(h, s, SHADE_LIGHTNESS[300])
}

// Apply the persisted color immediately on module load (before mount),
// mirroring the dark-mode no-flash script in index.html.
if (typeof document !== 'undefined' && localStorage.getItem('themeColor')) {
  applyThemeColor(themeColor.value)
}

export function useThemeColor() {
  return { themeColor, applyThemeColor }
}
