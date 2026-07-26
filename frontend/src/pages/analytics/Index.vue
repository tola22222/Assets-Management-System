<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import StatCard from '../../components/ui/StatCard.vue'
import DonutChart from '../../components/ui/DonutChart.vue'
import TrendChart from '../../components/ui/TrendChart.vue'
import ConditionByLocationChart from '../../components/ui/ConditionByLocationChart.vue'

const { t } = useI18n()

const period = ref('month')
const loading = ref(true)

const recordedValue = ref(0)
const pricedPercentage = ref(0)
const valueByCategory = ref({ total_value: 0, segments: [] })
const acquisitions = ref([])
const conditionByLocation = ref([])
const topValueAssets = ref([])
const openMaintenanceCount = ref(0)
const pendingVerificationCount = ref(0)

function money(v) {
  return '$' + Number(v || 0).toLocaleString(undefined, { maximumFractionDigits: 0 })
}

const acquiredThisPeriod = computed(() => acquisitions.value.reduce((sum, d) => sum + d.count, 0))

const topCategory = computed(() => valueByCategory.value.segments[0])
const insightSentence = computed(() => {
  const top = topCategory.value
  if (!top) return ''
  return t('analytics.insight_sentence', { category: top.category, percent: top.percentage })
})

async function load() {
  loading.value = true
  try {
    const [dash, vbc, acq, cbl, top, maint, verif] = await Promise.all([
      http.get('/dashboard'),
      http.get('/reports/value-by-category'),
      http.get('/reports/acquisitions-over-time', { params: { period: period.value } }),
      http.get('/reports/condition-by-location'),
      http.get('/reports/top-value-assets'),
      http.get('/asset-maintenance', { params: { status: 'pending', per_page: 1 } }),
      http.get('/asset-verifications', { params: { completed: 'no', per_page: 1 } }),
    ])
    recordedValue.value = dash.data.recorded_value ?? 0
    pricedPercentage.value = dash.data.priced_percentage ?? 0
    valueByCategory.value = vbc.data
    acquisitions.value = acq.data.data
    conditionByLocation.value = cbl.data
    topValueAssets.value = top.data
    openMaintenanceCount.value = maint.data.total ?? 0
    pendingVerificationCount.value = verif.data.total ?? 0
  } finally {
    loading.value = false
  }
}

watch(period, load)
onMounted(load)
</script>

<template>
  <v-container fluid class="pa-0 d-flex flex-column ga-6">
    <div class="d-flex flex-wrap align-center justify-space-between ga-3">
      <AppPageHeader :title="t('analytics.title')" :subtitle="t('analytics.subtitle')" class="mb-0" />
      <v-btn-toggle v-model="period" mandatory density="compact" color="primary">
        <v-btn v-for="p in ['day', 'month', 'year']" :key="p" :value="p" size="small">{{ t(`dashboard.period_${p}`) }}</v-btn>
      </v-btn-toggle>
    </div>

    <v-row dense>
      <v-col cols="12" sm="6" lg="3"><StatCard :value="money(recordedValue)" :label="t('analytics.total_value')" :badge="`${pricedPercentage}% ${t('analytics.priced')}`" /></v-col>
      <v-col cols="12" sm="6" lg="3"><StatCard :value="acquiredThisPeriod" :label="t('analytics.acquired_this_period')" /></v-col>
      <v-col cols="12" sm="6" lg="3"><StatCard :value="openMaintenanceCount" :label="t('analytics.open_maintenance')" /></v-col>
      <v-col cols="12" sm="6" lg="3"><StatCard :value="pendingVerificationCount" :label="t('analytics.pending_verifications')" /></v-col>
    </v-row>

    <v-row dense>
      <v-col cols="12" lg="6">
        <v-card rounded="lg" variant="flat" border class="pa-6 h-100">
          <h2 class="text-subtitle-1 font-weight-bold">{{ t('analytics.value_by_category') }}</h2>
          <p class="text-body-2 text-medium-emphasis mb-6">{{ t('analytics.value_by_category_subtitle') }}</p>
          <DonutChart
            v-if="valueByCategory.segments.length"
            :segments="valueByCategory.segments"
            :total="money(valueByCategory.total_value)"
            :total-label="t('analytics.total_value_label')"
            :format-count="money"
          />
          <p v-else class="text-body-2 text-medium-emphasis py-6 text-center">{{ t('dashboard.no_assets') }}</p>
        </v-card>
      </v-col>
      <v-col cols="12" lg="6">
        <v-card rounded="lg" variant="flat" border class="pa-6 h-100">
          <h2 class="text-subtitle-1 font-weight-bold">{{ t('analytics.acquisitions_over_time') }}</h2>
          <p class="text-body-2 text-medium-emphasis mb-6">{{ t('analytics.acquisitions_over_time_subtitle') }}</p>
          <TrendChart v-if="acquisitions.some((d) => d.count > 0)" :data="acquisitions" :period="period" />
          <p v-else class="text-body-2 text-medium-emphasis py-10 text-center">{{ t('dashboard.no_trend_data') }}</p>
        </v-card>
      </v-col>
    </v-row>

    <v-row dense>
      <v-col cols="12" lg="6">
        <v-card rounded="lg" variant="flat" border class="pa-6 h-100">
          <h2 class="text-subtitle-1 font-weight-bold">{{ t('analytics.condition_by_location') }}</h2>
          <p class="text-body-2 text-medium-emphasis mb-6">{{ t('analytics.condition_by_location_subtitle') }}</p>
          <ConditionByLocationChart v-if="conditionByLocation.length" :rows="conditionByLocation" />
          <p v-else class="text-body-2 text-medium-emphasis py-6 text-center">{{ t('dashboard.no_assets') }}</p>
        </v-card>
      </v-col>
      <v-col cols="12" lg="6">
        <v-card rounded="lg" variant="flat" border class="pa-6 h-100">
          <h2 class="text-subtitle-1 font-weight-bold">{{ t('analytics.top_value_assets') }}</h2>
          <p class="text-body-2 text-medium-emphasis mb-6">{{ t('analytics.top_value_assets_subtitle') }}</p>
          <v-table density="compact">
            <tbody>
              <tr v-for="(a, i) in topValueAssets" :key="a.id">
                <td class="text-medium-emphasis" style="width: 32px">{{ i + 1 }}</td>
                <td>
                  <div class="font-weight-medium">{{ a.name }}</div>
                  <div class="font-mono font-mono-tag text-caption text-medium-emphasis">{{ a.asset_code }}</div>
                </td>
                <td class="text-right font-weight-semibold font-display">{{ money(a.purchase_price) }}</td>
              </tr>
              <tr v-if="!topValueAssets.length">
                <td colspan="3" class="text-center text-medium-emphasis py-6">{{ t('dashboard.no_assets') }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-col>
    </v-row>

    <v-alert v-if="insightSentence" variant="tonal" color="secondary" icon="mdi-lightbulb-on-outline">
      {{ insightSentence }}
    </v-alert>
  </v-container>
</template>
