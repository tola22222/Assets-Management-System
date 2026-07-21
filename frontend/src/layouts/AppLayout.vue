<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import NotificationBell from '../components/ui/NotificationBell.vue'
import ThemeToggle from '../components/ui/ThemeToggle.vue'
import logoUrl from '../assets/logo/Official PEPY Logo_Green.png'

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const mobileOpen = ref(false)
const collapsed = ref(localStorage.getItem('sidebarCollapsed') === '1')

function toggleSidebar() {
  if (window.matchMedia('(min-width: 1024px)').matches) {
    collapsed.value = !collapsed.value
    localStorage.setItem('sidebarCollapsed', collapsed.value ? '1' : '0')
  } else {
    mobileOpen.value = !mobileOpen.value
  }
}

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}

const isAdmin = computed(() => auth.user?.role === 'operations_hr_manager')

const I = {
  home: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75',
  qr: 'M6.75 4.5H4.5v2.25M17.25 4.5h2.25v2.25M6.75 19.5H4.5v-2.25M17.25 19.5h2.25v-2.25M8.25 8.25h3v3h-3v-3zM3.75 12h16.5',
  assets: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
  tag: 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3zM6 6h.008v.008H6V6z',
  pin: 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z',
  swap: 'M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-9L21 7.5m0 0L16.5 3M21 7.5H7.5',
  clipboard: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z',
  uturn: 'M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3',
  shield: 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
  trash: 'M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0',
  users: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
  cap: 'M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5',
  truck: 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0H2.25',
  chart: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
  userCircle: 'M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z',
  cog: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.281zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
  setup: 'M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75',
  activity: 'M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6',
}

// Top-level (ungrouped) links
const topLinks = computed(() => [
  { to: '/', label: t('nav.dashboard'), icon: I.home, exact: true },
  { to: '/qr-scan', label: t('nav.qr_scanner'), icon: I.qr },
])

// Non-admin flat "My Assets" section (replaces the Asset Management group)
const myAssets = computed(() => [
  { to: '/asset-assignments', label: t('nav.my_assets'), icon: I.clipboard },
  { to: '/asset-returns', label: t('nav.return_requests'), icon: I.uturn },
  { to: '/asset-transfers', label: t('nav.transfer_requests'), icon: I.swap },
  { to: '/asset-verifications', label: t('nav.verification'), icon: I.shield },
])

const inventoryGroup = computed(() => ({
  key: 'inventory', title: t('nav.asset_management'), icon: I.assets,
  items: [
    { to: '/assets', label: t('nav.asset_register') },
    { to: '/asset-stocks', label: t('nav.receive_assets') },
    { to: '/asset-movements', label: t('nav.stock_movements') },
    { to: '/asset-assignments', label: t('nav.assignments') },
    { to: '/asset-transfers', label: t('nav.transfers') },
    { to: '/asset-returns', label: t('nav.returns') },
    { to: '/asset-verifications', label: t('nav.verification') },
    { to: '/asset-disposals', label: t('nav.disposals') },
  ],
}))
const peopleGroup = computed(() => ({
  key: 'people', title: t('nav.people_programs'), icon: I.users,
  items: [
    { to: '/staff', label: t('nav.staff_directory') },
    { to: '/programs', label: t('nav.programs') },
  ],
}))
const systemSetupGroup = computed(() => ({
  key: 'settings', title: t('nav.system_setup'), icon: I.setup,
  items: [
    { to: '/categories', label: t('nav.categories') },
    { to: '/locations', label: t('nav.locations') },
    { to: '/suppliers', label: t('nav.suppliers') },
  ],
}))
const settingGroup = computed(() => ({
  key: 'setting', title: t('nav.setting'), icon: I.cog,
  items: [
    { to: '/users', label: t('nav.user_management') },
    { to: '/settings', label: t('nav.system_settings') },
    { to: '/activity-logs', label: t('nav.activity_logs') },
  ],
}))

// Groups rendered in the scrollable area (order matches the old sidebar)
const mainGroups = computed(() =>
  isAdmin.value ? [inventoryGroup.value, peopleGroup.value, systemSetupGroup.value] : [peopleGroup.value, systemSetupGroup.value]
)

function isActive(to) {
  return route.path === to || route.path.startsWith(to + '/')
}

// Single-open accordion that follows the active route.
const allGroups = computed(() => [inventoryGroup.value, peopleGroup.value, systemSetupGroup.value, settingGroup.value])
const activeGroupKey = computed(() => {
  for (const g of allGroups.value) {
    if (g.items.some((it) => isActive(it.to))) return g.key
  }
  return ''
})
const openGroup = ref(activeGroupKey.value)
watch(activeGroupKey, (k) => { if (k) openGroup.value = k }, { immediate: true })
function toggleGroup(key) {
  openGroup.value = openGroup.value === key ? '' : key
}

function initials(name) {
  if (!name) return 'U'
  return name.split(' ').map((n) => n[0]).slice(0, 2).join('').toUpperCase()
}
</script>

<template>
  <div class="min-h-screen bg-canvas text-fg">
    <div v-if="mobileOpen" class="fixed inset-0 bg-black/40 z-40 lg:hidden" @click="mobileOpen = false"></div>

    <div class="flex min-h-screen">
      <!-- Sidebar -->
      <aside
        class="fixed lg:sticky top-0 z-50 h-screen flex-shrink-0 bg-brand-800 text-white flex flex-col
               border-r border-black/25 overflow-hidden transition-all duration-200 ease-in-out"
        :class="[
          mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
          collapsed ? 'lg:w-0 lg:border-0' : 'w-64',
        ]"
      >
        <div class="w-64 flex flex-col h-full">
          <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
            <div class="w-9 h-9 flex items-center justify-center flex-shrink-0">
              <img :src="logoUrl" alt="PEPY" class="w-full h-full object-contain" />
            </div>
            <div class="min-w-0 flex-1">
              <p class="font-display font-bold leading-tight truncate">{{ t('app_name') }}</p>
              <p class="text-white/45 text-xs truncate">{{ t('app_subtitle') }} · {{ t('app_location') }}</p>
            </div>
            <button class="lg:hidden text-white/60 hover:text-white p-1" @click="mobileOpen = false">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <!-- Scrollable nav -->
          <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-0.5">
            <!-- Top-level links -->
            <RouterLink
              v-for="item in topLinks"
              :key="item.to"
              :to="item.to"
              class="nav-link"
              :exact-active-class="item.exact ? 'nav-link-active' : ''"
              :active-class="item.exact ? '' : 'nav-link-active'"
              @click="mobileOpen = false"
            >
              <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" /></svg>
              <span class="truncate">{{ item.label }}</span>
            </RouterLink>

            <!-- Non-admin flat My Assets -->
            <div v-if="!isAdmin" class="pt-2">
              <p class="nav-section-label">{{ t('nav.my_assets') }}</p>
              <RouterLink
                v-for="item in myAssets"
                :key="item.to"
                :to="item.to"
                class="nav-link pl-9"
                active-class="nav-link-active"
                @click="mobileOpen = false"
              >
                <span class="truncate">{{ item.label }}</span>
              </RouterLink>
            </div>

            <!-- Accordion groups -->
            <div v-for="group in mainGroups" :key="group.key">
              <button
                class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm text-white/70 border-l-[3px] border-solid border-transparent hover:bg-white/10 hover:text-white active:bg-white/20 active:scale-[0.98] transition-[background-color,color,transform,border-color] duration-150"
                :class="openGroup === group.key ? 'bg-black/20 text-white' : ''"
                @click="toggleGroup(group.key)"
              >
                <span class="flex items-center gap-2.5 min-w-0">
                  <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="group.icon" /></svg>
                  <span class="truncate font-medium">{{ group.title }}</span>
                </span>
                <svg class="w-3.5 h-3.5 flex-shrink-0 transition-transform duration-200" :class="openGroup === group.key ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
              </button>
              <div v-show="openGroup === group.key" class="mt-0.5 space-y-0.5">
                <RouterLink
                  v-for="item in group.items"
                  :key="item.to"
                  :to="item.to"
                  class="nav-link pl-9"
                  active-class="nav-link-active"
                  @click="mobileOpen = false"
                >
                  <span class="truncate">{{ item.label }}</span>
                </RouterLink>
              </div>
            </div>

            <!-- Reports (top-level) -->
            <RouterLink to="/reports" class="nav-link" active-class="nav-link-active" @click="mobileOpen = false">
              <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="I.chart" /></svg>
              <span class="truncate">{{ t('nav.reports') }}</span>
            </RouterLink>
          </nav>

          <!-- Pinned bottom: Setting group (admin) + logout -->
          <div class="px-3 py-3 border-t border-white/10 space-y-0.5">
            <div v-if="isAdmin">
              <button
                class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm text-white/70 border-l-[3px] border-solid border-transparent hover:bg-white/10 hover:text-white active:bg-white/20 active:scale-[0.98] transition-[background-color,color,transform,border-color] duration-150"
                :class="openGroup === settingGroup.key ? 'bg-black/20 text-white' : ''"
                @click="toggleGroup(settingGroup.key)"
              >
                <span class="flex items-center gap-2.5 min-w-0">
                  <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="settingGroup.icon" /></svg>
                  <span class="truncate font-medium">{{ settingGroup.title }}</span>
                </span>
                <svg class="w-3.5 h-3.5 flex-shrink-0 transition-transform duration-200" :class="openGroup === settingGroup.key ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
              </button>
              <div v-show="openGroup === settingGroup.key" class="mt-0.5 space-y-0.5">
                <RouterLink
                  v-for="item in settingGroup.items"
                  :key="item.to"
                  :to="item.to"
                  class="nav-link pl-9"
                  active-class="nav-link-active"
                  @click="mobileOpen = false"
                >
                  <span class="truncate">{{ item.label }}</span>
                </RouterLink>
              </div>
            </div>

            <button @click="handleLogout" class="nav-link w-full text-red-200 hover:bg-red-500/20 hover:text-white">
              <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg>
              {{ t('nav.logout') }}
            </button>
          </div>
        </div>
      </aside>

      <!-- Main column -->
      <div class="flex-1 min-w-0 flex flex-col">
        <header class="sticky top-0 z-30 h-14 flex items-center gap-2 px-4 sm:px-6 bg-surface/80 backdrop-blur border-b border-line">
          <button
            class="-ml-1 w-9 h-9 rounded-xl flex items-center justify-center text-muted hover:bg-surface-2 hover:text-fg transition-colors"
            :title="collapsed ? t('nav.show_sidebar') : t('nav.hide_sidebar')"
            @click="toggleSidebar"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
          </button>

          <div class="flex-1"></div>

          <ThemeToggle />
          <NotificationBell />

          <RouterLink
            to="/profile"
            class="flex items-center gap-2.5 pl-2 sm:pl-3 sm:border-l sm:border-line rounded-xl hover:bg-surface-2 transition-colors py-1 pr-2"
          >
            <div class="w-8 h-8 rounded-full bg-brand text-white flex items-center justify-center text-xs font-bold overflow-hidden flex-shrink-0">
              <img v-if="auth.user?.photo_url" :src="auth.user.photo_url" class="w-full h-full object-cover" alt="" />
              <span v-else>{{ initials(auth.user?.name) }}</span>
            </div>
            <div class="hidden sm:block leading-tight">
              <p class="text-sm font-semibold text-fg truncate max-w-[10rem]">{{ auth.user?.name }}</p>
              <p class="text-[11px] text-faint capitalize">{{ auth.user?.role?.replace('_', ' ') }}</p>
            </div>
          </RouterLink>
        </header>

        <main class="flex-1 min-w-0">
          <slot />
        </main>
      </div>
    </div>
  </div>
</template>
