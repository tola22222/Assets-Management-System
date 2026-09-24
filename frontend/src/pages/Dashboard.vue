<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../api/http'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import AppLayout from '../layouts/AppLayout.vue'
import StatCard from '../components/ui/StatCard.vue'
import DonutChart from '../components/ui/DonutChart.vue'
import TrendChart from '../components/ui/TrendChart.vue'
import NeedsAttentionList from '../components/ui/NeedsAttentionList.vue'
import LocationPillCards from '../components/ui/LocationPillCards.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'

const { t } = useI18n()
const auth = useAuthStore()
const toast = useToastStore()
const isStaff = computed(() => (stats.value ? Array.isArray(stats.value.my_assignments) : auth.user?.role === 'staff'))
const isOpm = computed(() => auth.user?.role === 'operations_hr_manager')
const stats = ref(null)
const loading = ref(true)
const error = ref('')

const trendPeriod = ref('month')
const trendData = ref([])
const trendLoading = ref(false)

// The chart keeps itself current by re-polling: this backend has no
// broadcasting set up (BROADCAST_CONNECTION=log, and there is no Echo client in
// the SPA), so a socket push would mean standing up Reverb or Pusher first.
// Registrations are a low-frequency event, so half a minute is frequent enough
// to feel live without hammering the endpoint.
const REFRESH_MS = 30000
const lastUpdated = ref(null)
let refreshTimer = null

// `silent` is what separates a background poll from a load the user asked for:
// a poll must not blank the chart behind a spinner, and must not raise a toast
// every 30s if the network is down — it keeps the last good data on screen and
// tries again on the next tick.
async function loadTrend({ silent = false } = {}) {
  if (!silent) trendLoading.value = true
  try {
    const { data } = await http.get('/dashboard/by-period', { params: { period: trendPeriod.value } })
    trendData.value = data.data
    lastUpdated.value = new Date()
  } catch (e) {
    if (silent) return
    // A failed load left the chart showing "no data", which reads as "nothing
    // was ever registered" rather than "this request failed".
    trendData.value = []
    toast.error(errorMessage(e, t('dashboard.trend_failed')))
  } finally {
    if (!silent) trendLoading.value = false
  }
}

function stopAutoRefresh() {
  if (refreshTimer) clearInterval(refreshTimer)
  refreshTimer = null
}

function startAutoRefresh() {
  stopAutoRefresh()
  refreshTimer = setInterval(() => {
    // A backgrounded tab does not need fresh bars; it catches up the moment it
    // is looked at again (see onVisibility).
    if (document.visibilityState === 'visible') loadTrend({ silent: true })
  }, REFRESH_MS)
}

function onVisibility() {
  if (document.visibilityState === 'visible') loadTrend({ silent: true })
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

watch(trendPeriod, () => loadTrend())

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
    startAutoRefresh()
    document.addEventListener('visibilitychange', onVisibility)
  }
})

onBeforeUnmount(() => {
  stopAutoRefresh()
  document.removeEventListener('visibilitychange', onVisibility)
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
        <RouterLink v-if="isOpm" :to="{ path: '/assets', query: { create: 1 } }" class="btn-primary flex-shrink-0 mt-1 sm:mt-0">
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
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-2">
            <div>
              <div class="flex items-center gap-2.5">
                <h2 class="font-display text-lg font-bold text-fg">{{ t('dashboard.registered_over_time') }}</h2>
                <span
                  v-if="lastUpdated"
                  class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-muted"
                  :title="t('dashboard.last_updated', { time: lastUpdated.toLocaleTimeString() })"
                >
                  <span class="relative flex w-2 h-2">
                    <span class="absolute inline-flex h-full w-full rounded-full bg-brand opacity-60 animate-ping"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-brand"></span>
                  </span>
                  {{ t('dashboard.live') }}
                </span>
              </div>
              <p class="text-sm text-faint">{{ t('dashboard.registered_over_time_subtitle') }}</p>
            </div>
            <!-- Segmented period switch: a bordered pill track, the active option a
                 filled brand pill; the others lift to the card surface on hover. -->
            <div class="inline-flex items-center gap-0.5 p-1 rounded-full bg-surface-2 border border-line flex-shrink-0 self-start sm:self-auto" role="group">
              <button
                v-for="p in ['day', 'month', 'year']" :key="p"
                type="button"
                @click="trendPeriod = p"
                :aria-pressed="trendPeriod === p"
                class="min-w-[4rem] px-3.5 py-1.5 rounded-full text-xs font-semibold transition-colors duration-150
                       focus:outline-none focus-visible:ring-2 focus-visible:ring-brand/30"
                :class="trendPeriod === p ? 'bg-brand text-white shadow-sm' : 'text-muted hover:text-fg hover:bg-surface'"
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
