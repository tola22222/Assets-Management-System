import { createRouter, createWebHistory } from 'vue-router'
import Login from '../pages/Login.vue'
import AppLayout from '../layouts/AppLayout.vue'
import Dashboard from '../pages/Dashboard.vue'
import AssetsIndex from '../pages/assets/Index.vue'
import AssetsImport from '../pages/assets/Import.vue'
import CategoriesIndex from '../pages/categories/Index.vue'
import LocationsIndex from '../pages/locations/Index.vue'
import AssetStocksIndex from '../pages/asset-stocks/Index.vue'
import AssetMovementsIndex from '../pages/asset-movements/Index.vue'
import AssetAssignmentsIndex from '../pages/asset-assignments/Index.vue'
import AssetTransfersIndex from '../pages/asset-transfers/Index.vue'
import AssetReturnsIndex from '../pages/asset-returns/Index.vue'
import AssetVerificationsIndex from '../pages/asset-verifications/Index.vue'
import AssetDisposalsIndex from '../pages/asset-disposals/Index.vue'
import ProgramsIndex from '../pages/programs/Index.vue'
import StaffIndex from '../pages/staff/Index.vue'
import SuppliersIndex from '../pages/suppliers/Index.vue'
import UsersIndex from '../pages/users/Index.vue'
import SettingsIndex from '../pages/settings/Index.vue'
import ActivityLogsIndex from '../pages/activity-logs/Index.vue'
import ReportsIndex from '../pages/reports/Index.vue'
import QrScanIndex from '../pages/qr-scan/Index.vue'
import SearchIndex from '../pages/search/Index.vue'
import NotificationsIndex from '../pages/notifications/Index.vue'
import ProfileIndex from '../pages/profile/Index.vue'

const routes = [
  { path: '/login', name: 'login', component: Login, meta: { guest: true } },
  {
    path: '/',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: Dashboard },
      { path: 'assets', name: 'assets', component: AssetsIndex },
      { path: 'assets/add', name: 'assets-add', redirect: { name: 'assets', query: { add: '1' } } },
      { path: 'assets/import', name: 'assets-import', component: AssetsImport },
      { path: 'categories', name: 'categories', component: CategoriesIndex },
      { path: 'locations', name: 'locations', component: LocationsIndex },
      { path: 'asset-stocks', name: 'asset-stocks', component: AssetStocksIndex },
      { path: 'asset-movements', name: 'asset-movements', component: AssetMovementsIndex },
      { path: 'asset-assignments', name: 'asset-assignments', component: AssetAssignmentsIndex },
      { path: 'asset-transfers', name: 'asset-transfers', component: AssetTransfersIndex },
      { path: 'asset-returns', name: 'asset-returns', component: AssetReturnsIndex },
      { path: 'asset-verifications', name: 'asset-verifications', component: AssetVerificationsIndex },
      { path: 'asset-disposals', name: 'asset-disposals', component: AssetDisposalsIndex },
      { path: 'programs', name: 'programs', component: ProgramsIndex },
      { path: 'staff', name: 'staff', component: StaffIndex },
      { path: 'suppliers', name: 'suppliers', component: SuppliersIndex },
      { path: 'users', name: 'users', component: UsersIndex, meta: { adminOnly: true } },
      { path: 'settings', name: 'settings', component: SettingsIndex, meta: { adminOnly: true } },
      { path: 'activity-logs', name: 'activity-logs', component: ActivityLogsIndex, meta: { adminOnly: true } },
      { path: 'reports', name: 'reports', component: ReportsIndex },
      { path: 'qr-scan', name: 'qr-scan', component: QrScanIndex },
      { path: 'search', name: 'search', component: SearchIndex },
      { path: 'notifications', name: 'notifications', component: NotificationsIndex },
      { path: 'profile', name: 'profile', component: ProfileIndex },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach((to) => {
  const isAuthenticated = !!localStorage.getItem('token')

  if (to.meta.requiresAuth && !isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.guest && isAuthenticated) {
    return { name: 'dashboard' }
  }

  if (to.meta.adminOnly) {
    const user = JSON.parse(localStorage.getItem('user') || 'null')
    if (user?.role !== 'operations_hr_manager') {
      return { name: 'dashboard' }
    }
  }
})

export default router
