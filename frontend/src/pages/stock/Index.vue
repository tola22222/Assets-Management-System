<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import Modal from '../../components/ui/Modal.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import LocationFilter from '../../components/ui/LocationFilter.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableSort } from '../../composables/useTableSort'
import { useTableFilter } from '../../composables/useTableFilter'
import TablePagination from '../../components/ui/TablePagination.vue'
import { usePagination } from '../../composables/usePagination'

// Read-only register: stock rows are recorded through the Asset create/import
// flow, so this page only views them — no issue or delete actions.
const { t } = useI18n()

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

// ---- Consumables table ----------------------------------------------------
// Status is computed server-side per item, but sorting it "LOW first" needs
// a numeric rank — alphabetical ('high' < 'low' < 'normal') would put High first.
const STATUS_RANK = { low: 0, normal: 1, high: 2 }
const rankedItems = computed(() => items.value.map((i) => ({ ...i, status_rank: STATUS_RANK[i.status] ?? 1 })))

const { sortKey, sortDir, toggleSort, sorted: visible } = useTableSort(rankedItems, {
  defaultKey: 'status_rank', defaultDir: 'asc',
  paths: { location: 'location.name', balance: 'balance', threshold: 'min_threshold', updated: 'updated_at' },
})

// ---- Detail / transaction history ---------------------------------------
const viewing = ref(null)
async function openDetail(item) {
  const { data } = await http.get(`/stock-items/${item.id}`)
  viewing.value = data
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
// The grid shows just the day, so the column stays narrow enough for the table
// to fit its card; the full timestamp is kept in the cell's tooltip.
function formatDay(v) {
  return v ? new Date(v).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
}
// Quantities are stored as decimals (3.50), but most units are counted whole —
// show "3.5" and "2" rather than "3.50" and "2.00".
function formatQty(v) {
  if (v === null || v === undefined || v === '') return '—'
  const n = Number(v)
  return Number.isFinite(n) ? n.toLocaleString(undefined, { maximumFractionDigits: 2 }) : v
}

onMounted(() => {
  fetchAll()
  loadLocationStats()
})

// Pagination is the last step, applied to the finished list, so search
// and sort still consider every row rather than just the page on screen.
const { page, rowsPerPage, total, paged } = usePagination(visible)
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

      <!-- Grid -->
      <div class="card p-6 sm:p-8">

        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>{{ t('stock.stock_id') }}</th>
                <th class="th-sort" @click="toggleSort('name')">{{ t('stock.item_name') }}<TableSortIcon :active="sortKey === 'name'" :direction="sortDir" /></th>
                <th>{{ t('stock.category') }}</th>
                <th class="th-sort" @click="toggleSort('location')">{{ t('common.location') }}<TableSortIcon :active="sortKey === 'location'" :direction="sortDir" /></th>
                <th class="th-sort text-right" @click="toggleSort('balance')">{{ t('stock.balance') }}<TableSortIcon :active="sortKey === 'balance'" :direction="sortDir" /></th>
                <th>{{ t('stock.unit') }}</th>
                <th class="th-sort text-center" @click="toggleSort('status_rank')">{{ t('common.status') }}<TableSortIcon :active="sortKey === 'status_rank'" :direction="sortDir" /></th>
                <th class="th-sort text-right" @click="toggleSort('threshold')">{{ t('stock.min_threshold') }}<TableSortIcon :active="sortKey === 'threshold'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('updated')">{{ t('stock.last_transaction') }}<TableSortIcon :active="sortKey === 'updated'" :direction="sortDir" /></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in paged" :key="i.id" class="cursor-pointer" @click="openDetail(i)">
                <td class="whitespace-nowrap"><span class="id-chip">{{ i.stock_code }}</span></td>
                <td class="font-medium text-fg min-w-[9rem]">{{ i.name }}</td>
                <td class="whitespace-nowrap"><span class="tag">{{ i.category || '—' }}</span></td>
                <td class="text-muted whitespace-nowrap">{{ i.location?.name || '—' }}</td>
                <td class="font-medium text-fg text-right">{{ formatQty(i.balance) }}</td>
                <td class="text-muted whitespace-nowrap">{{ i.unit }}</td>
                <td class="text-center">
                  <span class="badge" :class="{ 'badge-danger': i.status === 'low', 'badge-success': i.status === 'normal', 'badge-warning': i.status === 'high' }">
                    {{ t(`stock.${i.status}`) }}
                  </span>
                </td>
                <td class="text-right text-muted">{{ formatQty(i.min_threshold) }}</td>
                <td class="text-muted whitespace-nowrap" :title="formatDate(i.updated_at)">{{ formatDay(i.updated_at) }}</td>
              </tr>
              <tr v-if="!loading && !visible.length">
                <td colspan="9" class="py-10 text-center text-faint">
                  {{ t('stock.empty') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <TablePagination v-model:page="page" v-model:rows-per-page="rowsPerPage" :count="total" />
      </div>
    </div>

    <!-- Detail / transaction history -->
    <Modal v-if="viewing" :title="t('stock.transaction_history')" wide @close="viewing = null">
      <div class="modal-body space-y-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h4 class="text-xl font-bold text-fg">{{ viewing.name }}</h4>
            <div class="mt-1.5 flex items-center gap-2">
              <span class="id-chip">{{ viewing.stock_code }}</span>
              <span class="badge" :class="{ 'badge-danger': viewing.status === 'low', 'badge-success': viewing.status === 'normal', 'badge-warning': viewing.status === 'high' }">{{ t(`stock.${viewing.status}`) }}</span>
            </div>
          </div>
          <div class="text-right flex-shrink-0">
            <p class="font-display text-2xl font-bold text-fg">{{ formatQty(viewing.balance) }} {{ viewing.unit }}</p>
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
                  {{ tx.type === 'in' ? '+' : '-' }}{{ formatQty(tx.quantity) }}
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

  </AppLayout>
</template>
