<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'

// Total / Transferred / Available for the model of the chosen asset AT ONE
// LOCATION (locationId — the transfer's From site; omitted = where the chosen
// unit is). Every location holds its own stock. Recounted by the server on
// every fetch (AssetStockService), which also refuses an over-quantity on
// submit — this box is the heads-up, not the guard.
const props = defineProps({
  assetId: { type: [String, Number], default: '' },
  locationId: { type: [String, Number], default: '' },
  quantity: { type: [String, Number], default: 1 },
})
const emit = defineEmits(['loaded'])
const { t } = useI18n()

const stock = ref(null)

watch(() => [props.assetId, props.locationId], async ([id, locationId]) => {
  stock.value = null
  emit('loaded', null)
  if (!id) return
  try {
    const params = locationId ? { asset_id: id, location_id: locationId } : { asset_id: id }
    const { data } = await http.get('/asset-transfers/stock', { params })
    // Ignore a slow answer for an asset or site the user has already changed away from.
    if (String(props.assetId) !== String(id) || String(props.locationId) !== String(locationId)) return
    stock.value = data
    emit('loaded', data)
  } catch {
    // Without the numbers the form still works; the server still checks.
  }
}, { immediate: true })

const over = computed(() => stock.value && Number(props.quantity) > stock.value.available)
</script>

<template>
  <!-- Same language as the dashboard's StatCard: sentence-case label over a
       semibold display figure, one tile per number. "Using" is the figure the
       form is checked against, so it carries the brand tint (red when low). -->
  <div v-if="stock" class="rounded-xl border border-line bg-surface-2 p-3.5 text-sm">
    <div class="flex items-center gap-2.5 mb-3">
      <p class="font-semibold text-fg truncate flex-1 min-w-0">{{ stock.name }}</p>
      <span v-if="stock.low_stock" class="badge badge-danger flex-shrink-0">{{ t('asset_transfers.low_stock') }}</span>
    </div>
    <div class="grid grid-cols-3 gap-2.5">
      <div class="rounded-lg border border-line bg-surface px-3 py-2.5">
        <p class="text-xs font-medium text-muted leading-snug">{{ t('asset_transfers.stock_total') }}</p>
        <p class="font-display text-2xl font-semibold text-fg tracking-tight leading-none mt-2">{{ stock.total }}</p>
      </div>
      <div class="rounded-lg border border-line bg-surface px-3 py-2.5">
        <p class="text-xs font-medium text-muted leading-snug">{{ t('asset_transfers.stock_transferred') }}</p>
        <p class="font-display text-2xl font-semibold text-fg tracking-tight leading-none mt-2">{{ stock.transferred }}</p>
      </div>
      <div
        class="rounded-lg border px-3 py-2.5"
        :class="stock.low_stock ? 'border-red-200 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10' : 'border-brand/30 bg-brand/5 dark:border-brand-200/30'"
      >
        <p class="text-xs font-medium leading-snug" :class="stock.low_stock ? 'text-red-700 dark:text-red-300' : 'text-brand dark:text-brand-200'">{{ t('asset_transfers.stock_available') }}</p>
        <p class="font-display text-2xl font-semibold tracking-tight leading-none mt-2" :class="stock.low_stock ? 'text-red-600 dark:text-red-400' : 'text-brand dark:text-brand-200'">{{ stock.available }}</p>
      </div>
    </div>
    <p v-if="stock.lost_broken" class="text-xs text-faint mt-2">{{ t('asset_transfers.stock_lost_broken', { n: stock.lost_broken }) }}</p>
    <p v-if="over" class="text-xs font-medium text-red-600 dark:text-red-400 mt-2">{{ t('asset_transfers.quantity_exceeds', { n: stock.available }) }}</p>
  </div>
</template>
