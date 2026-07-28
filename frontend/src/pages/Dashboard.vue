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

const { t } = useI18n()
const auth = useAuthStore()
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
  if (stats.value?.assets_by_category !== undefined) {
    loadTrend()
  }
})
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 max-w-7xl mx-auto space-y-6">
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

        <div v-if="stats.assets_by_category !== undefined" class="card p-6">
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
