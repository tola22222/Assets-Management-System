<script setup>
import { ref, onMounted, watch } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'

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

const STOCK_LEVEL_STYLES = {
  high: 'bg-emerald-50 text-emerald-700',
  medium: 'bg-amber-50 text-amber-700',
  low: 'bg-red-50 text-red-700',
}

async function load() {
  loading.value = true
  const { data } = await http.get(`/reports/${selected.value}`)
  rows.value = data
  loading.value = false
}

function cell(row, col) {
  return col[2] ? col[2](row) : row[col[0]]
}

function exportCsv() {
  const cols = columns[selected.value]
  const lines = [cols.map((c) => c[1]).join(',')]
  rows.value.forEach((row) => {
    lines.push(cols.map((c) => `"${String(cell(row, c) ?? '').replace(/"/g, '""')}"`).join(','))
  })
  const blob = new Blob([lines.join('\n')], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${selected.value}-report-${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

watch(selected, load)
onMounted(load)
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-6xl mx-auto space-y-6">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h1 class="text-xl font-bold text-fg tracking-tight">Reports</h1>
          <p class="text-muted text-sm mt-0.5">Export and review asset data</p>
        </div>
        <button @click="exportCsv" class="border border-line text-muted font-semibold text-sm px-5 py-2.5 rounded-xl hover:bg-surface-2 transition">
          Export CSV
        </button>
      </div>

      <div class="flex flex-wrap gap-2">
        <button v-for="t in reportTypes" :key="t.key" @click="selected = t.key"
          class="px-4 py-2 rounded-xl text-sm font-semibold transition"
          :class="selected === t.key ? 'bg-brand text-white' : 'bg-surface border border-line text-muted hover:bg-surface-2'">
          {{ t.label }}
        </button>
      </div>

      <div class="bg-surface rounded-2xl border border-line overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-faint font-semibold bg-surface-2/70 border-b border-line">
              <th v-for="col in columns[selected]" :key="col[0]" class="p-4 pl-5 first:pl-5">{{ col[1] }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            <tr v-for="(row, i) in rows" :key="i" class="hover:bg-surface-2/50">
              <td v-for="col in columns[selected]" :key="col[0]" class="p-4 pl-5 first:pl-5 text-muted">
                <span v-if="col[0] === 'stock_level'" class="px-2.5 py-1 rounded-full text-xs font-bold capitalize" :class="STOCK_LEVEL_STYLES[cell(row, col)] ?? ''">
                  {{ cell(row, col) }}
                </span>
                <template v-else>{{ cell(row, col) }}</template>
              </td>
            </tr>
            <tr v-if="!loading && !rows.length">
              <td :colspan="columns[selected].length" class="p-8 text-center text-faint">No data for this report.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
