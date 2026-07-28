<script setup>
import { ref, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableFilter } from '../../composables/useTableFilter'
import { useTableSort } from '../../composables/useTableSort'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'

const { t } = useI18n()
const { items: transfers, loading, fetchAll } = useApiCrud('/asset-transfers', { entityName: t('asset_transfers.entity') })
const toast = useToastStore()
const auth = useAuthStore()

const { search, filtered: searched } = useTableSearch(transfers, [(r) => r.asset?.name, (r) => r.asset?.asset_code, (r) => r.requester?.name])
const { filters, filtered: matched, hasActiveFilters, clearFilters } = useTableFilter(searched, {
  status: (row, v) => row.status === v,
})
const { sortKey, sortDir, toggleSort, sorted: sortedTransfers } = useTableSort(matched, {
  defaultKey: 'transfer_date', defaultDir: 'desc',
  paths: { asset: 'asset.name', from: 'from_location.name', to: 'to_location.name', requester: 'requester.name' },
})

const assets = ref([])
const locations = ref([])
const showModal = ref(false)
const form = reactive({ asset_id: '', from_location_id: '', to_location_id: '', reason: '', transfer_date: '' })

async function loadOptions() {
  const [a, l] = await Promise.all([http.get('/assets'), http.get('/locations')])
  assets.value = a.data
  locations.value = l.data
}

function openCreate() {
  Object.assign(form, { asset_id: '', from_location_id: '', to_location_id: '', reason: '', transfer_date: new Date().toISOString().slice(0, 10) })
  showModal.value = true
}

async function handleSubmit() {
  try {
    await http.post('/asset-transfers', form)
    toast.success(t('asset_transfers.submitted'))
    showModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || t('asset_transfers.submit_failed'))
  }
}

async function approve(id) {
  await http.post(`/asset-transfers/${id}/approve`)
  toast.success(t('asset_transfers.approved'))
  await fetchAll()
}

async function reject(id) {
  await http.post(`/asset-transfers/${id}/reject`)
  toast.success(t('asset_transfers.rejected'))
  await fetchAll()
}

onMounted(() => {
  fetchAll()
  loadOptions()
})
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-6xl mx-auto space-y-6">
      <PageHeader :title="t('asset_transfers.title')" :subtitle="t('asset_transfers.subtitle')" :buttonText="t('asset_transfers.new')" @action="openCreate" />

      <div class="table-wrap">
        <div class="table-toolbar">
          <div class="w-full sm:max-w-xs">
            <SearchInput v-model="search" :placeholder="t('common.search')" />
          </div>
          <select v-model="filters.status" class="filter-select">
            <option value="">{{ t('common.status') }}: {{ t('common.all') }}</option>
            <option value="pending">{{ t('status.pending') }}</option>
            <option value="approved">{{ t('status.approved') }}</option>
            <option value="rejected">{{ t('status.rejected') }}</option>
          </select>
          <button v-if="hasActiveFilters" @click="clearFilters" class="btn-subtle btn-sm">{{ t('common.clear_filters') }}</button>
        </div>
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th class="th-sort" @click="toggleSort('asset')">{{ t('common.asset') }}<TableSortIcon :active="sortKey === 'asset'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('from')">{{ t('asset_transfers.from') }}<TableSortIcon :active="sortKey === 'from'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('to')">{{ t('asset_transfers.to') }}<TableSortIcon :active="sortKey === 'to'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('requester')">{{ t('asset_transfers.requester') }}<TableSortIcon :active="sortKey === 'requester'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('status')">{{ t('common.status') }}<TableSortIcon :active="sortKey === 'status'" :direction="sortDir" /></th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t2 in sortedTransfers" :key="t2.id">
                <td class="font-medium text-fg">{{ t2.asset?.name || t('common.n_a') }}</td>
                <td>{{ t2.from_location?.name || t('common.n_a') }}</td>
                <td>{{ t2.to_location?.name || t('common.n_a') }}</td>
                <td>{{ t2.requester?.name || t('common.n_a') }}</td>
                <td><StatusBadge :status="t2.status" /></td>
                <td class="text-right whitespace-nowrap">
                  <template v-if="t2.status === 'pending' && auth.user?.role === 'operations_hr_manager'">
                    <div class="flex items-center justify-end gap-1.5">
                      <button @click="approve(t2.id)" :title="t('common.approve')" class="w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      </button>
                      <button @click="reject(t2.id)" :title="t('common.reject')" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      </button>
                    </div>
                  </template>
                </td>
              </tr>
              <tr v-if="!loading && !sortedTransfers.length">
                <td colspan="6" class="py-10 text-center text-faint">{{ t('asset_transfers.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <Modal v-if="showModal" :title="t('asset_transfers.modal_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_transfers.asset_required') }}</label>
            <select v-model="form.asset_id" required class="input">
              <option value="">{{ t('common.select_asset') }}</option>
              <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }} ({{ a.asset_code }})</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_transfers.from_location_required') }}</label>
              <select v-model="form.from_location_id" required class="input">
                <option value="">{{ t('common.select_location') }}</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_transfers.to_location_required') }}</label>
              <select v-model="form.to_location_id" required class="input">
                <option value="">{{ t('common.select_location') }}</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_transfers.transfer_date_required') }}</label>
            <input v-model="form.transfer_date" type="date" required class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_transfers.reason') }}</label>
            <textarea v-model="form.reason" rows="2" class="input"></textarea>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ t('asset_transfers.submit_button') }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>
