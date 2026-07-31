<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import FilterPills from '../../components/ui/FilterPills.vue'
import StatCard from '../../components/ui/StatCard.vue'
import DonutChart from '../../components/ui/DonutChart.vue'
import LocationPillCards from '../../components/ui/LocationPillCards.vue'

const reportTypes = [
  { key: 'inventory', label: 'Inventory' },
  { key: 'by-model', label: 'Assets by Model' },
  { key: 'assignments', label: 'Assignments' },
  { key: 'transfers', label: 'Transfers' },
  { key: 'verifications', label: 'Verifications' },
  { key: 'returns', label: 'Returns' },
  { key: 'disposed', label: 'Disposed Assets' },
  { key: 'lost', label: 'Lost Assets' },
  { key: 'locations', label: 'Locations' },
  { key: 'qr-scans', label: 'QR Scans' },
  { key: 'data-completeness', label: 'Data Completeness' },
]

const selected = ref('inventory')
const rows = ref([])
const loading = ref(false)

const columns = {
  inventory: [['asset_code', 'Code'], ['name', 'Name'], ['condition', 'Condition'], ['status', 'Status']],
  assignments: [['asset', 'Asset', (r) => r.asset?.name], ['recipient_name', 'Recipient'], ['status', 'Status']],
  transfers: [['asset', 'Asset', (r) => r.asset?.name], ['status', 'Status'], ['transfer_date', 'Date']],
  verifications: [['asset', 'Asset', (r) => r.asset?.name], ['condition', 'Condition'], ['verified_at', 'Verified At']],
  returns: [['asset', 'Asset', (r) => r.asset?.name], ['condition', 'Condition'], ['status', 'Status']],
  disposed: [['asset_code', 'Code'], ['name', 'Name'], ['condition', 'Condition']],
  lost: [['asset_code', 'Code'], ['name', 'Name'], ['updated_at', 'Last Updated']],
  locations: [['name', 'Name'], ['type', 'Type'], ['assets_count', 'Assets']],
  'qr-scans': [['message', 'Scan'], ['created_at', 'Date']],
  'data-completeness': [['asset_code', 'Code'], ['name', 'Name'], ['category', 'Category', (r) => r.category?.name], ['missing_fields', 'Missing Fields']],
  'by-model': [['name', 'Model'], ['category', 'Category', (r) => r.category?.name], ['total', 'Total Units'], ['stock_level', 'Stock Level']],
}

// Which field represents "when" for each report — drives the Day/Month/Year
// filter below. Reports with no natural date (aggregate/summary reports)
// simply don't get the filter; it's hidden for those.
const dateFields = {
  inventory: 'created_at',
  assignments: 'assigned_date',
  transfers: 'transfer_date',
  verifications: 'verified_at',
  returns: 'return_date',
  disposed: 'updated_at',
  lost: 'updated_at',
  'qr-scans': 'created_at',
}

const STOCK_LEVEL_STYLES = {
  high: 'bg-emerald-50 text-emerald-700',
  medium: 'bg-amber-50 text-amber-700',
  low: 'bg-red-50 text-red-700',
}

const hasDateField = computed(() => !!dateFields[selected.value])

const granularity = ref('all') // all | day | month | year
const periodValue = ref('')
const today = new Date()
const years = Array.from({ length: 6 }, (_, i) => today.getFullYear() - i)
const yearOptions = years.map((y) => ({ value: String(y), label: String(y) }))

function defaultPeriodValue(g) {
  if (g === 'day') return today.toISOString().slice(0, 10)
  if (g === 'month') return today.toISOString().slice(0, 7)
  if (g === 'year') return String(today.getFullYear())
  return ''
}
watch(granularity, (g) => { periodValue.value = defaultPeriodValue(g) })
watch(selected, load)

const completenessStats = ref(null)
async function loadCompletenessStats() {
  const { data } = await http.get('/reports/inventory')
  const total = data.length
  const pct = (pred) => (total ? Math.round((data.filter(pred).length / total) * 100) : 0)
  completenessStats.value = {
    total,
    price: pct((a) => a.purchase_price !== null && a.purchase_price !== undefined),
    date: pct((a) => a.purchase_date !== null && a.purchase_date !== undefined),
    serial: pct((a) => !!a.serial_number),
  }
}

// ---------------------------------------------------------------------------
// Reports overview — a dashboard-style summary (KPI tiles + DonutChart/
// LocationPillCards, same components and visual language as the main
// Dashboard page) shown above the existing report browser below. Fetched
// once on mount, independent of whatever report type/tab is selected in the
// browser, so it always reflects the live register.
// ---------------------------------------------------------------------------
const overviewLoading = ref(true)
const overviewLocationsRaw = ref([])
const overviewInventory = ref([])
const overviewCategoriesRaw = ref([])

async function loadOverview() {
  overviewLoading.value = true
  try {
    const [locRes, invRes, catRes] = await Promise.all([
      http.get('/reports/locations'),
      http.get('/reports/inventory'),
      http.get('/categories'),
    ])
    overviewLocationsRaw.value = locRes.data
    overviewInventory.value = invRes.data
    overviewCategoriesRaw.value = catRes.data
    if (!completenessStats.value) await loadCompletenessStats()
  } finally {
    overviewLoading.value = false
  }
}

// Mirrors Dashboard.vue's own currency formatting so the KPI tiles read consistently across both pages.
function formatCurrency(value) {
  if (value >= 1000) return `$${Math.round(value / 1000)}K`
  return `$${Math.round(value || 0)}`
}
function money(v) {
  return '$' + Number(v || 0).toLocaleString(undefined, { maximumFractionDigits: 0 })
}

const overviewLocationsList = computed(() => overviewLocationsRaw.value.map((r) => ({ location: r.name, total: r.assets_count || 0 })))

// [{ category, count, percentage }] — the exact shape DonutChart already expects (same as Dashboard's assets_by_category).
function toSegments(counts) {
  const total = Object.values(counts).reduce((s, c) => s + c, 0) || 1
  return Object.entries(counts)
    .sort((a, b) => b[1] - a[1])
    .map(([category, count]) => ({ category, count, percentage: Math.round((count / total) * 100) }))
}

const overviewCategorySegments = computed(() => {
  const counts = {}
  overviewInventory.value.forEach((a) => {
    const name = a.category?.name || 'Uncategorized'
    counts[name] = (counts[name] || 0) + 1
  })
  return toSegments(counts)
})

// Same in-use / needs-repair / lost / retiring buckets as the Asset Register page's
// status pill, so "Asset status" here reads consistently with the rest of the app.
function assetBucket(a) {
  if (a.status === 'disposed') return 'retiring'
  if (a.condition === 'lost') return 'lost'
  if (a.condition === 'fair' || a.condition === 'broken') return 'needs_repair'
  return 'in_use'
}
const STATUS_LABELS = { in_use: 'In use', needs_repair: 'Needs repair', lost: 'Lost', retiring: 'Retiring' }
const overviewStatusSegments = computed(() => {
  const counts = { in_use: 0, needs_repair: 0, lost: 0, retiring: 0 }
  overviewInventory.value.forEach((a) => { counts[assetBucket(a)]++ })
  const relabeled = {}
  Object.entries(counts).forEach(([key, count]) => { relabeled[STATUS_LABELS[key]] = count })
  return toSegments(relabeled)
})

const overviewTotalValue = computed(() => overviewInventory.value.reduce((sum, a) => sum + (Number(a.purchase_price) || 0), 0))
const overviewValueSegments = computed(() => {
  const sums = {}
  overviewInventory.value.forEach((a) => {
    if (!a.purchase_price) return
    const name = a.category?.name || 'Uncategorized'
    sums[name] = (sums[name] || 0) + Number(a.purchase_price)
  })
  return toSegments(sums)
})

const pricedCount = computed(() => overviewInventory.value.filter((a) => a.purchase_price !== null && a.purchase_price !== undefined).length)
const pricedPercentage = computed(() => (overviewInventory.value.length ? Math.round((pricedCount.value / overviewInventory.value.length) * 100) : 0))

async function load() {
  loading.value = true
  granularity.value = 'all'
  sortKey.value = null
  const { data } = await http.get(`/reports/${selected.value}`)
  rows.value = data
  if (selected.value === 'data-completeness') await loadCompletenessStats()
  loading.value = false
}

// "Assets by location" bar breakdown — mirrors the report-deck mockup's
// horizontal bar list, built from the same data the table already fetches.
const locationBars = computed(() => {
  if (selected.value !== 'locations') return []
  const max = Math.max(1, ...rows.value.map((r) => r.assets_count || 0))
  return [...rows.value]
    .sort((a, b) => (b.assets_count || 0) - (a.assets_count || 0))
    .map((r) => ({ label: r.name, count: r.assets_count || 0, pct: Math.round(((r.assets_count || 0) / max) * 100) }))
})

// "Data completeness" bar summary — aggregate % of the register with each
// field recorded, alongside the existing per-asset checklist table below it.
const completenessBars = computed(() => {
  const s = completenessStats.value
  if (!s) return []
  return [
    { label: 'Has Asset ID', pct: 100 },
    { label: 'Has purchase price', pct: s.price },
    { label: 'Has purchase date', pct: s.date },
    { label: 'Has serial no.', pct: s.serial },
  ]
})

function cell(row, col) {
  return col[2] ? col[2](row) : row[col[0]]
}

const filteredRows = computed(() => {
  const field = dateFields[selected.value]
  if (!field || granularity.value === 'all' || !periodValue.value) return rows.value

  return rows.value.filter((row) => {
    const raw = row[field]
    if (!raw) return false
    const d = new Date(raw)
    if (isNaN(d)) return false
    if (granularity.value === 'day') return d.toISOString().slice(0, 10) === periodValue.value
    if (granularity.value === 'month') return d.toISOString().slice(0, 7) === periodValue.value
    if (granularity.value === 'year') return String(d.getFullYear()) === periodValue.value
    return true
  })
})

const sortKey = ref(null)
const sortDir = ref('asc')
function toggleSort(colKey) {
  if (sortKey.value === colKey) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = colKey
    sortDir.value = 'asc'
  }
}

const sortedRows = computed(() => {
  if (!sortKey.value) return filteredRows.value
  const col = columns[selected.value].find((c) => c[0] === sortKey.value)
  if (!col) return filteredRows.value

  return [...filteredRows.value].sort((a, b) => {
    let av = cell(a, col) ?? ''
    let bv = cell(b, col) ?? ''
    if (typeof av === 'string') av = av.toLowerCase()
    if (typeof bv === 'string') bv = bv.toLowerCase()
    if (av < bv) return sortDir.value === 'asc' ? -1 : 1
    if (av > bv) return sortDir.value === 'asc' ? 1 : -1
    return 0
  })
})

function exportCsv() {
  const cols = columns[selected.value]
  const lines = [cols.map((c) => c[1]).join(',')]
  sortedRows.value.forEach((row) => {
    lines.push(cols.map((c) => `"${String(cell(row, c) ?? '').replace(/"/g, '""')}"`).join(','))
  })
  const blob = new Blob([lines.join('\n')], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  const suffix = granularity.value !== 'all' && periodValue.value ? `-${periodValue.value}` : ''
  a.download = `${selected.value}-report${suffix}-${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(() => {
  load()
  loadOverview()
})
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 space-y-6">

      <!-- Page heading — same layout as Dashboard.vue's own header -->
      <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
          <h1 class="font-display text-3xl sm:text-4xl font-semibold text-fg tracking-tight">Reports</h1>
          <p class="text-muted text-sm mt-2">Live roll-ups across the asset register</p>
        </div>
        <button @click="exportCsv" class="btn-ghost flex-shrink-0 mt-1 sm:mt-0">
          <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
          Export CSV
        </button>
      </div>

      <div v-if="overviewLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="card p-5 h-24 animate-pulse"></div>
      </div>

      <template v-else>
        <!-- KPI row — StatCard, identical component to the Dashboard page -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard :value="overviewInventory.length" label="Total assets" />
          <StatCard :value="overviewCategoriesRaw.length" label="Categories" />
          <StatCard :value="overviewLocationsRaw.length" label="Sites" />
          <StatCard
            :value="formatCurrency(overviewTotalValue)"
            :label="pricedCount < overviewInventory.length ? `Recorded value (${overviewInventory.length - pricedCount} unpriced)` : 'Recorded value'"
            :badge="`${pricedPercentage}% priced`"
          />
        </div>

        <!-- Asset categories + Asset status — DonutChart, same component as Dashboard's "by category" card -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div class="card p-6">
            <h2 class="font-display text-lg font-bold text-fg">Assets by category</h2>
            <p class="text-sm text-faint mb-6">Breakdown of what PEPY owns</p>
            <DonutChart v-if="overviewCategorySegments.length" :segments="overviewCategorySegments" :total="overviewInventory.length" />
            <p v-else class="text-sm text-faint">No assets yet.</p>
          </div>

          <div class="card p-6">
            <h2 class="font-display text-lg font-bold text-fg">Asset status</h2>
            <p class="text-sm text-faint mb-6">Condition &amp; lifecycle at a glance</p>
            <DonutChart v-if="overviewInventory.length" :segments="overviewStatusSegments" :total="overviewInventory.length" total-label="assets" />
            <p v-else class="text-sm text-faint">No assets yet.</p>
          </div>
        </div>

        <!-- Assets by location — LocationPillCards, same component as Dashboard's "by location" card -->
        <div class="card p-6">
          <h2 class="font-display text-lg font-bold text-fg">Assets by location</h2>
          <p class="text-sm text-faint mb-6">Office &amp; Learning Center holds the largest share; each school carries its own working set</p>
          <LocationPillCards :locations="overviewLocationsList" />
        </div>

        <!-- Asset value by category — a third DonutChart, currency-formatted -->
        <div class="card p-6">
          <h2 class="font-display text-lg font-bold text-fg">Asset value by category</h2>
          <p class="text-sm text-faint mb-6">{{ money(overviewTotalValue) }} recorded across {{ overviewInventory.length }} assets</p>
          <DonutChart
            v-if="overviewValueSegments.length"
            :segments="overviewValueSegments"
            :total="overviewTotalValue"
            total-label="total value"
            :format-value="money"
          />
          <p v-else class="text-sm text-faint">No priced assets yet.</p>
        </div>

        <!-- Data completeness — plain progress bars, same treatment as the browser's own version below -->
        <div class="card p-6">
          <h2 class="font-display text-lg font-bold text-fg">Data completeness</h2>
          <p class="text-sm text-faint mb-6">Fields recorded across {{ completenessStats?.total ?? 0 }} assets</p>
          <div v-for="b in completenessBars" :key="b.label" class="flex items-center gap-3 mb-3 last:mb-0">
            <span class="w-36 sm:w-44 flex-shrink-0 text-sm font-semibold text-fg truncate">{{ b.label }}</span>
            <div class="flex-1 h-2.5 rounded-full bg-surface-2 border border-line overflow-hidden">
              <div class="h-full rounded-full bg-brand transition-all" :style="{ width: b.pct + '%' }"></div>
            </div>
            <span class="w-10 text-right text-sm font-semibold text-fg flex-shrink-0">{{ b.pct }}%</span>
          </div>
        </div>
      </template>

      <!-- Detailed report browser (unchanged functionality — tabs, sort, date filter, CSV export) -->
      <div class="card p-6 space-y-6">
        <h2 class="font-display text-lg font-bold text-fg">Detailed reports</h2>

        <div class="flex items-center gap-2 overflow-x-auto pb-1">
          <button v-for="t in reportTypes" :key="t.key" @click="selected = t.key"
            class="filter-pill flex-shrink-0" :class="{ 'filter-pill-active': selected === t.key }">
            {{ t.label }}
          </button>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div class="card p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-brand/10 text-brand flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
            </div>
            <div>
              <p class="font-display text-2xl font-bold text-fg leading-none">{{ rows.length }}</p>
              <p class="text-xs text-muted mt-1">Total records</p>
            </div>
          </div>
          <div class="card p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <div>
              <p class="font-display text-2xl font-bold text-fg leading-none">{{ sortedRows.length }}</p>
              <p class="text-xs text-muted mt-1">{{ granularity === 'all' ? 'Showing' : 'Matching period' }}</p>
            </div>
          </div>
          <div class="card p-4 col-span-2 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
            </div>
            <p class="text-sm text-muted">{{ reportTypes.find((t) => t.key === selected)?.label }}{{ granularity !== 'all' && periodValue ? ` — ${periodValue}` : '' }}</p>
          </div>
        </div>

        <!-- Assets by location — horizontal bar breakdown, no raw table needed. -->
        <div v-if="selected === 'locations'" class="card p-5 sm:p-6">
          <h3 class="font-display text-base font-bold text-fg">Assets by location</h3>
          <p class="text-xs text-faint mt-0.5 mb-6">{{ locationBars.length }} sites by item count</p>
          <div v-for="b in locationBars" :key="b.label" class="flex items-center gap-3 mb-3 last:mb-0">
            <span class="w-36 sm:w-44 flex-shrink-0 text-sm font-semibold text-fg truncate">{{ b.label }}</span>
            <div class="flex-1 h-2.5 rounded-full bg-surface-2 border border-line overflow-hidden">
              <div class="h-full rounded-full bg-brand transition-all" :style="{ width: b.pct + '%' }"></div>
            </div>
            <span class="w-10 text-right text-sm font-semibold text-fg flex-shrink-0">{{ b.count }}</span>
          </div>
          <p v-if="!loading && !locationBars.length" class="text-sm text-faint text-center py-6">No data for this report.</p>
        </div>

        <template v-else>
          <!-- Data completeness — aggregate bar summary above the per-asset checklist. -->
          <div v-if="selected === 'data-completeness'" class="card p-5 sm:p-6">
            <h3 class="font-display text-base font-bold text-fg">Data completeness</h3>
            <p class="text-xs text-faint mt-0.5 mb-6">Fields recorded across {{ completenessStats?.total ?? 0 }} assets</p>
            <div v-for="b in completenessBars" :key="b.label" class="flex items-center gap-3 mb-3 last:mb-0">
              <span class="w-36 sm:w-44 flex-shrink-0 text-sm font-semibold text-fg truncate">{{ b.label }}</span>
              <div class="flex-1 h-2.5 rounded-full bg-surface-2 border border-line overflow-hidden">
                <div class="h-full rounded-full bg-brand transition-all" :style="{ width: b.pct + '%' }"></div>
              </div>
              <span class="w-10 text-right text-sm font-semibold text-fg flex-shrink-0">{{ b.pct }}%</span>
            </div>
          </div>

          <div class="table-wrap">
            <div class="table-toolbar">
              <template v-if="hasDateField">
                <div class="flex items-center gap-1 bg-surface-2 rounded-xl p-1">
                  <button
                    v-for="g in ['all', 'day', 'month', 'year']" :key="g"
                    @click="granularity = g"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold capitalize transition"
                    :class="granularity === g ? 'bg-brand text-white' : 'text-muted hover:text-fg'"
                  >
                    {{ g }}
                  </button>
                </div>
                <input v-if="granularity === 'day'" v-model="periodValue" type="date" class="filter-select" />
                <input v-else-if="granularity === 'month'" v-model="periodValue" type="month" class="filter-select" />
                <FilterPills v-else-if="granularity === 'year'" v-model="periodValue" :options="yearOptions" hide-all />
              </template>
            </div>
            <div class="overflow-x-auto">
              <table class="data-table">
                <thead>
                  <tr>
                    <th v-for="col in columns[selected]" :key="col[0]" class="th-sort" @click="toggleSort(col[0])">
                      {{ col[1] }}<TableSortIcon :active="sortKey === col[0]" :direction="sortDir" />
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, i) in sortedRows" :key="i">
                    <td v-for="col in columns[selected]" :key="col[0]">
                      <span v-if="col[0] === 'stock_level'" class="px-2.5 py-1 rounded-full text-xs font-bold capitalize" :class="STOCK_LEVEL_STYLES[cell(row, col)] ?? ''">
                        {{ cell(row, col) }}
                      </span>
                      <span v-else-if="col[0] === 'asset_code'" class="id-chip">{{ cell(row, col) }}</span>
                      <template v-else>{{ cell(row, col) }}</template>
                    </td>
                  </tr>
                  <tr v-if="!loading && !sortedRows.length">
                    <td :colspan="columns[selected].length" class="py-10 text-center text-faint">
                      {{ granularity !== 'all' ? 'No records in this period.' : 'No data for this report.' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </template>
      </div>

    </div>
  </AppLayout>
</template>
