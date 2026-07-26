<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import http from '../../api/http'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import StatCard from '../../components/ui/StatCard.vue'

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

const STOCK_LEVEL_COLOR = {
  high: 'success',
  medium: 'warning',
  low: 'error',
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
  <v-container fluid class="pa-0 d-flex flex-column ga-6">
      <AppPageHeader
        title="Reports"
        subtitle="Export and review asset data"
        :actions="[{ label: 'Export CSV', icon: 'mdi-tray-arrow-down', onClick: exportCsv }]"
      />

      <div class="overflow-x-auto">
        <v-tabs v-model="selected" density="compact">
          <v-tab v-for="t in reportTypes" :key="t.key" :value="t.key">{{ t.label }}</v-tab>
        </v-tabs>
      </div>

      <v-row dense>
        <v-col cols="6" sm="3">
          <StatCard :value="rows.length" label="Total records" />
        </v-col>
        <v-col cols="6" sm="3">
          <StatCard :value="sortedRows.length" :label="granularity === 'all' ? 'Showing' : 'Matching period'" />
        </v-col>
        <v-col cols="12" sm="6">
          <v-card rounded="lg" variant="flat" border class="pa-5 d-flex align-center ga-3 h-100">
            <v-avatar rounded="lg" color="mint-tint" size="40"><v-icon icon="mdi-calendar-month-outline" color="primary" /></v-avatar>
            <p class="text-body-2 text-medium-emphasis">{{ reportTypes.find((t) => t.key === selected)?.label }}{{ granularity !== 'all' && periodValue ? ` — ${periodValue}` : '' }}</p>
          </v-card>
        </v-col>
      </v-row>

      <v-card rounded="lg" variant="flat" border>
        <v-card-text v-if="hasDateField" class="d-flex flex-wrap align-center ga-3 pa-4">
          <v-btn-toggle v-model="granularity" mandatory density="compact" color="primary">
            <v-btn v-for="g in ['all', 'day', 'month', 'year']" :key="g" :value="g" size="small" class="text-capitalize">{{ g }}</v-btn>
          </v-btn-toggle>
          <v-text-field v-if="granularity === 'day'" v-model="periodValue" type="date" density="compact" variant="outlined" hide-details style="max-width: 200px" />
          <v-text-field v-else-if="granularity === 'month'" v-model="periodValue" type="month" density="compact" variant="outlined" hide-details style="max-width: 200px" />
          <v-select
            v-else-if="granularity === 'year'"
            v-model="periodValue"
            :items="years.map((y) => ({ title: String(y), value: String(y) }))"
            density="compact"
            variant="outlined"
            hide-details
            style="max-width: 140px"
          />
        </v-card-text>
        <v-divider v-if="hasDateField" />
        <div class="overflow-x-auto">
          <v-table>
            <thead>
              <tr>
                <th v-for="col in columns[selected]" :key="col[0]" class="text-no-wrap" style="cursor: pointer" @click="toggleSort(col[0])">
                  {{ col[1] }}<TableSortIcon :active="sortKey === col[0]" :direction="sortDir" />
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in sortedRows" :key="i">
                <td v-for="col in columns[selected]" :key="col[0]">
                  <v-chip v-if="col[0] === 'stock_level'" size="small" :color="STOCK_LEVEL_COLOR[cell(row, col)]" variant="tonal" class="text-capitalize">
                    {{ cell(row, col) }}
                  </v-chip>
                  <span v-else-if="col[0] === 'asset_code'" class="font-mono font-mono-tag">{{ cell(row, col) }}</span>
                  <template v-else>{{ cell(row, col) }}</template>
                </td>
              </tr>
              <tr v-if="!loading && !sortedRows.length">
                <td :colspan="columns[selected].length" class="py-10 text-center text-medium-emphasis">
                  {{ granularity !== 'all' ? 'No records in this period.' : 'No data for this report.' }}
                </td>
              </tr>
            </tbody>
          </v-table>
        </div>
      </v-card>
  </v-container>
</template>
