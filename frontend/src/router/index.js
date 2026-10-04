import { hasCustomGrant } from '../composables/usePermissions'
import { createRouter, createWebHistory } from 'vue-router'
import Login from '../pages/Login.vue'
import Dashboard from '../pages/Dashboard.vue'
import AssetsIndex from '../pages/assets/Index.vue'
import CategoriesIndex from '../pages/categories/Index.vue'
import LocationsIndex from '../pages/locations/Index.vue'
import AssetTransfersIndex from '../pages/asset-transfers/Index.vue'
import AssetVerificationsIndex from '../pages/asset-verifications/Index.vue'
import AssetDisposalsIndex from '../pages/asset-disposals/Index.vue'
import StockIndex from '../pages/stock/Index.vue'
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
  // Import is a dialog on Add Asset now; the old address opens it there.
  { path: '/assets/import', redirect: { path: '/assets', query: { import: 1 } } },
  { path: '/categories', name: 'categories', component: CategoriesIndex, meta: { requiresAuth: true } },
  { path: '/locations', name: 'locations', component: LocationsIndex, meta: { requiresAuth: true } },
  // There is no separate Assignment screen: assigning happens through a
  // transfer that names a staff member or program. Old links and "assigned to
  // you" notifications land on Transfers.
  { path: '/asset-assignments', redirect: '/asset-transfers' },
  { path: '/asset-transfers', name: 'asset-transfers', component: AssetTransfersIndex, meta: { requiresAuth: true } },
  { path: '/asset-verifications', name: 'asset-verifications', component: AssetVerificationsIndex, meta: { requiresAuth: true } },
  { path: '/asset-disposals', name: 'asset-disposals', component: AssetDisposalsIndex, meta: { requiresAuth: true } },
  { path: '/stock', name: 'stock', component: StockIndex, meta: { requiresAuth: true } },
  { path: '/programs', name: 'programs', component: ProgramsIndex, meta: { requiresAuth: true } },
  { path: '/staff', name: 'staff', component: StaffIndex, meta: { requiresAuth: true } },
  { path: '/suppliers', name: 'suppliers', component: SuppliersIndex, meta: { requiresAuth: true } },
  { path: '/users', name: 'users', component: UsersIndex, meta: { requiresAuth: true, adminOnly: true, hrOnly: true, module: 'users' } },
  // Staff reach it too — their Settings page is the Appearance tab, applied to
  // their own browser only.
  { path: '/settings', name: 'settings', component: SettingsIndex, meta: { requiresAuth: true, adminOnly: true, staffToo: true, module: 'settings' } },
  { path: '/activity-logs', name: 'activity-logs', component: ActivityLogsIndex, meta: { requiresAuth: true, adminOnly: true, hrOnly: true, module: 'activity-logs' } },
  { path: '/reports', name: 'reports', component: ReportsIndex, meta: { requiresAuth: true, notStaff: true, module: 'reports' } },
  // :code is what a printed QR tag's public page links to (/app/qr-scan/PEY-SR-FAF-0928).
  { path: '/qr-scan/:code?', name: 'qr-scan', component: QrScanIndex, meta: { requiresAuth: true } },
  { path: '/search', name: 'search', component: SearchIndex, meta: { requiresAuth: true } },
  { path: '/notifications', name: 'notifications', component: NotificationsIndex, meta: { requiresAuth: true } },
  { path: '/profile', name: 'profile', component: ProfileIndex, meta: { requiresAuth: true } },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

/**
 * The public asset page (/asset/{code}, a Blade page outside this SPA) sends
 * people to /login?return=/asset/{code} so they come back to it signed in.
 * It sits outside the router's /app base, so it needs a real navigation rather
 * than router.push — and because that leaves the app, the target is held to
 * exactly that one shape: never a host, never another path.
 */
export function assetReturnUrl(query) {
  const match = typeof query.return === 'string' && query.return.match(/^\/asset\/([^/\\?#]+)$/)
  return match ? `/asset/${encodeURIComponent(match[1])}` : null
}

router.beforeEach(async (to) => {
  const isAuthenticated = !!localStorage.getItem('token')

  if (to.meta.requiresAuth && !isAuthenticated) {
    // Remember where they were headed: someone who scanned a QR tag must land
    // back on that asset after signing in, not on the dashboard.
    return { name: 'login', query: to.fullPath === '/' ? {} : { redirect: to.fullPath } }
  }

  if (to.meta.guest && isAuthenticated) {
    // Already signed in (e.g. the scan page's link was opened twice): go
    // straight back to the asset instead of stranding them on the dashboard.
    const back = assetReturnUrl(to.query)
    if (back) {
      window.location.assign(back)
      return false
    }
    return { name: 'dashboard' }
  }

  const signedIn = JSON.parse(localStorage.getItem('user') || 'null')
  // A page the base role can't open still opens when a custom role (Roles &
  // Permissions) grants its module — the same rule as the server's role:
  // guard, so the page loads only when its data will.
  const customGrant = () => hasCustomGrant(to.meta.module, to.meta.ability || 'view')

  if (to.meta.adminOnly && !(to.meta.staffToo && signedIn?.role === 'staff')) {
    const user = signedIn
    // HR or the Accountant (same access; the Accountant's Settings page shows
    // Appearance only).
    const roleAllowed = ['operations_hr_manager', 'finance_manager'].includes(user?.role)
      // Administration pages the Accountant doesn't get (Users, Activity Logs).
      && !(to.meta.hrOnly && user?.role !== 'operations_hr_manager')
    if (!roleAllowed && !(await customGrant())) {
      return { name: 'dashboard' }
    }
  }

  if (to.meta.notStaff && signedIn?.role === 'staff' && !(await customGrant())) {
    return { name: 'dashboard' }
  }
})

export default router
