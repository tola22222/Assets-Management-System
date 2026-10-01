<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import Modal from '../../components/ui/Modal.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import LocationFilter from '../../components/ui/LocationFilter.vue'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableSort } from '../../composables/useTableSort'
import { useTableFilter } from '../../composables/useTableFilter'
import TablePagination from '../../components/ui/TablePagination.vue'
import { usePagination } from '../../composables/usePagination'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import { useAuthStore } from '../../stores/auth'

// Read-only register: stock rows are recorded through the Asset create/import
// flow, so this page only views them — no issue or delete actions.
const { t } = useI18n()
const auth = useAuthStore()
// HR and the Accountant see how stock moved through transfers.
const isAdmin = computed(() => ['operations_hr_manager', 'finance_manager'].includes(auth.user?.role))

// ---- Transfer history ------------------------------------------------------
// Every transfer that took assets out of stock or brought them back, in the
// order the flow runs: HR sends ticked asset codes → the receiver accepts all
// or some of them (or rejects) → HR returns them, which puts them back in
// stock. Read from /asset-transfers, which already carries each transfer's
// codes and each code's outcome.
const history = ref([])
const historyLoading = ref(false)

async function loadHistory() {
  historyLoading.value = true
  try {
    const { data } = await http.get('/asset-transfers')
    history.value = data.map((r) => {
      const units = r.units?.length ? r.units : (r.asset ? [r.asset] : [])
      const accepted = units.filter((u) => u.pivot?.status === 'accepted').length
      const declined = units.filter((u) => u.pivot?.status === 'declined').length
      const isReturn = !!r.parent_transfer_id
      const status = r.status === 'received' ? (isReturn || r.return_transfer ? 'returned' : 'accepted') : r.status
      return {
        ...r,
        units,
        isReturn,
        historyStatus: status,
        sent: r.quantity || units.length || 1,
        accepted,
        declined,
        date: (r.received_at || r.transfer_date || r.created_at || '').slice(0, 10),
      }
    })
  } catch {
    history.value = []
  } finally {
    historyLoading.value = false
  }
}

const { search: historySearch, filtered: historySearched } = useTableSearch(history, [
  (r) => r.asset?.name, (r) => r.asset?.asset_code, 'recipient_name',
  (r) => r.from_location?.name, (r) => r.to_location?.name,
  (r) => r.units.map((u) => u.asset_code).join(' '),
  (r) => r.requester?.name,
])
// Newest transfer first (by when it was created), like every list in the app.
const historySorted = computed(() => [...historySearched.value].sort((a, b) => (b.created_at || '').localeCompare(a.created_at || '') || b.id - a.id))
const { page: historyPage, rowsPerPage: historyRows, total: historyTotal, paged: historyPaged } = usePagination(historySorted)

// The asset codes on one transfer, and what happened to each.
const historyViewing = ref(null)
const codeState = (u) => (u.pivot?.status === 'declined' ? 'declined' : u.pivot?.status === 'accepted' ? 'accepted' : null)

const items = ref([])
const locationStats = ref([])
const loading = ref(true)

async function fetchAll() {
  loading.value = true
  const { data } = await http.get('/stock-items')
  items.value = data
  loading.value = false
}
async function loadLocationStats() {
  const { data } = await http.get('/stock-items/by-location')
  locationStats.value = data
}

// ---- Total Assets by Location ------------------------------------------
// A live tally of the Asset Register per site (already sorted busiest-first
// server-side), not a stored balance — so it always matches the register.
// The filter bar narrows this card: search by site name/code, or pick one
// site. Each row still shows its `level` badge, which comes from the server
// (Low < 5, Normal 5–19, High 20+ — the same thresholds as the Assets by
// Model report).
const { search, filtered: searchedSites } = useTableSearch(locationStats, ['name', 'code'])
const { filters, filtered: visibleSites } = useTableFilter(searchedSites, {
  location: (row, val) => String(row.location_id) === String(val),
})
const totalAssets = computed(() => visibleSites.value.reduce((sum, l) => sum + l.total, 0))

// ---- Consumables (CSV export only) ----------------------------------------
// The consumables table was removed from this page; the header's Export CSV
// still downloads the list, LOW first. Status is computed server-side per
// item, but sorting it "LOW first" needs a numeric rank — alphabetical
// ('high' < 'low' < 'normal') would put High first.
const STATUS_RANK = { low: 0, normal: 1, high: 2 }
const rankedItems = computed(() => items.value.map((i) => ({ ...i, status_rank: STATUS_RANK[i.status] ?? 1 })))

const { sorted: visible } = useTableSort(rankedItems, {
  defaultKey: 'status_rank', defaultDir: 'asc',
  paths: { location: 'location.name', balance: 'balance', threshold: 'min_threshold', updated: 'updated_at' },
})

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

onMounted(() => {
  fetchAll()
  loadLocationStats()
  if (isAdmin.value) loadHistory()
})
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 space-y-6">

      <!-- Total assets by location: a live tally of the Asset Register per
           site, so a site sitting at zero is shown rather than hidden. -->
      <div class="card p-6 sm:p-8">
        <!-- Page heading, inside the first card like every other list page
             (Assets, Transfers, Verification…), not loose on the canvas. -->
        <!-- A full-width rule under it (negative margins reach the card's
             edges) separates the page header from the section below. -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 -mx-6 sm:-mx-8 px-6 sm:px-8 pb-6 mb-6 border-b border-line">
          <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('stock.title') }}</h1>
          <div class="flex items-center gap-2 flex-shrink-0">
            <button @click="exportCsv" class="btn-ghost btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 12L12 16.5m0 0l4.5-4.5M12 16.5V3" /></svg>
              {{ t('stock.export_csv') }}
            </button>
          </div>
        </div>

        <!-- The total rides beside the section title as a count pill, so the
             right edge holds only the Export button above. -->
        <div class="flex flex-wrap items-center gap-2.5 mb-4">
          <h3 class="font-display text-base font-bold text-fg">{{ t('stock.assets_by_location') }}</h3>
          <span class="badge-neutral gap-1">
            <span class="font-bold text-fg">{{ totalAssets }}</span>
            <span class="lowercase">{{ t('stock.total_assets') }}</span>
          </span>
        </div>

        <div class="flex flex-wrap items-center gap-3 mb-5">
          <div class="flex-1 min-w-[260px]">
            <SearchInput v-model="search" :placeholder="t('stock.search_sites_placeholder')" />
          </div>
          <LocationFilter v-model="filters.location" />
        </div>
        <p class="text-xs text-faint -mt-3 mb-4">{{ t('stock.level_hint') }}</p>

        <!-- Three columns only on very wide screens: at laptop widths a third of
             the card can't fit a site name beside its level badge and count,
             and names like "Banteay Srei HS" were cut to "Banteay Srei …". -->
        <div v-if="visibleSites.length" class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-3 gap-x-6 gap-y-1">
          <div
            v-for="l in visibleSites"
            :key="l.location_id ?? 'unplaced'"
            class="flex items-center justify-between gap-3 px-2.5 py-2 rounded-lg border-b border-line/60"
          >
            <span class="flex items-center gap-2 min-w-0">
              <span v-if="l.code" class="id-chip flex-shrink-0">{{ l.code }}</span>
              <span class="text-sm truncate" :class="l.location_id ? 'font-semibold text-fg' : 'font-semibold text-amber-600 dark:text-amber-400'">
                {{ l.name || t('stock.no_location') }}
              </span>
            </span>
            <span class="flex items-center gap-2 flex-shrink-0">
              <span class="badge" :class="{ 'badge-danger': l.level === 'low', 'badge-success': l.level === 'normal', 'badge-warning': l.level === 'high' }">{{ t(`stock.${l.level}`) }}</span>
              <span class="text-sm font-bold w-8 text-right" :class="l.total ? 'text-fg' : 'text-faint'">{{ l.total }}</span>
            </span>
          </div>
        </div>
        <p v-else class="text-sm text-faint">{{ locationStats.length ? t('stock.no_sites_match') : t('stock.no_locations') }}</p>
      </div>

      <!-- Transfer history (HR / Accountant): stock going out on transfers
           and coming back on returns, newest first. -->
      <div v-if="isAdmin" class="card p-6 sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
          <div>
            <h3 class="font-display text-base font-bold text-fg">{{ t('stock.transfer_history') }}</h3>
            <p class="text-xs text-faint mt-0.5">{{ t('stock.transfer_history_hint') }}</p>
          </div>
          <div class="w-full sm:w-72">
            <SearchInput v-model="historySearch" :placeholder="t('common.search')" />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>{{ t('common.date') }}</th>
                <th>{{ t('stock.movement') }}</th>
                <th>{{ t('common.asset') }}</th>
                <th class="text-right">{{ t('stock.history_qty') }}</th>
                <th>{{ t('stock.history_route') }}</th>
                <th>{{ t('assets.assigned_to') }}</th>
                <th>{{ t('common.status') }}</th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in historyPaged" :key="r.id">
                <td class="whitespace-nowrap text-muted">{{ r.date || '—' }}</td>
                <td>
                  <span class="badge" :class="r.isReturn ? 'badge-success' : 'badge-info'">
                    {{ r.isReturn ? t('stock.movement_in') : t('stock.movement_out') }}
                  </span>
                </td>
                <td class="font-medium text-fg">{{ r.asset?.name || '—' }}</td>
                <td class="text-right whitespace-nowrap">
                  <!-- Accepted of sent, when the receiver left some codes out. -->
                  <template v-if="r.declined">{{ t('stock.accepted_of', { n: r.accepted, total: r.sent }) }}</template>
                  <template v-else>{{ r.isReturn ? '+' : '−' }}{{ r.sent }}</template>
                </td>
                <td class="whitespace-nowrap text-muted">{{ r.from_location?.name || '—' }} → {{ r.to_location?.name || '—' }}</td>
                <td>{{ r.recipient_name || '—' }}</td>
                <td><StatusBadge :status="r.historyStatus" /></td>
                <td class="text-right">
                  <button @click="historyViewing = r" :title="t('asset_transfers.asset_codes')" :aria-label="t('asset_transfers.asset_codes')" class="btn-icon-view">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                  </button>
                </td>
              </tr>
              <tr v-if="!historyLoading && !historySorted.length">
                <td colspan="8" class="py-10 text-center text-faint">{{ t('stock.no_transfer_history') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <TablePagination v-model:page="historyPage" v-model:rows-per-page="historyRows" :count="historyTotal" />
      </div>

    </div>

    <!-- One transfer's asset codes, and what happened to each. -->
    <Modal v-if="historyViewing" :title="t('asset_transfers.asset_codes')" @close="historyViewing = null">
      <div class="modal-body space-y-4">
        <div class="rounded-xl border border-line bg-surface-2 px-3.5 py-3 text-sm">
          <div class="flex items-center justify-between gap-2">
            <p class="font-semibold text-fg">{{ historyViewing.asset?.name }} × {{ historyViewing.sent }}</p>
            <StatusBadge :status="historyViewing.historyStatus" />
          </div>
          <p class="text-muted text-[13px] mt-0.5">
            {{ historyViewing.date }} · {{ historyViewing.from_location?.name }} → {{ historyViewing.to_location?.name }}<template v-if="historyViewing.recipient_name"> · {{ historyViewing.recipient_name }}</template><template v-if="historyViewing.requester?.name"> · {{ t('asset_transfers.requester') }}: {{ historyViewing.requester.name }}</template>
          </p>
          <p v-if="historyViewing.rejection_reason" class="text-[13px] text-muted mt-1">{{ historyViewing.rejection_reason }}</p>
        </div>
        <ul class="divide-y divide-line rounded-xl border border-line max-h-72 overflow-y-auto">
          <li v-for="u in historyViewing.units" :key="u.id" class="flex items-center justify-between gap-3 px-3.5 py-2 text-sm">
            <span class="font-mono text-[13px] text-fg">{{ u.asset_code }}</span>
            <span v-if="codeState(u) === 'declined'" class="badge badge-danger">{{ t('asset_transfers.not_accepted') }}</span>
            <span v-else-if="codeState(u) === 'accepted'" class="badge badge-success">{{ t('status.accepted') }}</span>
            <span v-else class="text-xs text-faint">—</span>
          </li>
        </ul>
      </div>
    </Modal>

  </AppLayout>
</template>
