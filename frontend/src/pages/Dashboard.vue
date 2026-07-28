<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../api/http'
import { useAuthStore } from '../stores/auth'
import StatCard from '../components/ui/StatCard.vue'
import DonutChart from '../components/ui/DonutChart.vue'
import TrendChart from '../components/ui/TrendChart.vue'
import NeedsAttentionList from '../components/ui/NeedsAttentionList.vue'
import LocationPillCards from '../components/ui/LocationPillCards.vue'

const { t } = useI18n()
const auth = useAuthStore()
const isStaff = computed(() => auth.user?.role === 'staff')
const stats = ref(null)
const loading = ref(true)
const error = ref('')

const trendPeriod = ref('month')
const trendData = ref([])
const trendLoading = ref(false)

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

function relativeTime(date) {
  const seconds = Math.floor((Date.now() - new Date(date).getTime()) / 1000)
  const units = [
    ['year', 31536000], ['month', 2592000], ['day', 86400],
    ['hour', 3600], ['minute', 60],
  ]
  for (const [unit, secs] of units) {
    const value = Math.floor(seconds / secs)
    if (value >= 1) return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(-value, unit)
  }
  return t('dashboard.just_now')
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
  }
})
</script>

<template>
  <v-container fluid class="pa-0 d-flex flex-column ga-6">
      <div class="d-flex flex-column flex-sm-row align-sm-start justify-space-between ga-4">
        <div>
          <h1 class="text-h4 font-weight-bold font-display">{{ greeting }}, {{ auth.user?.name?.split(' ')[0] || auth.user?.name }}</h1>
          <p class="text-body-2 text-medium-emphasis mt-2">{{ t('dashboard.subtitle') }}</p>
        </div>
        <v-btn to="/assets" color="primary" variant="flat" prepend-icon="mdi-plus" class="flex-shrink-0">
          {{ t('dashboard.add_asset') }}
        </v-btn>
      </div>

      <v-row v-if="loading" dense>
        <v-col v-for="i in 4" :key="i" cols="12" sm="6" lg="3">
          <v-skeleton-loader type="card" height="96" />
        </v-col>
      </v-row>
      <v-alert v-else-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>

      <template v-else-if="stats && isStaff">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <StatCard :value="stats.pending_returns" :label="t('dashboard.pending_returns')" />
          <StatCard :value="stats.upcoming_verifications" :label="t('dashboard.upcoming_verifications')" />
        </div>

        <div class="card p-6">
          <h2 class="font-display text-lg font-bold text-fg">{{ t('dashboard.my_assignments') }}</h2>
          <p class="text-sm text-faint mb-6">{{ t('dashboard.my_assignments_subtitle') }}</p>
          <div v-if="stats.my_assignments.length" class="rounded-2xl border border-line divide-y divide-line">
            <div v-for="a in stats.my_assignments" :key="a.id" class="p-4 flex items-center justify-between gap-4">
              <div>
                <p class="text-sm font-medium text-fg">{{ a.asset?.name }}</p>
                <p class="text-xs text-faint mt-0.5">{{ a.asset?.asset_code }}</p>
              </div>
              <span class="text-xs text-muted capitalize">{{ a.status }}</span>
            </div>
          </div>
          <p v-else class="text-sm text-faint">{{ t('dashboard.no_assignments') }}</p>
        </div>

        <div class="card p-6">
          <h2 class="font-display text-lg font-bold text-fg">{{ t('dashboard.recent_scans') }}</h2>
          <p class="text-sm text-faint mb-6">{{ t('dashboard.recent_scans_subtitle') }}</p>
          <div v-if="stats.recent_scans.length" class="rounded-2xl border border-line divide-y divide-line">
            <div v-for="n in stats.recent_scans" :key="n.id" class="p-4">
              <p class="text-sm text-fg">{{ n.message }}</p>
              <p class="text-xs text-faint mt-0.5">{{ new Date(n.created_at).toLocaleString() }}</p>
            </div>
          </div>
          <p v-else class="text-sm text-faint">{{ t('dashboard.no_scans') }}</p>
        </div>
      </template>

      <template v-else-if="stats">
        <v-row dense>
          <v-col cols="12" sm="6" lg="3"><StatCard :value="stats.total_assets" :label="t('dashboard.total_assets')" /></v-col>
          <v-col cols="12" sm="6" lg="3"><StatCard :value="stats.total_categories" :label="t('dashboard.categories')" /></v-col>
          <v-col cols="12" sm="6" lg="3"><StatCard :value="stats.total_locations" :label="t('dashboard.locations')" /></v-col>
          <v-col cols="12" sm="6" lg="3">
            <StatCard
              :value="formatCurrency(stats.recorded_value)"
              :label="stats.missing_price_count ? t('dashboard.recorded_value_missing', { count: stats.missing_price_count }) : t('dashboard.recorded_value')"
              :badge="t('dashboard.priced_badge', { percent: stats.priced_percentage })"
            />
          </v-col>
        </v-row>

        <v-row dense>
          <v-col cols="12" lg="6">
            <v-card rounded="lg" variant="flat" border class="pa-6 h-100">
              <h2 class="text-subtitle-1 font-weight-bold">{{ t('dashboard.by_category') }}</h2>
              <p class="text-body-2 text-medium-emphasis mb-6">{{ t('dashboard.by_category_subtitle') }}</p>
              <DonutChart
                v-if="stats.assets_by_category.length"
                :segments="stats.assets_by_category"
                :total="stats.total_assets"
              />
              <p v-else class="text-body-2 text-medium-emphasis">{{ t('dashboard.no_assets') }}</p>
            </v-card>
          </v-col>

          <v-col v-if="stats.assets_by_condition" cols="12" lg="6">
            <v-card rounded="lg" variant="flat" border class="pa-6 h-100">
              <h2 class="text-subtitle-1 font-weight-bold">{{ t('dashboard.by_condition') }}</h2>
              <p class="text-body-2 text-medium-emphasis mb-6">{{ t('dashboard.by_condition_subtitle') }}</p>
              <DonutChart
                v-if="stats.assets_by_condition.length"
                :segments="stats.assets_by_condition"
                :total="stats.total_assets"
              />
              <p v-else class="text-body-2 text-medium-emphasis">{{ t('dashboard.no_assets') }}</p>
            </v-card>
          </v-col>
        </v-row>

        <v-row v-if="stats.recent_activity" dense>
          <v-col cols="12" lg="6">
            <v-card rounded="lg" variant="flat" border class="pa-6 h-100">
              <h2 class="text-subtitle-1 font-weight-bold">{{ t('dashboard.needs_attention') }}</h2>
              <p class="text-body-2 text-medium-emphasis mb-6">{{ t('dashboard.needs_attention_subtitle') }}</p>
              <NeedsAttentionList :items="stats.needs_attention" />
            </v-card>
          </v-col>

          <v-col cols="12" lg="6">
            <v-card rounded="lg" variant="flat" border class="pa-6 h-100">
              <h2 class="text-subtitle-1 font-weight-bold">{{ t('dashboard.recent_activity') }}</h2>
              <p class="text-body-2 text-medium-emphasis mb-6">{{ t('dashboard.recent_activity_subtitle') }}</p>
              <ul class="d-flex flex-column pl-0" style="list-style: none">
                <li
                  v-for="(log, i) in stats.recent_activity"
                  :key="log.id"
                  class="d-flex align-center justify-space-between ga-3 py-2"
                  :class="{ 'border-b border-dashed': i < stats.recent_activity.length - 1 }"
                >
                  <span class="text-body-2 font-weight-medium text-truncate" style="min-width: 0">{{ log.description }}</span>
                  <span class="text-caption text-medium-emphasis flex-shrink-0 text-no-wrap">
                    {{ log.user?.name || t('common.n_a') }} · {{ relativeTime(log.created_at) }}
                  </span>
                </li>
                <li v-if="!stats.recent_activity.length" class="py-6 text-center text-body-2 text-medium-emphasis">{{ t('dashboard.no_activity') }}</li>
              </ul>
            </v-card>
          </v-col>
        </v-row>

        <v-card v-else rounded="lg" variant="flat" border class="pa-6">
          <h2 class="text-subtitle-1 font-weight-bold">{{ t('dashboard.needs_attention') }}</h2>
          <p class="text-body-2 text-medium-emphasis mb-6">{{ t('dashboard.needs_attention_subtitle') }}</p>
          <NeedsAttentionList :items="stats.needs_attention" />
        </v-card>

        <v-card rounded="lg" variant="flat" border class="pa-6">
          <h2 class="text-subtitle-1 font-weight-bold">{{ t('dashboard.by_location') }}</h2>
          <p class="text-body-2 text-medium-emphasis mb-6">{{ t('dashboard.by_location_subtitle') }}</p>
          <LocationPillCards :locations="stats.assets_by_location" />
        </v-card>

        <v-card v-if="stats.recent_assets" rounded="lg" variant="flat" border class="pa-6">
          <h2 class="text-subtitle-1 font-weight-bold">{{ t('dashboard.recent_assets') }}</h2>
          <p class="text-body-2 text-medium-emphasis mb-6">{{ t('dashboard.recent_assets_subtitle') }}</p>
          <ul class="d-flex flex-column pl-0" style="list-style: none">
            <li
              v-for="(asset, i) in stats.recent_assets"
              :key="asset.id"
              class="d-flex align-center justify-space-between ga-3 py-2"
              :class="{ 'border-b border-dashed': i < stats.recent_assets.length - 1 }"
            >
              <div class="d-flex align-center ga-2 flex-grow-1" style="min-width: 0">
                <span class="font-weight-semibold text-body-2 text-truncate">{{ asset.name }}</span>
                <span class="font-mono font-mono-tag text-caption text-medium-emphasis text-truncate flex-shrink-0">{{ asset.asset_code }}</span>
              </div>
              <span class="text-caption text-medium-emphasis flex-shrink-0 text-no-wrap">{{ asset.category?.name || t('common.n_a') }}</span>
            </li>
            <li v-if="!stats.recent_assets.length" class="py-6 text-center text-body-2 text-medium-emphasis">{{ t('dashboard.no_assets') }}</li>
          </ul>
        </v-card>

        <v-card v-if="stats.assets_by_category !== undefined" rounded="lg" variant="flat" border class="pa-6">
          <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-3">
            <div>
              <h2 class="text-subtitle-1 font-weight-bold">{{ t('dashboard.registered_over_time') }}</h2>
              <p class="text-body-2 text-medium-emphasis">{{ t('dashboard.registered_over_time_subtitle') }}</p>
            </div>
            <v-btn-toggle v-model="trendPeriod" mandatory density="compact" color="primary" class="flex-shrink-0">
              <v-btn v-for="p in ['day', 'month', 'year']" :key="p" :value="p" size="small">{{ t(`dashboard.period_${p}`) }}</v-btn>
            </v-btn-toggle>
          </div>
          <div v-if="trendLoading" class="d-flex align-center justify-center text-body-2 text-medium-emphasis" style="height: 224px">{{ t('common.loading') }}</div>
          <TrendChart v-else-if="trendData.some((d) => d.count > 0)" :data="trendData" :period="trendPeriod" class="mt-4" />
          <p v-else class="text-body-2 text-medium-emphasis py-10 text-center">{{ t('dashboard.no_trend_data') }}</p>
        </v-card>
      </template>
  </v-container>
</template>
