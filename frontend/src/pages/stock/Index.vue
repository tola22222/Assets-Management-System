<script setup>
import { ref, computed, onMounted, reactive, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import FilterPills from '../../components/ui/FilterPills.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableSort } from '../../composables/useTableSort'
import { useTableFilter } from '../../composables/useTableFilter'
import { useBulkSelect } from '../../composables/useBulkSelect'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'

const { t } = useI18n()
const toast = useToastStore()
const auth = useAuthStore()
const route = useRoute()
const canManage = computed(() => ['operations_hr_manager', 'finance_manager'].includes(auth.user?.role))

const items = ref([])
const locations = ref([])
const locationStats = ref([])
const loading = ref(true)

async function fetchAll() {
  loading.value = true
  try {
    const { data } = await http.get('/stock-items')
    items.value = data
  } catch (e) {
    items.value = []
    toast.error(errorMessage(e, t('stock.load_failed')))
  } finally {
    loading.value = false
  }
}
async function loadLocations() {
  try {
    const { data } = await http.get('/locations')
    locations.value = data
  } catch {
    // The location filter just stays on "All locations"; the grid still works.
    locations.value = []
  }
}
async function loadLocationStats() {
  try {
    const { data } = await http.get('/stock-items/by-location')
    locationStats.value = data
  } catch {
    locationStats.value = []
  }
}

// ---- Total Assets by Location ------------------------------------------
// A live tally of the Asset Register per site (already sorted busiest-first
// server-side), not a stored balance — so it always matches the register.
const totalAssets = computed(() => locationStats.value.reduce((sum, l) => sum + l.total, 0))

// Status is computed server-side per item, but sorting it "LOW first" needs
// a numeric rank — alphabetical ('high' < 'low' < 'normal') would put High first.
const STATUS_RANK = { low: 0, normal: 1, high: 2 }
const rankedItems = computed(() => items.value.map((i) => ({ ...i, status_rank: STATUS_RANK[i.status] ?? 1 })))

const { search, filtered: searched } = useTableSearch(rankedItems, ['name', 'stock_code', 'category'])
const { sortKey, sortDir, toggleSort, sorted: sortedItems } = useTableSort(searched, {
  defaultKey: 'status_rank', defaultDir: 'asc',
  paths: { location: 'location.name', balance: 'balance', threshold: 'min_threshold', updated: 'updated_at' },
})
const { filters, filtered: visible } = useTableFilter(sortedItems, {
  status: (row, val) => row.status === val,
  location: (row, val) => String(row.location_id) === String(val),
})
const statusOptions = computed(() => [
  { value: 'low', label: t('stock.low') },
  { value: 'normal', label: t('stock.normal') },
  { value: 'high', label: t('stock.high') },
])

// Sidebar sub-links (Low Stock / Normal / High) land here as /stock?status=X —
// keep the filter pill in sync with that, including when clicking between
// them without a full page reload (Vue Router reuses this component instance).
watch(() => route.query.status, (v) => { filters.status = v || '' }, { immediate: true })

const { selectedIds, allSelected, toggleSelectAll, toggleSelect, clearSelection } = useBulkSelect(visible)
const confirmingBulkDelete = ref(false)

const locationFilterLabel = computed(() => {
  const loc = locations.value.find((l) => String(l.id) === String(filters.location))
  return loc?.name
})

// ---- Issue Stock --------------------------------------------------------
const issuing = ref(null) // the stock item being issued
const issueSubmitting = ref(false)
const issueForm = reactive({ quantity: '', reason: '', transaction_date: new Date().toISOString().slice(0, 10) })

function openIssue(item) {
  issuing.value = item
  Object.assign(issueForm, { quantity: '', reason: '', transaction_date: new Date().toISOString().slice(0, 10) })
}

async function submitIssue() {
  issueSubmitting.value = true
  try {
    await http.post(`/stock-items/${issuing.value.id}/issue`, issueForm)
    toast.success(t('stock.issued_message', { quantity: issueForm.quantity, unit: issuing.value.unit, name: issuing.value.name }))
    issuing.value = null
    await fetchAll()
  } catch (e) {
    toast.error(errorMessage(e, t('stock.issue_failed')))
  } finally {
    issueSubmitting.value = false
  }
}

// ---- Detail / transaction history ---------------------------------------
const viewing = ref(null)
async function openDetail(item) {
  try {
    const { data } = await http.get(`/stock-items/${item.id}`)
    viewing.value = data
  } catch (e) {
    // Clicking a row did nothing at all when this failed.
    toast.error(errorMessage(e, t('stock.detail_failed')))
  }
}

// ---- Delete ---------------------------------------------------------------
const deletingId = ref(null)
async function confirmDelete() {
  try {
    await http.delete(`/stock-items/${deletingId.value}`)
    toast.success(t('stock.item_deleted'))
    await fetchAll()
  } catch (e) {
    toast.error(errorMessage(e, t('stock.delete_failed')))
  } finally {
    deletingId.value = null
  }
}

async function confirmBulkDelete() {
  confirmingBulkDelete.value = false
  const ids = selectedIds.value
  const results = await Promise.allSettled(ids.map((id) => http.delete(`/stock-items/${id}`)))
  const failed = results.filter((r) => r.status === 'rejected').length
  if (failed) toast.error(t('stock.bulk_delete_partial_failed', { count: failed }))
  toast.success(t('stock.bulk_deleted', { count: ids.length - failed }))
  clearSelection()
  await fetchAll()
}

// ---- CSV export -----------------------------------------------------------
function exportCsv() {
  const cols = [
    ['stock_code', t('stock.stock_id')], ['name', t('stock.item_name')], ['category', t('stock.category')],
    ['location', t('common.location')], ['balance', t('stock.balance')], ['unit', t('stock.unit')],
    ['status', t('common.status')], ['min_threshold', t('stock.min_threshold')], ['updated_at', t('stock.last_transaction')],
  ]
  const row = (i) => ({
    stock_code: i.stock_code, name: i.name, category: i.category || '',
    location: i.location?.name || '', balance: i.balance, unit: i.unit,
    status: i.status, min_threshold: i.min_threshold ?? '', updated_at: i.updated_at,
  })
  const lines = [cols.map(([, label]) => label).join(',')]
  visible.value.forEach((i) => {
    const r = row(i)
    lines.push(cols.map(([key]) => `"${String(r[key] ?? '').replace(/"/g, '""')}"`).join(','))
  })
  const blob = new Blob([lines.join('\n')], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `stock-grid-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

function formatDate(v) {
  return v ? new Date(v).toLocaleString() : '—'
}

onMounted(() => {
  fetchAll()
  loadLocations()
  loadLocationStats()
})
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 space-y-6">

      <!-- Page heading -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('stock.title') }}</h1>
          <p class="text-muted text-sm mt-1">{{ t('stock.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <button v-if="canManage && selectedIds.length" @click="confirmingBulkDelete = true" class="btn-danger btn-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
            {{ t('stock.delete_selected', { count: selectedIds.length }) }}
          </button>
          <button @click="exportCsv" class="btn-ghost btn-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 12L12 16.5m0 0l4.5-4.5M12 16.5V3" /></svg>
            {{ t('stock.export_csv') }}
          </button>
        </div>
      </div>

      <!-- Total assets by location: a live tally of the Asset Register per
           site, so a site sitting at zero is shown rather than hidden. -->
      <div class="card p-5 sm:p-6">
        <div class="flex items-center justify-between gap-4 mb-4">
          <h3 class="font-display text-base font-bold text-fg">{{ t('stock.assets_by_location') }}</h3>
          <div class="text-right flex-shrink-0">
            <p class="font-display text-2xl font-bold text-fg leading-none">{{ totalAssets }}</p>
            <p class="text-xs text-faint mt-1">{{ t('stock.total_assets') }}</p>
          </div>
        </div>

        <div v-if="locationStats.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-1">
          <div
            v-for="l in locationStats"
            :key="l.location_id ?? 'unplaced'"
            class="flex items-center justify-between gap-3 px-2.5 py-2 rounded-lg border-b border-line/60"
          >
            <span class="flex items-center gap-2 min-w-0">
              <span v-if="l.code" class="id-chip flex-shrink-0">{{ l.code }}</span>
              <span class="text-sm truncate" :class="l.location_id ? 'font-semibold text-fg' : 'font-semibold text-amber-600 dark:text-amber-400'">
                {{ l.name || t('stock.no_location') }}
              </span>
            </span>
            <span class="text-sm font-bold flex-shrink-0" :class="l.total ? 'text-fg' : 'text-faint'">{{ l.total }}</span>
          </div>
        </div>
        <p v-else class="text-sm text-faint">{{ t('stock.no_locations') }}</p>
      </div>

      <!-- Grid -->
      <div class="card p-6 sm:p-8">
        <div class="flex flex-wrap items-center gap-3 mb-6">
          <div class="flex-1 min-w-[260px]">
            <SearchInput v-model="search" :placeholder="t('stock.search_placeholder')" />
          </div>
          <select v-model="filters.location" class="filter-select">
            <option value="">{{ t('stock.all_locations') }}</option>
            <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
          </select>
          <FilterPills v-model="filters.status" :options="statusOptions" />
        </div>

        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th v-if="canManage" class="w-10">
                  <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-line text-brand focus:ring-brand/30" />
                </th>
                <th>{{ t('stock.stock_id') }}</th>
                <th class="th-sort" @click="toggleSort('name')">{{ t('stock.item_name') }}<TableSortIcon :active="sortKey === 'name'" :direction="sortDir" /></th>
                <th>{{ t('stock.category') }}</th>
                <th class="th-sort" @click="toggleSort('location')">{{ t('common.location') }}<TableSortIcon :active="sortKey === 'location'" :direction="sortDir" /></th>
                <th class="th-sort text-right" @click="toggleSort('balance')">{{ t('stock.balance') }}<TableSortIcon :active="sortKey === 'balance'" :direction="sortDir" /></th>
                <th>{{ t('stock.unit') }}</th>
                <th class="th-sort text-center" @click="toggleSort('status_rank')">{{ t('common.status') }}<TableSortIcon :active="sortKey === 'status_rank'" :direction="sortDir" /></th>
                <th class="th-sort text-right" @click="toggleSort('threshold')">{{ t('stock.min_threshold') }}<TableSortIcon :active="sortKey === 'threshold'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('updated')">{{ t('stock.last_transaction') }}<TableSortIcon :active="sortKey === 'updated'" :direction="sortDir" /></th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in visible" :key="i.id" class="cursor-pointer" @click="openDetail(i)">
                <td v-if="canManage" @click.stop>
                  <input type="checkbox" :checked="selectedIds.includes(i.id)" @change="toggleSelect(i.id)" class="rounded border-line text-brand focus:ring-brand/30" />
                </td>
                <td class="whitespace-nowrap"><span class="id-chip">{{ i.stock_code }}</span></td>
                <td class="font-medium text-fg">{{ i.name }}</td>
                <td><span class="tag">{{ i.category || '—' }}</span></td>
                <td class="text-muted">{{ i.location?.name || '—' }}</td>
                <td class="font-medium text-fg text-right">{{ i.balance }}</td>
                <td class="text-muted">{{ i.unit }}</td>
                <td class="text-center">
                  <span class="badge" :class="{ 'badge-danger': i.status === 'low', 'badge-success': i.status === 'normal', 'badge-warning': i.status === 'high' }">
                    {{ i.status }}
                  </span>
                </td>
                <td class="text-right text-muted">{{ i.min_threshold ?? '—' }}</td>
                <td class="text-muted whitespace-nowrap">{{ formatDate(i.updated_at) }}</td>
                <td class="text-right" @click.stop>
                  <div v-if="canManage" class="flex items-center justify-end gap-1.5">
                    <button @click="openIssue(i)" :title="t('stock.issue')" class="w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                    </button>
                    <button @click="deletingId = i.id" :title="t('common.delete')" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                    </button>
                  </div>
                  <span v-else class="text-faint text-xs">—</span>
                </td>
              </tr>
              <tr v-if="!loading && !visible.length">
                <td :colspan="canManage ? 11 : 10" class="py-10 text-center text-faint">
                  {{ locationFilterLabel ? t('stock.empty_for_location', { location: locationFilterLabel }) : (search || filters.status ? t('stock.empty_search') : t('stock.empty')) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Issue Stock -->
    <Modal v-if="issuing" :title="t('stock.issue_stock')" @close="issuing = null">
      <form @submit.prevent="submitIssue">
        <div class="p-6 space-y-4">
          <p class="text-sm text-muted">{{ issuing.name }} <span class="font-mono text-xs text-faint">({{ issuing.stock_code }})</span> — {{ t('stock.on_hand', { balance: issuing.balance, unit: issuing.unit }) }}</p>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('stock.quantity_to_issue') }} <span class="text-red-500">*</span></label>
            <input v-model="issueForm.quantity" type="number" step="0.01" min="0.01" :max="issuing.balance" required class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('stock.issued_to') }}</label>
            <input v-model="issueForm.reason" class="input" :placeholder="t('stock.issued_to_placeholder')" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('common.date') }}</label>
            <input v-model="issueForm.transaction_date" type="date" class="input" />
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" :disabled="issueSubmitting" class="btn-primary">
            {{ issueSubmitting ? t('stock.saving') : t('stock.issue_stock') }}
          </button>
          <button type="button" class="btn-ghost" @click="issuing = null">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <!-- Detail / transaction history -->
    <Modal v-if="viewing" :title="t('stock.transaction_history')" wide @close="viewing = null">
      <div class="p-6 space-y-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h4 class="text-xl font-bold text-fg">{{ viewing.name }}</h4>
            <div class="mt-1.5 flex items-center gap-2">
              <span class="id-chip">{{ viewing.stock_code }}</span>
              <span class="badge" :class="{ 'badge-danger': viewing.status === 'low', 'badge-success': viewing.status === 'normal', 'badge-warning': viewing.status === 'high' }">{{ viewing.status }}</span>
            </div>
          </div>
          <div class="text-right flex-shrink-0">
            <p class="font-display text-2xl font-bold text-fg">{{ viewing.balance }} {{ viewing.unit }}</p>
            <p class="text-xs text-faint">{{ viewing.location?.name }}</p>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>{{ t('common.date') }}</th>
                <th>{{ t('stock.type') }}</th>
                <th class="text-right">{{ t('common.quantity') }}</th>
                <th>{{ t('stock.source_reason') }}</th>
                <th>{{ t('stock.recorded_by') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tx in viewing.transactions" :key="tx.id">
                <td>{{ tx.transaction_date }}</td>
                <td><span class="badge" :class="tx.type === 'in' ? 'badge-success' : 'badge-info'">{{ tx.type === 'in' ? t('stock.stock_in') : t('stock.stock_out') }}</span></td>
                <td class="text-right font-medium" :class="tx.type === 'in' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                  {{ tx.type === 'in' ? '+' : '-' }}{{ tx.quantity }}
                </td>
                <td>{{ tx.reason || '—' }}</td>
                <td>{{ tx.recorded_by?.name || '—' }}</td>
              </tr>
              <tr v-if="!viewing.transactions?.length">
                <td colspan="5" class="py-8 text-center text-faint">{{ t('stock.no_transactions') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
    <ConfirmDialog
      v-if="confirmingBulkDelete"
      :title="t('stock.confirm_bulk_delete_title', { count: selectedIds.length })"
      :message="t('stock.items_with_history_skipped')"
      @confirm="confirmBulkDelete"
      @cancel="confirmingBulkDelete = false"
    />
  </AppLayout>
</template>
