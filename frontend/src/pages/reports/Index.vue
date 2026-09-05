<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import Modal from '../../components/ui/Modal.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import FilterPills from '../../components/ui/FilterPills.vue'
import StatCard from '../../components/ui/StatCard.vue'
import DonutChart from '../../components/ui/DonutChart.vue'
import LocationPillCards from '../../components/ui/LocationPillCards.vue'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'
import TablePagination from '../../components/ui/TablePagination.vue'
import { usePagination } from '../../composables/usePagination'

const { t } = useI18n()
const toast = useToastStore()
const auth = useAuthStore()

const showEmailModal = ref(false)
const emailAddress = ref('')
const emailSending = ref(false)

function openEmailModal() {
  emailAddress.value = auth.user?.email || ''
  showEmailModal.value = true
}

async function sendReportEmail() {
  emailSending.value = true
  try {
    await http.post('/reports/email', { email: emailAddress.value })
    toast.success(t('reports.email_sent', { email: emailAddress.value }))
    showEmailModal.value = false
  } catch (e) {
    toast.error(errorMessage(e, t('reports.email_send_failed')))
  } finally {
    emailSending.value = false
  }
}

// computed, not a plain array: the Settings language picker swaps the locale
// live, and a t() call baked into a module-scope constant is evaluated once at
// setup — the labels would stay in the previous language until a page reload.
const reportTypes = computed(() => [
  { key: 'inventory', label: t('reports.type_inventory') },
  { key: 'by-model', label: t('reports.type_by_model') },
  { key: 'assignments', label: t('reports.type_assignments') },
  { key: 'transfers', label: t('reports.type_transfers') },
  { key: 'verifications', label: t('reports.type_verifications') },
  { key: 'returns', label: t('reports.type_returns') },
  { key: 'disposed', label: t('reports.type_disposed') },
  { key: 'lost', label: t('reports.type_lost') },
  { key: 'locations', label: t('reports.type_locations') },
  { key: 'qr-scans', label: t('reports.type_qr_scans') },
  { key: 'data-completeness', label: t('reports.type_data_completeness') },
])

const selected = ref('inventory')
const rows = ref([])
const loading = ref(false)

const columns = computed(() => ({
  inventory: [['asset_code', t('reports.col_code')], ['name', t('reports.col_name')], ['condition', t('reports.col_condition')], ['status', t('common.status')]],
  assignments: [['asset', t('common.asset'), (r) => r.asset?.name], ['recipient_name', t('reports.col_recipient')], ['status', t('common.status')]],
  transfers: [['asset', t('common.asset'), (r) => r.asset?.name], ['status', t('common.status')], ['transfer_date', t('common.date')]],
  verifications: [['asset', t('common.asset'), (r) => r.asset?.name], ['condition', t('reports.col_condition')], ['verified_at', t('reports.col_verified_at')]],
  returns: [['asset', t('common.asset'), (r) => r.asset?.name], ['condition', t('reports.col_condition')], ['status', t('common.status')]],
  disposed: [['asset_code', t('reports.col_code')], ['name', t('reports.col_name')], ['condition', t('reports.col_condition')]],
  lost: [['asset_code', t('reports.col_code')], ['name', t('reports.col_name')], ['updated_at', t('reports.col_last_updated')]],
  locations: [['name', t('reports.col_name')], ['type', t('reports.col_type')], ['assets_count', t('reports.col_assets')]],
  'qr-scans': [['message', t('reports.col_scan')], ['created_at', t('common.date')]],
  'data-completeness': [['asset_code', t('reports.col_code')], ['name', t('reports.col_name')], ['category', t('reports.col_category'), (r) => r.category?.name], ['missing_fields', t('reports.col_missing_fields')]],
  'by-model': [['name', t('reports.col_model')], ['category', t('reports.col_category'), (r) => r.category?.name], ['total', t('reports.col_total_units')], ['stock_level', t('reports.col_stock_level')]],
}))

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

// Derived from overviewInventory (already fetched below) rather than a
// separate /reports/inventory call, so the KPI tiles and this stay coherent
// with each other and mount doesn't triple-fetch the same endpoint.
const completenessStats = computed(() => {
  const total = overviewInventory.value.length
  const pct = (pred) => (total ? Math.round((overviewInventory.value.filter(pred).length / total) * 100) : 0)
  return {
    total,
    price: pct((a) => a.purchase_price !== null && a.purchase_price !== undefined),
    date: pct((a) => a.purchase_date !== null && a.purchase_date !== undefined),
    serial: pct((a) => !!a.serial_number),
  }
})

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
  } catch (e) {
    // Previously try/finally with no catch: the cards silently rendered zeros
    // and empty charts, which reads as "you own nothing" rather than "this failed".
    overviewLocationsRaw.value = []
    overviewInventory.value = []
    overviewCategoriesRaw.value = []
    toast.error(errorMessage(e, t('reports.load_failed')))
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
    const name = a.category?.name || t('reports.uncategorized')
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
// computed so the chart legend re-labels when the locale changes (see reportTypes above).
const STATUS_LABELS = computed(() => ({ in_use: t('reports.status_in_use'), needs_repair: t('reports.status_needs_repair'), lost: t('reports.status_lost'), retiring: t('reports.status_retiring') }))
const overviewStatusSegments = computed(() => {
  const counts = { in_use: 0, needs_repair: 0, lost: 0, retiring: 0 }
  overviewInventory.value.forEach((a) => { counts[assetBucket(a)]++ })
  const relabeled = {}
  Object.entries(counts).forEach(([key, count]) => { if (count > 0) relabeled[STATUS_LABELS.value[key]] = count })
  return toSegments(relabeled)
})

const overviewTotalValue = computed(() => overviewInventory.value.reduce((sum, a) => sum + (Number(a.purchase_price) || 0), 0))
const overviewValueSegments = computed(() => {
  const sums = {}
  overviewInventory.value.forEach((a) => {
    if (!a.purchase_price) return
    const name = a.category?.name || t('reports.uncategorized')
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
  try {
    const { data } = await http.get(`/reports/${selected.value}`)
    rows.value = data
  } catch (e) {
    // Without this catch the thrown request left `loading` stuck true forever,
    // so a permission failure showed an endless spinner instead of a message.
    rows.value = []
    toast.error(errorMessage(e, t('reports.load_failed')))
  } finally {
    loading.value = false
  }
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
    { label: t('reports.field_has_asset_id'), pct: 100 },
    { label: t('reports.field_has_purchase_price'), pct: s.price },
    { label: t('reports.field_has_purchase_date'), pct: s.date },
    { label: t('reports.field_has_serial_no'), pct: s.serial },
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
  const col = columns.value[selected.value].find((c) => c[0] === sortKey.value)
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

// Pagination sits on the finished list. exportCsv and the record counts below
// deliberately keep reading sortedRows, so they still cover every row.
const { page, rowsPerPage, total, paged } = usePagination(sortedRows)
// Switching report type swaps the whole dataset, so page 3 of the old one is
// meaningless in the new one. Nothing assigns `selected` in the template today
// (the type picker isn't wired up — reportTypes only feeds a label), so this
// never fires as things stand; it is here so pagination stays correct if the
// picker comes back.
watch(selected, () => { page.value = 0 })

function exportCsv() {
  const cols = columns.value[selected.value]
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
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('reports.title') }}</h1>
          <p class="text-muted text-sm mt-1">{{ t('reports.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0 mt-1 sm:mt-0">
          <button @click="openEmailModal" class="btn-ghost">
            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
            {{ t('reports.email_report') }}
          </button>
          <button @click="exportCsv" class="btn-ghost">
            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            {{ t('reports.export_csv') }}
          </button>
        </div>
      </div>

      <div v-if="overviewLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="card p-5 h-24 animate-pulse"></div>
      </div>

      <template v-else>
        <!-- KPI row — StatCard, identical component to the Dashboard page -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard :value="overviewInventory.length" :label="t('reports.total_assets')" />
          <StatCard :value="overviewCategoriesRaw.length" :label="t('reports.categories')" />
          <StatCard :value="overviewLocationsRaw.length" :label="t('reports.sites')" />
          <StatCard
            :value="formatCurrency(overviewTotalValue)"
            :label="pricedCount < overviewInventory.length ? t('reports.recorded_value_unpriced', { count: overviewInventory.length - pricedCount }) : t('reports.recorded_value')"
            :badge="t('reports.priced_badge', { percent: pricedPercentage })"
          />
        </div>

        <!-- Asset categories + Asset status — DonutChart, same component as Dashboard's "by category" card -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div class="card p-6">
            <h2 class="font-display text-lg font-bold text-fg">{{ t('reports.by_category') }}</h2>
            <p class="text-sm text-faint mb-6">{{ t('reports.by_category_subtitle') }}</p>
            <DonutChart v-if="overviewCategorySegments.length" :segments="overviewCategorySegments" :total="overviewInventory.length" />
            <p v-else class="text-sm text-faint">{{ t('reports.no_assets_yet') }}</p>
          </div>

          <div class="card p-6">
            <h2 class="font-display text-lg font-bold text-fg">{{ t('reports.asset_status') }}</h2>
            <p class="text-sm text-faint mb-6">{{ t('reports.asset_status_subtitle') }}</p>
            <DonutChart v-if="overviewInventory.length" :segments="overviewStatusSegments" :total="overviewInventory.length" :total-label="t('reports.total_label_assets')" />
            <p v-else class="text-sm text-faint">{{ t('reports.no_assets_yet') }}</p>
          </div>
        </div>

        <!-- Assets by location — LocationPillCards, same component as Dashboard's "by location" card -->
        <div class="card p-6">
          <h2 class="font-display text-lg font-bold text-fg">{{ t('reports.by_location') }}</h2>
          <p class="text-sm text-faint mb-6">{{ t('reports.by_location_subtitle') }}</p>
          <LocationPillCards :locations="overviewLocationsList" />
        </div>

        <!-- Asset value by category — a third DonutChart, currency-formatted -->
        <div class="card p-6">
          <h2 class="font-display text-lg font-bold text-fg">{{ t('reports.value_by_category') }}</h2>
          <p class="text-sm text-faint mb-6">{{ t('reports.value_recorded_across', { amount: money(overviewTotalValue), count: overviewInventory.length }) }}</p>
          <DonutChart
            v-if="overviewValueSegments.length"
            :segments="overviewValueSegments"
            :total="overviewTotalValue"
            :total-label="t('reports.total_label_value')"
            :format-value="money"
          />
          <p v-else class="text-sm text-faint">{{ t('reports.no_priced_assets') }}</p>
        </div>

        <!-- Data completeness — plain progress bars, same treatment as the browser's own version below -->
        <div class="card p-6">
          <h2 class="font-display text-lg font-bold text-fg">{{ t('reports.data_completeness') }}</h2>
          <p class="text-sm text-faint mb-6">{{ t('reports.fields_recorded_across', { count: completenessStats?.total ?? 0 }) }}</p>
          <div v-for="b in completenessBars" :key="b.label" class="flex items-center gap-3 mb-3 last:mb-0">
            <span class="w-36 sm:w-44 flex-shrink-0 text-sm font-semibold text-fg truncate">{{ b.label }}</span>
            <div class="flex-1 h-2.5 rounded-full bg-surface-2 border border-line overflow-hidden">
              <div class="h-full rounded-full bg-brand transition-all" :style="{ width: b.pct + '%' }"></div>
            </div>
            <span class="w-10 text-right text-sm font-semibold text-fg flex-shrink-0">{{ b.pct }}%</span>
          </div>
        </div>

      </template>

      <!-- Detailed report browser -->
      <div class="card p-6 sm:p-8">
        <!-- Date filter + export -->
        <div class="flex flex-wrap items-center gap-3 mb-6">
          <template v-if="hasDateField">
            <div class="flex items-center gap-1 bg-surface-2 rounded-xl p-1">
              <button
                v-for="g in ['all', 'day', 'month', 'year']" :key="g"
                @click="granularity = g"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold capitalize transition"
                :class="granularity === g ? 'bg-brand text-white' : 'text-muted hover:text-fg'"
              >
                {{ t(`reports.granularity_${g}`) }}
              </button>
            </div>
            <input v-if="granularity === 'day'" v-model="periodValue" type="date" class="filter-select" />
            <input v-else-if="granularity === 'month'" v-model="periodValue" type="month" class="filter-select" />
            <FilterPills v-else-if="granularity === 'year'" v-model="periodValue" :options="yearOptions" hide-all />
          </template>
          <button @click="exportCsv" class="btn-ghost btn-sm sm:ml-auto flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            {{ t('reports.export_csv') }}
          </button>
        </div>

        <!-- Inline record count summary -->
        <div class="flex flex-wrap items-center gap-x-8 gap-y-3 mb-6 pb-6 border-b border-line">
          <div>
            <p class="font-display text-2xl font-bold text-fg leading-none">{{ rows.length }}</p>
            <p class="text-xs text-muted mt-1">{{ t('reports.total_records') }}</p>
          </div>
          <div>
            <p class="font-display text-2xl font-bold text-fg leading-none">{{ sortedRows.length }}</p>
            <p class="text-xs text-muted mt-1">{{ granularity === 'all' ? t('reports.showing') : t('reports.matching_period') }}</p>
          </div>
          <p class="sm:ml-auto text-sm text-muted">{{ reportTypes.find((rt) => rt.key === selected)?.label }}{{ granularity !== 'all' && periodValue ? ` — ${periodValue}` : '' }}</p>
        </div>

        <!-- Assets by location — horizontal bar breakdown, no raw table needed. -->
        <div v-if="selected === 'locations'">
          <div v-for="b in locationBars" :key="b.label" class="flex items-center gap-3 mb-3 last:mb-0">
            <span class="w-36 sm:w-44 flex-shrink-0 text-sm font-semibold text-fg truncate">{{ b.label }}</span>
            <div class="flex-1 h-2.5 rounded-full bg-surface-2 border border-line overflow-hidden">
              <div class="h-full rounded-full bg-brand transition-all" :style="{ width: b.pct + '%' }"></div>
            </div>
            <span class="w-10 text-right text-sm font-semibold text-fg flex-shrink-0">{{ b.count }}</span>
          </div>
          <p v-if="!loading && !locationBars.length" class="text-sm text-faint text-center py-6">{{ t('reports.no_data_for_report') }}</p>
        </div>

        <template v-else>
          <!-- Data completeness — aggregate bar summary above the per-asset checklist. -->
          <div v-if="selected === 'data-completeness'" class="mb-6 pb-6 border-b border-line">
            <p class="text-xs font-semibold text-faint uppercase tracking-wide mb-3">{{ t('reports.fields_recorded_across', { count: completenessStats.total }) }}</p>
            <div v-for="b in completenessBars" :key="b.label" class="flex items-center gap-3 mb-3 last:mb-0">
              <span class="w-36 sm:w-44 flex-shrink-0 text-sm font-semibold text-fg truncate">{{ b.label }}</span>
              <div class="flex-1 h-2.5 rounded-full bg-surface-2 border border-line overflow-hidden">
                <div class="h-full rounded-full bg-brand transition-all" :style="{ width: b.pct + '%' }"></div>
              </div>
              <span class="w-10 text-right text-sm font-semibold text-fg flex-shrink-0">{{ b.pct }}%</span>
            </div>
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
                <tr v-for="(row, i) in paged" :key="i">
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
                    {{ granularity !== 'all' ? t('reports.no_records_in_period') : t('reports.no_data_for_report') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <TablePagination v-model:page="page" v-model:rows-per-page="rowsPerPage" :count="total" />
        </template>
      </div>

    </div>

    <Modal v-if="showEmailModal" :title="t('reports.email_report')" @close="showEmailModal = false">
      <form @submit.prevent="sendReportEmail">
        <div class="p-6 space-y-4">
          <p class="text-sm text-muted">
            {{ t('reports.email_body_hint') }}
          </p>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('reports.recipient_email') }}</label>
            <input v-model="emailAddress" type="email" required placeholder="name@example.com" class="input" />
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" :disabled="emailSending" class="btn-primary">
            {{ emailSending ? t('reports.sending') : t('reports.send') }}
          </button>
          <button type="button" class="btn-ghost" @click="showEmailModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>
