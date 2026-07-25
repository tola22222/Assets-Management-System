import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'

// mdi- icon names for the sidebar (was raw SVG path data pre-Vuetify)
const ICONS = {
  home: 'mdi-view-dashboard-outline',
  qr: 'mdi-qrcode-scan',
  assets: 'mdi-package-variant-closed',
  workflow: 'mdi-clipboard-flow-outline',
  clipboard: 'mdi-clipboard-text-outline',
  uturn: 'mdi-arrow-u-left-top',
  swap: 'mdi-swap-horizontal',
  shield: 'mdi-shield-check-outline',
  users: 'mdi-account-group-outline',
  cog: 'mdi-cog-outline',
  setup: 'mdi-tune-variant',
  chart: 'mdi-chart-bar',
  logout: 'mdi-logout',
}

// Shared nav-structure + breadcrumb logic for AppNavigationDrawer.vue and
// AppHeader.vue, so the sidebar's groups/items and the header's breadcrumb
// trail can never drift out of sync with each other.
export function useAppNav() {
  const { t } = useI18n()
  const auth = useAuthStore()
  const route = useRoute()

  const isAdmin = computed(() => auth.user?.role === 'operations_hr_manager')

  const topLinks = computed(() => [
    { to: '/', label: t('nav.dashboard'), icon: ICONS.home, exact: true },
    { to: '/qr-scan', label: t('nav.qr_scanner'), icon: ICONS.qr },
  ])

  const myAssets = computed(() => [
    { to: '/asset-assignments', label: t('nav.my_assets'), icon: ICONS.clipboard },
    { to: '/asset-returns', label: t('nav.return_requests'), icon: ICONS.uturn },
    { to: '/asset-transfers', label: t('nav.transfer_requests'), icon: ICONS.swap },
    { to: '/asset-verifications', label: t('nav.verification'), icon: ICONS.shield },
  ])

  // Split from a single "Inventory" group: core asset/stock data here...
  const assetsGroup = computed(() => ({
    key: 'assets', title: t('nav.asset_management'), icon: ICONS.assets,
    items: [
      { to: '/assets', label: t('nav.asset_register') },
      { to: '/asset-stocks', label: t('nav.receive_assets') },
      { to: '/asset-movements', label: t('nav.stock_movements') },
    ],
  }))
  // ...and the approve/reject/complete workflow entities here, so each group
  // stays scannable instead of one 8-item "Inventory" catch-all.
  const workflowsGroup = computed(() => ({
    key: 'workflows', title: t('nav.workflows'), icon: ICONS.workflow,
    items: [
      { to: '/asset-assignments', label: t('nav.assignments') },
      { to: '/asset-transfers', label: t('nav.transfers') },
      { to: '/asset-returns', label: t('nav.returns') },
      { to: '/asset-verifications', label: t('nav.verification') },
      { to: '/asset-disposals', label: t('nav.disposals') },
    ],
  }))
  const peopleGroup = computed(() => ({
    key: 'people', title: t('nav.people_programs'), icon: ICONS.users,
    items: [
      { to: '/staff', label: t('nav.staff_directory') },
      { to: '/programs', label: t('nav.programs') },
    ],
  }))
  const systemSetupGroup = computed(() => ({
    key: 'settings', title: t('nav.system_setup'), icon: ICONS.setup,
    items: [
      { to: '/categories', label: t('nav.categories') },
      { to: '/locations', label: t('nav.locations') },
      { to: '/suppliers', label: t('nav.suppliers') },
    ],
  }))
  const settingGroup = computed(() => ({
    key: 'setting', title: t('nav.setting'), icon: ICONS.cog,
    items: [
      { to: '/users', label: t('nav.user_management') },
      { to: '/settings', label: t('nav.system_settings') },
      { to: '/activity-logs', label: t('nav.activity_logs') },
    ],
  }))

  // Groups rendered in the scrollable area (order matches the old sidebar)
  const mainGroups = computed(() =>
    isAdmin.value
      ? [assetsGroup.value, workflowsGroup.value, peopleGroup.value, systemSetupGroup.value]
      : [peopleGroup.value, systemSetupGroup.value]
  )

  function isActive(to) {
    return route.path === to || route.path.startsWith(to + '/')
  }

  const allGroups = computed(() => [assetsGroup.value, workflowsGroup.value, peopleGroup.value, systemSetupGroup.value, settingGroup.value])
  const activeGroupKey = computed(() => {
    for (const g of allGroups.value) {
      if (g.items.some((it) => isActive(it.to))) return g.key
    }
    return ''
  })

  // Breadcrumb trail, built from the same group/item definitions that drive
  // the sidebar so the two can never drift apart.
  const breadcrumbMap = computed(() => {
    const map = {}
    const add = (to, page, section = null) => { map[to] = { section, page } }

    topLinks.value.forEach((item) => add(item.to, item.label))
    add('/reports', t('nav.reports'))
    add('/search', t('nav.search'))
    add('/profile', 'My Profile')
    add('/notifications', 'Notifications')
    add('/assets/import', t('import.title'), t('nav.asset_management'))

    myAssets.value.forEach((item) => add(item.to, item.label, t('nav.my_assets')))
    ;[assetsGroup.value, workflowsGroup.value, peopleGroup.value, systemSetupGroup.value, settingGroup.value].forEach((group) => {
      group.items.forEach((item) => add(item.to, item.label, group.title))
    })

    return map
  })

  const breadcrumb = computed(() => {
    if (breadcrumbMap.value[route.path]) return breadcrumbMap.value[route.path]
    const match = Object.entries(breadcrumbMap.value).find(([to]) => to !== '/' && route.path.startsWith(to + '/'))
    return match ? match[1] : null
  })

  return {
    ICONS,
    isAdmin,
    topLinks,
    myAssets,
    mainGroups,
    settingGroup,
    activeGroupKey,
    breadcrumb,
  }
}
