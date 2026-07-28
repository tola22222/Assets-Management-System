import { ref } from 'vue'

// Tailwind v4's @theme block compiles --color-brand-* into real CSS custom
// properties on :root, so overriding them at runtime restyles every
// bg-brand-*/text-brand-*/border-brand-* utility already in the DOM — no
// rebuild needed. This is what makes the Settings "Theme Color" picker work.
const themeColor = ref(localStorage.getItem('themeColor') || '#1f3d2e')

// Roughly matches the lightness curve of the shipped brand-50..900 scale.
const SHADE_LIGHTNESS = { 50: 95, 100: 88, 200: 76, 300: 60, 400: 45, 500: 34, 600: 26, 700: 20, 800: 14, 900: 9 }

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
  const root = document.documentElement
  Object.entries(SHADE_LIGHTNESS).forEach(([shade, l]) => {
    root.style.setProperty(`--color-brand-${shade}`, hslToHex(h, s, l))
  })
  root.style.setProperty('--color-brand', hslToHex(h, s, 20))
  root.style.setProperty('--color-brand-dark', hslToHex(h, s, 14))
  root.style.setProperty('--color-brand-light', hslToHex(h, s, 26))
}

// Apply the persisted color immediately on module load (before mount),
// mirroring the dark-mode no-flash script in index.html.
if (typeof document !== 'undefined' && localStorage.getItem('themeColor')) {
  applyThemeColor(themeColor.value)
}

export function useThemeColor() {
  return { themeColor, applyThemeColor }
}
