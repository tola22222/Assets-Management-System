<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../api/http'
import { useAuthStore } from '../stores/auth'
import AppLayout from '../layouts/AppLayout.vue'
import StatCard from '../components/ui/StatCard.vue'
import DonutChart from '../components/ui/DonutChart.vue'
import TrendChart from '../components/ui/TrendChart.vue'
import NeedsAttentionList from '../components/ui/NeedsAttentionList.vue'
import LocationPillCards from '../components/ui/LocationPillCards.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'

const { t } = useI18n()
const auth = useAuthStore()
const isStaff = computed(() => auth.user?.role === 'staff')
const stats = ref(null)
const loading = ref(true)
const error = ref('')

const trendPeriod = ref('month')
const trendData = ref([])
const trendLoading = ref(false)

const stockItems = ref([])
const stockLoading = ref(true)
async function loadStock() {
  stockLoading.value = true
  try {
    const { data } = await http.get('/stock-items')
    stockItems.value = data
  } finally {
    stockLoading.value = false
  }
}
const stockLow = computed(() => stockItems.value.filter((i) => i.status === 'low'))
const stockNormalCount = computed(() => stockItems.value.filter((i) => i.status === 'normal').length)
const stockHigh = computed(() => stockItems.value.filter((i) => i.status === 'high'))

async function loadTrend() {
  trendLoading.value = true
  try {
    const { data } = await http.get('/dashboard/by-period', { params: { period: trendPeriod.value } })
    trendData.value = data.data
  } finally {
    trendLoading.value = false
  }
}

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return t('dashboard.greeting_morning')
  if (h < 18) return t('dashboard.greeting_afternoon')
  return t('dashboard.greeting_evening')
})

function formatCurrency(value) {
  if (value >= 1000) return `$${Math.round(value / 1000)}K`
  return `$${Math.round(value || 0)}`
}

watch(trendPeriod, loadTrend)

onMounted(async () => {
  try {
    const { data } = await http.get('/dashboard')
    stats.value = data
  } catch (e) {
    error.value = t('dashboard.load_error')
  } finally {
    loading.value = false
  }

  // Trend data is an admin-only concept (mirrors the admin vs. staff dashboard split).
  if (!isStaff.value) {
    loadTrend()
    loadStock()
  }
})
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-2">
        <div>
          <h1 class="font-display text-3xl sm:text-4xl font-semibold text-fg tracking-tight">{{ greeting }}, {{ auth.user?.name?.split(' ')[0] || auth.user?.name }}</h1>
          <p class="text-muted text-sm mt-2">{{ t('dashboard.subtitle') }}</p>
        </div>
        <RouterLink to="/assets" class="btn-primary flex-shrink-0 mt-1 sm:mt-0">
          <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
          {{ t('dashboard.add_asset') }}
        </RouterLink>
      </div>

      <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="card p-5 h-24 animate-pulse"></div>
      </div>
      <div v-else-if="error" class="card p-4 text-red-600 dark:text-red-400 text-sm">{{ error }}</div>

      <template v-else-if="stats && isStaff">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <StatCard :value="stats.my_assignments.length" :label="t('dashboard.my_assignments')" />
          <StatCard :value="stats.pending_returns" :label="t('dashboard.pending_returns')" />
          <StatCard :value="stats.upcoming_verifications" :label="t('dashboard.upcoming_verifications')" />
        </div>

        <div class="card p-6">
          <h2 class="font-display text-lg font-bold text-fg">{{ t('dashboard.my_assignments') }}</h2>
          <p class="text-sm text-faint mb-6">{{ t('dashboard.my_assignments_subtitle') }}</p>
          <div v-if="stats.my_assignments.length" class="overflow-x-auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th>{{ t('common.asset') }}</th>
                  <th>{{ t('asset_assignments.qty') }}</th>
                  <th>{{ t('common.status') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="a in stats.my_assignments" :key="a.id">
                  <td class="font-medium text-fg">{{ a.asset?.name || t('common.n_a') }}</td>
                  <td>{{ a.quantity }}</td>
                  <td><StatusBadge :status="a.status" /></td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="text-sm text-faint">{{ t('dashboard.no_assignments') }}</p>
        </div>

        <div class="card p-6">
          <h2 class="font-display text-lg font-bold text-fg">{{ t('dashboard.recent_scans') }}</h2>
          <p class="text-sm text-faint mb-6">{{ t('dashboard.recent_scans_subtitle') }}</p>
          <ul v-if="stats.recent_scans.length" class="divide-y divide-border">
            <li v-for="n in stats.recent_scans" :key="n.id" class="py-3 text-sm text-fg">{{ n.message }}</li>
          </ul>
          <p v-else class="text-sm text-faint">{{ t('dashboard.no_scans') }}</p>
        </div>
      </template>

      <template v-else-if="stats">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard :value="stats.total_assets" :label="t('dashboard.total_assets')" />
          <StatCard :value="stats.total_categories" :label="t('dashboard.categories')" />
          <StatCard :value="stats.total_locations" :label="t('dashboard.locations')" />
          <StatCard
            :value="formatCurrency(stats.recorded_value)"
            :label="stats.missing_price_count ? t('dashboard.recorded_value_missing', { count: stats.missing_price_count }) : t('dashboard.recorded_value')"
            :badge="t('dashboard.priced_badge', { percent: stats.priced_percentage })"
          />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div class="card p-6">
            <h2 class="font-display text-lg font-bold text-fg">{{ t('dashboard.by_category') }}</h2>
            <p class="text-sm text-faint mb-6">{{ t('dashboard.by_category_subtitle') }}</p>
            <DonutChart
              v-if="stats.assets_by_category.length"
              :segments="stats.assets_by_category"
              :total="stats.total_assets"
            />
            <p v-else class="text-sm text-faint">{{ t('dashboard.no_assets') }}</p>
          </div>

          <div class="card p-6">
            <h2 class="font-display text-lg font-bold text-fg">{{ t('dashboard.needs_attention') }}</h2>
            <p class="text-sm text-faint mb-6">{{ t('dashboard.needs_attention_subtitle') }}</p>
            <NeedsAttentionList :items="stats.needs_attention" />
          </div>
        </div>

        <div class="card p-6">
          <h2 class="font-display text-lg font-bold text-fg">{{ t('dashboard.by_location') }}</h2>
          <p class="text-sm text-faint mb-6">{{ t('dashboard.by_location_subtitle') }}</p>
          <LocationPillCards :locations="stats.assets_by_location" />
        </div>

        <div class="card p-6">
          <div class="flex items-center justify-between mb-1">
            <h2 class="font-display text-lg font-bold text-fg">Stock</h2>
            <RouterLink to="/stock" class="text-xs font-semibold text-brand-600 dark:text-brand-300 hover:underline flex-shrink-0">View Stock →</RouterLink>
          </div>
          <p class="text-sm text-faint mb-6">Bulk consumables — toner, paper, cables, and other items with no individual tag</p>

          <div v-if="stockLoading" class="h-20 flex items-center justify-center text-sm text-faint">{{ t('common.loading') }}</div>
          <template v-else>
            <div class="grid grid-cols-3 gap-4 mb-4">
              <RouterLink to="/stock?status=low" class="rounded-xl border border-line p-4 text-center hover:bg-surface-2 transition">
                <p class="font-display text-2xl font-bold text-red-600 dark:text-red-400">{{ stockLow.length }}</p>
                <p class="text-xs text-muted mt-1">Low Stock</p>
              </RouterLink>
              <RouterLink to="/stock?status=normal" class="rounded-xl border border-line p-4 text-center hover:bg-surface-2 transition">
                <p class="font-display text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stockNormalCount }}</p>
                <p class="text-xs text-muted mt-1">Normal</p>
              </RouterLink>
              <RouterLink to="/stock?status=high" class="rounded-xl border border-line p-4 text-center hover:bg-surface-2 transition">
                <p class="font-display text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stockHigh.length }}</p>
                <p class="text-xs text-muted mt-1">High</p>
              </RouterLink>
            </div>

            <div v-if="stockLow.length" class="space-y-1">
              <RouterLink v-for="i in stockLow.slice(0, 4)" :key="i.id" to="/stock?status=low"
                class="flex items-center justify-between gap-2 px-2.5 py-2 rounded-lg hover:bg-surface-2 transition">
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-fg truncate">{{ i.name }}</p>
                  <p class="text-xs text-faint truncate">{{ i.location?.name }}</p>
                </div>
                <p class="text-sm font-bold text-red-600 dark:text-red-400 flex-shrink-0">{{ i.balance }} {{ i.unit }}</p>
              </RouterLink>
            </div>
            <p v-else class="text-sm text-faint">Nothing running low.</p>

            <p class="text-xs font-semibold text-faint uppercase tracking-wide mt-5 mb-2">Overstock</p>
            <div v-if="stockHigh.length" class="space-y-1">
              <RouterLink v-for="i in stockHigh.slice(0, 4)" :key="i.id" to="/stock?status=high"
                class="flex items-center justify-between gap-2 px-2.5 py-2 rounded-lg hover:bg-surface-2 transition">
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-fg truncate">{{ i.name }}</p>
                  <p class="text-xs text-faint truncate">{{ i.location?.name }}</p>
                </div>
                <p class="text-sm font-bold text-amber-600 dark:text-amber-400 flex-shrink-0">{{ i.balance }} {{ i.unit }}</p>
              </RouterLink>
            </div>
            <p v-else class="text-sm text-faint">Nothing over its max.</p>
          </template>
        </div>

        <div class="card p-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-2">
            <div>
              <h2 class="font-display text-lg font-bold text-fg">{{ t('dashboard.registered_over_time') }}</h2>
              <p class="text-sm text-faint">{{ t('dashboard.registered_over_time_subtitle') }}</p>
            </div>
            <div class="flex items-center gap-1 bg-surface-2 rounded-xl p-1 flex-shrink-0">
              <button
                v-for="p in ['day', 'month', 'year']" :key="p"
                @click="trendPeriod = p"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                :class="trendPeriod === p ? 'bg-brand text-white' : 'text-muted hover:text-fg'"
              >
                {{ t(`dashboard.period_${p}`) }}
              </button>
            </div>
          </div>
          <div v-if="trendLoading" class="h-56 flex items-center justify-center text-sm text-faint">{{ t('common.loading') }}</div>
          <TrendChart v-else-if="trendData.some((d) => d.count > 0)" :data="trendData" :period="trendPeriod" class="mt-4" />
          <p v-else class="text-sm text-faint py-10 text-center">{{ t('dashboard.no_trend_data') }}</p>
        </div>
      </template>
    </div>
  </AppLayout>
</template>
