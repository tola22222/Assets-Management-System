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
const { items: returnsList, loading, fetchAll } = useApiCrud('/asset-returns', { entityName: t('asset_returns.entity') })
const toast = useToastStore()
const auth = useAuthStore()

const { search, filtered: searched } = useTableSearch(returnsList, [(r) => r.asset?.name, (r) => r.asset?.asset_code, (r) => r.returned_by?.name])
const { filters, filtered: matched, hasActiveFilters, clearFilters } = useTableFilter(searched, {
  status: (row, v) => row.status === v,
  condition: (row, v) => row.condition === v,
})
const { sortKey, sortDir, toggleSort, sorted: sortedReturns } = useTableSort(matched, {
  defaultKey: 'return_date', defaultDir: 'desc',
  paths: { asset: 'asset.name', returned_by: 'returned_by.name' },
})

const assignments = ref([])
const showModal = ref(false)
const form = reactive({ assignment_id: '', asset_id: '', condition: 'good', damage_notes: '', return_date: '' })

async function loadOptions() {
  const { data } = await http.get('/asset-assignments')
  assignments.value = data.filter((a) => a.status !== 'returned')
}

function openCreate() {
  Object.assign(form, { assignment_id: '', asset_id: '', condition: 'good', damage_notes: '', return_date: new Date().toISOString().slice(0, 10) })
  showModal.value = true
}

function onAssignmentChange() {
  const assignment = assignments.value.find((a) => a.id == form.assignment_id)
  form.asset_id = assignment?.asset_id || ''
}

async function handleSubmit() {
  try {
    await http.post('/asset-returns', form)
    toast.success(t('asset_returns.submitted'))
    showModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || t('asset_returns.submit_failed'))
  }
}

async function approve(id) {
  await http.post(`/asset-returns/${id}/approve`)
  toast.success(t('asset_returns.approved'))
  await fetchAll()
}

async function reject(id) {
  await http.post(`/asset-returns/${id}/reject`)
  toast.success(t('asset_returns.rejected'))
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
      <PageHeader :title="t('asset_returns.title')" :subtitle="t('asset_returns.subtitle')" :buttonText="t('asset_returns.new')" @action="openCreate" />

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
          <select v-model="filters.condition" class="filter-select">
            <option value="">{{ t('asset_returns.condition') }}: {{ t('common.all') }}</option>
            <option value="good">{{ t('asset_returns.condition_good') }}</option>
            <option value="fair">{{ t('asset_returns.condition_fair') }}</option>
            <option value="broken">{{ t('asset_returns.condition_broken') }}</option>
            <option value="lost">{{ t('asset_returns.condition_lost') }}</option>
          </select>
          <button v-if="hasActiveFilters" @click="clearFilters" class="btn-subtle btn-sm">{{ t('common.clear_filters') }}</button>
        </div>
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th class="th-sort" @click="toggleSort('asset')">{{ t('common.asset') }}<TableSortIcon :active="sortKey === 'asset'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('condition')">{{ t('asset_returns.condition') }}<TableSortIcon :active="sortKey === 'condition'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('returned_by')">{{ t('asset_returns.returned_by') }}<TableSortIcon :active="sortKey === 'returned_by'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('status')">{{ t('common.status') }}<TableSortIcon :active="sortKey === 'status'" :direction="sortDir" /></th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in sortedReturns" :key="r.id">
                <td class="font-medium text-fg">{{ r.asset?.name || t('common.n_a') }}</td>
                <td class="capitalize">{{ r.condition }}</td>
                <td>{{ r.returned_by?.name || t('common.n_a') }}</td>
                <td><StatusBadge :status="r.status" /></td>
                <td class="text-right whitespace-nowrap">
                  <template v-if="r.status === 'pending' && auth.user?.role === 'operations_hr_manager'">
                    <div class="flex items-center justify-end gap-1.5">
                      <button @click="approve(r.id)" :title="t('common.approve')" class="w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      </button>
                      <button @click="reject(r.id)" :title="t('common.reject')" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      </button>
                    </div>
                  </template>
                </td>
              </tr>
              <tr v-if="!loading && !sortedReturns.length">
                <td colspan="5" class="py-10 text-center text-faint">{{ t('asset_returns.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <Modal v-if="showModal" :title="t('asset_returns.modal_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_returns.assignment_required') }}</label>
            <select v-model="form.assignment_id" required @change="onAssignmentChange" class="input">
              <option value="">{{ t('asset_returns.select_assignment') }}</option>
              <option v-for="a in assignments" :key="a.id" :value="a.id">{{ a.asset?.name }} — {{ a.recipient_name }}</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_returns.condition_required') }}</label>
            <select v-model="form.condition" class="input">
              <option value="good">{{ t('asset_returns.condition_good') }}</option>
              <option value="fair">{{ t('asset_returns.condition_fair') }}</option>
              <option value="broken">{{ t('asset_returns.condition_broken') }}</option>
              <option value="lost">{{ t('asset_returns.condition_lost') }}</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_returns.return_date_required') }}</label>
            <input v-model="form.return_date" type="date" required class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_returns.damage_notes') }}</label>
            <textarea v-model="form.damage_notes" rows="2" class="input"></textarea>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ t('asset_returns.submit_button') }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>
