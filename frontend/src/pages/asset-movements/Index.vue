<script setup>
import { onMounted, ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useServerTable } from '../../composables/useServerTable'
import { useConfirm } from '../../composables/useConfirm'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const {
  items: movements, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
  filters, hasActiveFilters, applyFilters, clearFilters,
} = useServerTable('/asset-movements', { filterKeys: ['movement_type'] })
const { destroy } = useApiCrud('/asset-movements', { entityName: t('asset_movements.title'), refetch: fetchPage })
const { confirm } = useConfirm()
const toast = useToastStore()

function formatDate(v) {
  return v ? new Date(v).toLocaleString() : t('common.n_a')
}

async function handleDelete(row) {
  const ok = await confirm({
    title: t('asset_movements.delete_confirm_title'),
    message: t('asset_movements.delete_confirm_message'),
    color: 'error',
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return
  try {
    await destroy(row.id)
  } catch (e) {
    toast.error(e.response?.data?.message || t('asset_movements.delete_failed'))
  }
}

const headers = computed(() => [
  { title: t('common.asset'), key: 'asset', sortable: false },
  { title: t('asset_movements.type'), key: 'movement_type', sortable: true },
  { title: t('asset_movements.from'), key: 'from', sortable: false },
  { title: t('asset_movements.to'), key: 'to', sortable: false },
  { title: t('asset_movements.reference'), key: 'reference_no', sortable: true },
  { title: t('asset_movements.date'), key: 'created_at', sortable: true },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 70 },
])

onMounted(fetchPage)
</script>

<template>
  <v-container fluid class="pa-0">
      <AppPageHeader :title="t('asset_movements.title')" :subtitle="t('asset_movements.subtitle')" />

      <AppDataTable
        :headers="headers"
        :items="movements"
        :items-length="total"
        :loading="loading"
        :page="page"
        :items-per-page="perPage"
        :items-per-page-options="[10, 25, 50, 100]"
        :sort-by="sortByVuetify"
        :search="search"
        :empty-text="t('asset_movements.empty')"
        :show-view="false"
        :show-edit="false"
        @update:search="setSearch"
        @update:options="handleOptions"
        @delete="handleDelete"
      >
        <template #filters>
          <v-select
            v-model="filters.movement_type"
            :label="t('asset_movements.type')"
            :items="[
              { title: t('common.all'), value: '' },
              { title: t('status.stock_in'), value: 'stock_in' },
              { title: t('status.transfer'), value: 'transfer' },
            ]"
            density="compact"
            variant="outlined"
            hide-details
            style="max-width: 220px"
            @update:model-value="applyFilters"
          />
          <v-btn v-if="hasActiveFilters" variant="text" size="small" @click="clearFilters">{{ t('common.clear_filters') }}</v-btn>
        </template>

        <template #item.asset="{ item }">
          <p class="font-medium">{{ item.asset?.name || t('common.n_a') }}</p>
          <p class="font-mono text-caption text-medium-emphasis">{{ item.asset?.asset_code }}</p>
        </template>
        <template #item.movement_type="{ item }"><StatusBadge :status="item.movement_type" /></template>
        <template #item.from="{ item }">{{ item.from_location?.name || '—' }}</template>
        <template #item.to="{ item }">{{ item.to_location?.name || t('common.n_a') }}</template>
        <template #item.reference_no="{ item }"><span class="font-mono text-caption">{{ item.reference_no || '—' }}</span></template>
        <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>
      </AppDataTable>
  </v-container>
</template>
