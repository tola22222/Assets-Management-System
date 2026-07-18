<script setup>
import { ref, onMounted } from 'vue'
import http from '../api/http'
import { useAuthStore } from '../stores/auth'
import AppLayout from '../layouts/AppLayout.vue'
import StatCard from '../components/ui/StatCard.vue'
import DonutChart from '../components/ui/DonutChart.vue'
import NeedsAttentionList from '../components/ui/NeedsAttentionList.vue'
import LocationPillCards from '../components/ui/LocationPillCards.vue'

const auth = useAuthStore()
const stats = ref(null)
const loading = ref(true)
const error = ref('')

function formatCurrency(value) {
  if (value >= 1000) return `$${Math.round(value / 1000)}K`
  return `$${value}`
}

onMounted(async () => {
  try {
    const { data } = await http.get('/dashboard')
    stats.value = data
  } catch (e) {
    error.value = 'Could not load dashboard data.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-7xl mx-auto space-y-6">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h1 class="font-display text-3xl font-bold text-ink">Good morning, {{ auth.user?.name?.split(' ')[0] || auth.user?.name }}</h1>
          <p class="text-gray-500 text-sm mt-1">Snapshot of PEPY's fixed assets across the office and all learning centers</p>
        </div>
        <button class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-5 py-2.5 rounded-xl transition flex-shrink-0">
          + Add asset
        </button>
      </div>

      <div v-if="loading" class="text-gray-400 text-sm">Loading…</div>
      <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl">{{ error }}</div>

      <template v-else-if="stats">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard :value="stats.total_assets" label="Total assets on register" />
          <StatCard :value="stats.total_categories" label="Asset categories" />
          <StatCard :value="stats.total_locations" label="Offices & learning centers" />
          <StatCard
            :value="formatCurrency(stats.recorded_value)"
            label="Recorded value"
            :badge="`${stats.priced_percentage}% priced`"
          />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h2 class="font-bold text-ink">Assets by category</h2>
            <p class="text-sm text-gray-400 mb-5">Breakdown of what PEPY owns</p>
            <DonutChart
              v-if="stats.assets_by_category.length"
              :segments="stats.assets_by_category"
              :total="stats.total_assets"
            />
            <p v-else class="text-sm text-gray-400">No assets registered yet.</p>
          </div>

          <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h2 class="font-bold text-ink">Needs attention</h2>
            <p class="text-sm text-gray-400 mb-4">Records requiring follow-up</p>
            <NeedsAttentionList :items="stats.needs_attention" />
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6">
          <h2 class="font-bold text-ink">Assets by location</h2>
          <p class="text-sm text-gray-400 mb-4">Stock quantity recorded at each site</p>
          <LocationPillCards :locations="stats.assets_by_location" />
        </div>
      </template>
    </div>
  </AppLayout>
</template>
