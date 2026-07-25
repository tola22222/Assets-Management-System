import { computed } from 'vue'
import { useThemeColor } from './useThemeColor'

// Derives Naive UI's GlobalThemeOverrides from the same brand hex the rest of
// the app already uses (Settings > Theme Color, applied to --color-brand-* by
// useThemeColor). Keeps Naive UI components in sync with the live brand color
// instead of hardcoding a second, divergent green.
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

export function useNaiveTheme() {
  const { themeColor } = useThemeColor()

  const themeOverrides = computed(() => {
    const [h, s] = hexToHsl(themeColor.value)
    const primaryColor = themeColor.value
    const hover = hslToHex(h, s, 40)
    const pressed = hslToHex(h, s, 16)
    const suppl = hslToHex(h, s, 34)

    const common = {
      primaryColor,
      primaryColorHover: hover,
      primaryColorPressed: pressed,
      primaryColorSuppl: suppl,
      borderRadius: '10px',
      fontFamily: 'var(--font-sans)',
    }

    return {
      common,
      DataTable: {
        thPaddingMedium: '12px 16px',
        tdPaddingMedium: '12px 16px',
      },
      Pagination: {
        // Extra breathing room between the prev/page-number/next buttons and
        // before the "10 / page" size-picker — applies to every n-data-table's
        // built-in pagination bar app-wide (this composable is used once in
        // App.vue's NConfigProvider). Set for all three sizes since n-data-table
        // doesn't always render "medium".
        itemMarginSmall: '0 0 0 12px',
        itemMarginMedium: '0 0 0 12px',
        itemMarginLarge: '0 0 0 12px',
        selectMarginSmall: '0 0 0 20px',
        selectMarginMedium: '0 0 0 20px',
        selectMarginLarge: '0 0 0 20px',
      },
    }
  })

  return { themeOverrides }
}
