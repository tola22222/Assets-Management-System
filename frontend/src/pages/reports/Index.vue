<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'

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

function defaultPeriodValue(g) {
  if (g === 'day') return today.toISOString().slice(0, 10)
  if (g === 'month') return today.toISOString().slice(0, 7)
  if (g === 'year') return String(today.getFullYear())
  return ''
}
watch(granularity, (g) => { periodValue.value = defaultPeriodValue(g) })

async function load() {
  loading.value = true
  granularity.value = 'all'
  sortKey.value = null
  const { data } = await http.get(`/reports/${selected.value}`)
  rows.value = data
  loading.value = false
}

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

onMounted(load)
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-6xl mx-auto space-y-6">
      <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="w-11 h-11 rounded-2xl bg-brand/10 text-brand flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
          </div>
          <div>
            <h1 class="font-display text-xl font-bold text-fg tracking-tight">Reports</h1>
            <p class="text-muted text-sm mt-0.5">Export and review asset data</p>
          </div>
        </div>
        <button @click="exportCsv" class="btn-ghost btn-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
          Export CSV
        </button>
      </div>

      <div class="border-b border-line overflow-x-auto">
        <div class="flex gap-6 min-w-max">
          <button v-for="t in reportTypes" :key="t.key" @click="selected = t.key"
            class="px-0.5 py-3 text-sm font-medium whitespace-nowrap border-b-2 -mb-px transition"
            :class="selected === t.key ? 'border-brand text-brand font-semibold' : 'border-transparent text-muted hover:text-fg'">
            {{ t.label }}
          </button>
        </div>
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
            <select v-else-if="granularity === 'year'" v-model="periodValue" class="filter-select">
              <option v-for="y in years" :key="y" :value="String(y)">{{ y }}</option>
            </select>
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
    </div>
  </AppLayout>
</template>
