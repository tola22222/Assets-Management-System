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
  <v-container fluid class="pa-0 d-flex flex-column ga-6">
      <div class="d-flex flex-column flex-sm-row align-sm-start justify-space-between ga-4">
        <div>
          <h1 class="text-h4 font-weight-bold">{{ greeting }}, {{ auth.user?.name?.split(' ')[0] || auth.user?.name }}</h1>
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

          <v-col cols="12" lg="6">
            <v-card rounded="lg" variant="flat" border class="pa-6 h-100">
              <h2 class="text-subtitle-1 font-weight-bold">{{ t('dashboard.needs_attention') }}</h2>
              <p class="text-body-2 text-medium-emphasis mb-6">{{ t('dashboard.needs_attention_subtitle') }}</p>
              <NeedsAttentionList :items="stats.needs_attention" />
            </v-card>
          </v-col>
        </v-row>

        <v-card rounded="lg" variant="flat" border class="pa-6">
          <h2 class="text-subtitle-1 font-weight-bold">{{ t('dashboard.by_location') }}</h2>
          <p class="text-body-2 text-medium-emphasis mb-6">{{ t('dashboard.by_location_subtitle') }}</p>
          <LocationPillCards :locations="stats.assets_by_location" />
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
