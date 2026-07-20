<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../api/http'
import { useAuthStore } from '../stores/auth'
import AppLayout from '../layouts/AppLayout.vue'
import StatCard from '../components/ui/StatCard.vue'
import DonutChart from '../components/ui/DonutChart.vue'
import NeedsAttentionList from '../components/ui/NeedsAttentionList.vue'
import LocationPillCards from '../components/ui/LocationPillCards.vue'

const { t } = useI18n()
const auth = useAuthStore()
const stats = ref(null)
const loading = ref(true)
const error = ref('')

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

onMounted(async () => {
  try {
    const { data } = await http.get('/dashboard')
    stats.value = data
  } catch (e) {
    error.value = t('dashboard.load_error')
  } finally {
    loading.value = false
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
      </template>
    </div>
  </AppLayout>
</template>
