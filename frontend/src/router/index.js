import { createRouter, createWebHistory } from 'vue-router'
import Login from '../pages/Login.vue'
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
  { path: '/', name: 'dashboard', component: Dashboard, meta: { requiresAuth: true } },
  { path: '/assets', name: 'assets', component: AssetsIndex, meta: { requiresAuth: true } },
  { path: '/assets/import', name: 'assets-import', component: AssetsImport, meta: { requiresAuth: true } },
  { path: '/categories', name: 'categories', component: CategoriesIndex, meta: { requiresAuth: true } },
  { path: '/locations', name: 'locations', component: LocationsIndex, meta: { requiresAuth: true } },
  { path: '/asset-stocks', name: 'asset-stocks', component: AssetStocksIndex, meta: { requiresAuth: true } },
  { path: '/asset-movements', name: 'asset-movements', component: AssetMovementsIndex, meta: { requiresAuth: true } },
  { path: '/asset-assignments', name: 'asset-assignments', component: AssetAssignmentsIndex, meta: { requiresAuth: true } },
  { path: '/asset-transfers', name: 'asset-transfers', component: AssetTransfersIndex, meta: { requiresAuth: true } },
  { path: '/asset-returns', name: 'asset-returns', component: AssetReturnsIndex, meta: { requiresAuth: true } },
  { path: '/asset-verifications', name: 'asset-verifications', component: AssetVerificationsIndex, meta: { requiresAuth: true } },
  { path: '/asset-disposals', name: 'asset-disposals', component: AssetDisposalsIndex, meta: { requiresAuth: true } },
  { path: '/programs', name: 'programs', component: ProgramsIndex, meta: { requiresAuth: true } },
  { path: '/staff', name: 'staff', component: StaffIndex, meta: { requiresAuth: true } },
  { path: '/suppliers', name: 'suppliers', component: SuppliersIndex, meta: { requiresAuth: true } },
  { path: '/users', name: 'users', component: UsersIndex, meta: { requiresAuth: true, adminOnly: true } },
  { path: '/settings', name: 'settings', component: SettingsIndex, meta: { requiresAuth: true, adminOnly: true } },
  { path: '/activity-logs', name: 'activity-logs', component: ActivityLogsIndex, meta: { requiresAuth: true, adminOnly: true } },
  { path: '/reports', name: 'reports', component: ReportsIndex, meta: { requiresAuth: true } },
  { path: '/qr-scan', name: 'qr-scan', component: QrScanIndex, meta: { requiresAuth: true } },
  { path: '/search', name: 'search', component: SearchIndex, meta: { requiresAuth: true } },
  { path: '/notifications', name: 'notifications', component: NotificationsIndex, meta: { requiresAuth: true } },
  { path: '/profile', name: 'profile', component: ProfileIndex, meta: { requiresAuth: true } },
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
