import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'

// mdi- icon names for the sidebar
const ICONS = {
  home: 'mdi-view-dashboard-outline',
  qr: 'mdi-qrcode-scan',
  assets: 'mdi-package-variant-closed',
  addAsset: 'mdi-plus-box-outline',
  swap: 'mdi-swap-horizontal',
  location: 'mdi-map-marker-outline',
  maintenance: 'mdi-tools',
  shield: 'mdi-shield-check-outline',
  chart: 'mdi-chart-bar',
  analytics: 'mdi-chart-donut',
  cog: 'mdi-cog-outline',
  logout: 'mdi-logout',
  profile: 'mdi-account-outline',
}

// Shared nav-structure + breadcrumb logic for AppNavigationDrawer.vue and
// AppHeader.vue, so the sidebar's items and the header's breadcrumb trail
// can never drift out of sync with each other.
export function useAppNav() {
  const { t } = useI18n()
  const auth = useAuthStore()
  const route = useRoute()

  const isAdmin = computed(() => auth.user?.role === 'operations_hr_manager')

  // Fixed OVERVIEW / MANAGE / INSIGHT / SYSTEM sidebar structure. Pages not
  // listed here (categories, workflow self-service pages, people/programs,
  // users, activity logs, qr-scan, search, notifications, profile) keep
  // working via direct link/route — they're just not in the main nav.
  // Maintenance and Analytics are added once their pages exist.
  const navSections = computed(() => [
    {
      key: 'overview',
      title: t('nav.overview'),
      items: [
        { to: '/', label: t('nav.dashboard'), icon: ICONS.home, exact: true },
      ],
    },
    {
      key: 'manage',
      title: t('nav.manage'),
      items: [
        { to: '/assets', label: t('nav.asset_register'), icon: ICONS.assets },
        { to: '/assets/add', label: t('nav.add_asset'), icon: ICONS.addAsset },
        { to: '/asset-movements', label: t('nav.stock_motion'), icon: ICONS.swap },
        { to: '/locations', label: t('nav.locations'), icon: ICONS.location },
        { to: '/maintenance', label: t('nav.maintenance'), icon: ICONS.maintenance },
        { to: '/asset-verifications', label: t('nav.verification'), icon: ICONS.shield },
      ],
    },
    {
      key: 'insight',
      title: t('nav.insight'),
      items: [
        { to: '/reports', label: t('nav.reports'), icon: ICONS.chart },
        { to: '/analytics', label: t('nav.analytics'), icon: ICONS.analytics },
      ],
    },
    {
      key: 'system',
      title: t('nav.system'),
      items: [
        { to: '/settings', label: t('nav.system_settings'), icon: ICONS.cog },
      ],
    },
  ])

  function isActive(to) {
    return route.path === to || route.path.startsWith(to + '/')
  }

  const activeSectionKey = computed(() => {
    for (const s of navSections.value) {
      if (s.items.some((it) => isActive(it.to))) return s.key
    }
    return ''
  })

  // Breadcrumb trail. Includes both the visible nav items and every route
  // that no longer has a sidebar entry, so breadcrumbs still resolve when
  // one of those is reached by direct link or an in-page action.
  const breadcrumbMap = computed(() => {
    const map = {}
    const add = (to, page, section = null) => { map[to] = { section, page } }

    navSections.value.forEach((section) => {
      section.items.forEach((item) => add(item.to, item.label, section.title))
    })

    add('/assets/import', t('import.title'), t('nav.manage'))
    add('/qr-scan', t('nav.qr_scanner'))
    add('/search', t('nav.search'))
    add('/notifications', t('notifications.title'))
    add('/profile', t('header.profile'))
    add('/categories', t('nav.categories'))
    add('/programs', t('nav.programs'))
    add('/staff', t('nav.staff_directory'))
    add('/suppliers', t('nav.suppliers'))
    add('/users', t('nav.user_management'))
    add('/activity-logs', t('nav.activity_logs'))
    add('/asset-assignments', t('nav.assignments'))
    add('/asset-transfers', t('nav.transfers'))
    add('/asset-returns', t('nav.returns'))
    add('/asset-disposals', t('nav.disposals'))

    return map
  })

  const breadcrumb = computed(() => {
    if (route.path === '/') return null // Dashboard is the breadcrumb's own home crumb — nothing to add
    if (breadcrumbMap.value[route.path]) return breadcrumbMap.value[route.path]
    const match = Object.entries(breadcrumbMap.value).find(([to]) => to !== '/' && route.path.startsWith(to + '/'))
    return match ? match[1] : null
  })

  return {
    ICONS,
    isAdmin,
    navSections,
    activeSectionKey,
    breadcrumb,
  }
}
