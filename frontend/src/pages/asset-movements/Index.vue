<script setup>
import { onMounted, ref, h, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { NDataTable } from 'naive-ui'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableFilter } from '../../composables/useTableFilter'

const { t } = useI18n()
const { items: movements, loading, fetchAll, destroy } = useApiCrud('/asset-movements', { entityName: t('asset_movements.title') })
const deletingId = ref(null)

const { search, filtered: searched } = useTableSearch(movements, [(m) => m.asset?.name, (m) => m.asset?.asset_code, 'reference_no'])
const { filters, filtered: matched, hasActiveFilters, clearFilters } = useTableFilter(searched, {
  movement_type: (row, v) => row.movement_type === v,
})

function formatDate(v) {
  return v ? new Date(v).toLocaleString() : t('common.n_a')
}

async function confirmDelete() {
  await destroy(deletingId.value)
  deletingId.value = null
}

const columns = computed(() => [
  {
    title: t('common.asset'), key: 'asset', sorter: 'default',
    render: (m) => h('div', {}, [
      h('p', { class: 'font-medium text-fg' }, m.asset?.name || t('common.n_a')),
      h('p', { class: 'font-mono text-xs text-faint' }, m.asset?.asset_code),
    ]),
  },
  {
    title: t('asset_movements.type'), key: 'movement_type', sorter: 'default',
    render: (m) => h(StatusBadge, { status: m.movement_type }),
  },
  {
    title: t('asset_movements.from'), key: 'from', sorter: 'default',
    render: (m) => m.from_location?.name || '—',
  },
  {
    title: t('asset_movements.to'), key: 'to', sorter: 'default',
    render: (m) => m.to_location?.name || t('common.n_a'),
  },
  {
    title: t('asset_movements.reference'), key: 'reference_no',
    render: (m) => h('span', { class: 'font-mono text-xs' }, m.reference_no || '—'),
  },
  {
    title: t('asset_movements.date'), key: 'created_at', sorter: 'default',
    render: (m) => formatDate(m.created_at),
  },
  {
    title: t('common.actions'), key: 'actions', width: 70,
    render: (m) => h('div', { class: 'flex justify-end' }, [
      h('button', {
        onClick: () => { deletingId.value = m.id },
        title: t('common.delete'),
        class: 'w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition',
      }, [h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', 'stroke-width': '2.2', viewBox: '0 0 24 24' }, [
        h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0' }),
      ])]),
    ]),
  },
])

onMounted(fetchAll)
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-6xl mx-auto space-y-6">
      <PageHeader :title="t('asset_movements.title')" :subtitle="t('asset_movements.subtitle')" />

      <div class="table-wrap">
        <div class="table-toolbar">
          <div class="w-full sm:max-w-xs">
            <SearchInput v-model="search" :placeholder="t('common.search')" />
          </div>
          <select v-model="filters.movement_type" class="filter-select">
            <option value="">{{ t('asset_movements.type') }}: {{ t('common.all') }}</option>
            <option value="stock_in">{{ t('status.stock_in') }}</option>
            <option value="transfer">{{ t('status.transfer') }}</option>
          </select>
          <button v-if="hasActiveFilters" @click="clearFilters" class="btn-subtle btn-sm">{{ t('common.clear_filters') }}</button>
        </div>
        <n-data-table
          :columns="columns"
          :data="matched"
          :loading="loading"
          :row-key="(row) => row.id"
          :pagination="{ pageSize: 10, showSizePicker: true, pageSizes: [10, 25, 50, 100] }"
          :bordered="false"
        >
          <template #empty>
            <p class="py-10 text-center text-faint text-sm">{{ t('asset_movements.empty') }}</p>
          </template>
        </n-data-table>
      </div>
    </div>

    <ConfirmDialog v-if="deletingId" :title="t('asset_movements.delete_confirm_title')" :message="t('asset_movements.delete_confirm_message')" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
