<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import StatCard from '../../components/ui/StatCard.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import TrendChart from '../../components/ui/TrendChart.vue'
import DonutChart from '../../components/ui/DonutChart.vue'
import MovementDialog from '../../components/dialogs/MovementDialog.vue'
import { useStockMotion } from '../../composables/useStockMotion'

const { t } = useI18n()
const { items, loading, stats, load } = useStockMotion()

const showMovementDialog = ref(false)
const typeFilter = ref('')

const TYPES = ['transfer', 'checkout', 'checkin', 'disposal']

const filteredItems = computed(() => {
  if (!typeFilter.value) return items.value
  return items.value.filter((i) => i.type === typeFilter.value)
})

// Last 14 days, movement counts per day — computed client-side from the merged feed.
const trendData = computed(() => {
  const buckets = {}
  for (let i = 13; i >= 0; i--) {
    const d = new Date()
    d.setDate(d.getDate() - i)
    buckets[d.toISOString().slice(0, 10)] = 0
  }
  items.value.forEach((i) => {
    const key = new Date(i.date).toISOString().slice(0, 10)
    if (key in buckets) buckets[key]++
  })
  return Object.entries(buckets).map(([label, count]) => ({ label, count }))
})

// Flow by destination location — reuses DonutChart's {category,count,percentage} shape.
const flowByLocation = computed(() => {
  const counts = {}
  items.value.forEach((i) => {
    const dest = i.to
    if (!dest) return
    counts[dest] = (counts[dest] || 0) + 1
  })
  const total = Object.values(counts).reduce((a, b) => a + b, 0)
  return Object.entries(counts)
    .map(([category, count]) => ({ category, count, percentage: total ? Math.round((count / total) * 100) : 0 }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 6)
})

function formatDate(v) {
  return v ? new Date(v).toLocaleString() : t('common.n_a')
}

onMounted(load)
</script>

<template>
  <v-container fluid class="pa-0">
    <AppPageHeader
      :title="t('stock_motion.title')"
      :subtitle="t('stock_motion.subtitle')"
      :actions="[{ label: t('stock_motion.new_movement'), icon: 'mdi-plus', onClick: () => (showMovementDialog = true) }]"
    />

    <v-row dense class="mb-2">
      <v-col cols="12" sm="6" lg="3"><StatCard :value="stats.movementsThisMonth" :label="t('stock_motion.movements_this_month')" /></v-col>
      <v-col cols="12" sm="6" lg="3"><StatCard :value="stats.pendingTransfers" :label="t('stock_motion.pending_transfers')" /></v-col>
      <v-col cols="12" sm="6" lg="3"><StatCard :value="stats.checkedOut" :label="t('stock_motion.checked_out')" /></v-col>
      <v-col cols="12" sm="6" lg="3"><StatCard :value="stats.overdueReturns" :label="t('stock_motion.overdue_returns')" /></v-col>
    </v-row>

    <v-row dense class="mb-2">
      <v-col cols="12" lg="6">
        <v-card rounded="lg" variant="flat" border class="pa-6 h-100">
          <h2 class="text-subtitle-1 font-weight-bold">{{ t('stock_motion.trend_title') }}</h2>
          <p class="text-body-2 text-medium-emphasis mb-4">{{ t('stock_motion.trend_subtitle') }}</p>
          <TrendChart :data="trendData" period="day" unit-label="movement" action-label="" />
        </v-card>
      </v-col>
      <v-col cols="12" lg="6">
        <v-card rounded="lg" variant="flat" border class="pa-6 h-100">
          <h2 class="text-subtitle-1 font-weight-bold">{{ t('stock_motion.flow_title') }}</h2>
          <p class="text-body-2 text-medium-emphasis mb-4">{{ t('stock_motion.flow_subtitle') }}</p>
          <DonutChart v-if="flowByLocation.length" :segments="flowByLocation" :total="items.length" :total-label="t('stock_motion.flow_total_label')" />
          <p v-else class="text-body-2 text-medium-emphasis py-6 text-center">{{ t('stock_motion.no_flow_data') }}</p>
        </v-card>
      </v-col>
    </v-row>

    <v-card rounded="lg" variant="flat" border class="pa-6">
      <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
        <h2 class="text-subtitle-1 font-weight-bold">{{ t('stock_motion.recent_title') }}</h2>
        <v-chip-group v-model="typeFilter" mandatory="force" filter selected-class="text-primary">
          <v-chip value="" size="small" variant="outlined">{{ t('common.all') }}</v-chip>
          <v-chip v-for="type in TYPES" :key="type" :value="type" size="small" variant="outlined">{{ t(`stock_motion.type_${type}`) }}</v-chip>
        </v-chip-group>
      </div>

      <v-timeline v-if="!loading && filteredItems.length" density="compact" side="end" truncate-line="both">
        <v-timeline-item
          v-for="entry in filteredItems.slice(0, 30)"
          :key="entry.id"
          :dot-color="entry.color"
          size="small"
        >
          <div class="d-flex align-center justify-space-between flex-wrap ga-3">
            <div style="min-width: 0">
              <div class="d-flex align-center ga-2">
                <span class="font-weight-semibold text-body-2">{{ entry.asset?.name || t('common.n_a') }}</span>
                <span class="font-mono font-mono-tag text-caption text-medium-emphasis">{{ entry.asset?.asset_code }}</span>
              </div>
              <p class="text-caption text-medium-emphasis mt-1">
                <span class="text-capitalize">{{ t(`stock_motion.type_${entry.type}`) }}</span>
                <template v-if="entry.from || entry.to"> · {{ entry.from || '—' }} → {{ entry.to || '—' }}</template>
                <template v-if="entry.actor"> · {{ entry.actor }}</template>
              </p>
            </div>
            <div class="d-flex align-center ga-3 flex-shrink-0">
              <StatusBadge v-if="entry.status" :status="entry.status" />
              <span class="text-caption text-medium-emphasis text-no-wrap">{{ formatDate(entry.date) }}</span>
            </div>
          </div>
        </v-timeline-item>
      </v-timeline>
      <p v-else-if="!loading" class="text-body-2 text-medium-emphasis text-center py-10">{{ t('stock_motion.empty') }}</p>
      <div v-else class="d-flex justify-center py-10"><v-progress-circular indeterminate color="primary" /></div>
    </v-card>

    <MovementDialog v-model="showMovementDialog" />
  </v-container>
</template>
