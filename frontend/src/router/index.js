import { createRouter, createWebHistory } from 'vue-router'
import Login from '../pages/Login.vue'
import Dashboard from '../pages/Dashboard.vue'
import AssetsIndex from '../pages/assets/Index.vue'
import CategoriesIndex from '../pages/categories/Index.vue'
import LocationsIndex from '../pages/locations/Index.vue'
import AssetStocksIndex from '../pages/asset-stocks/Index.vue'
import AssetAssignmentsIndex from '../pages/asset-assignments/Index.vue'
import AssetTransfersIndex from '../pages/asset-transfers/Index.vue'
import AssetReturnsIndex from '../pages/asset-returns/Index.vue'
import AssetVerificationsIndex from '../pages/asset-verifications/Index.vue'
import AssetDisposalsIndex from '../pages/asset-disposals/Index.vue'

const routes = [
  { path: '/login', name: 'login', component: Login, meta: { guest: true } },
  { path: '/', name: 'dashboard', component: Dashboard, meta: { requiresAuth: true } },
  { path: '/assets', name: 'assets', component: AssetsIndex, meta: { requiresAuth: true } },
  { path: '/categories', name: 'categories', component: CategoriesIndex, meta: { requiresAuth: true } },
  { path: '/locations', name: 'locations', component: LocationsIndex, meta: { requiresAuth: true } },
  { path: '/asset-stocks', name: 'asset-stocks', component: AssetStocksIndex, meta: { requiresAuth: true } },
  { path: '/asset-assignments', name: 'asset-assignments', component: AssetAssignmentsIndex, meta: { requiresAuth: true } },
  { path: '/asset-transfers', name: 'asset-transfers', component: AssetTransfersIndex, meta: { requiresAuth: true } },
  { path: '/asset-returns', name: 'asset-returns', component: AssetReturnsIndex, meta: { requiresAuth: true } },
  { path: '/asset-verifications', name: 'asset-verifications', component: AssetVerificationsIndex, meta: { requiresAuth: true } },
  { path: '/asset-disposals', name: 'asset-disposals', component: AssetDisposalsIndex, meta: { requiresAuth: true } },
]

const router = createRouter({
  history: createWebHistory(),
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
})

export default router
