<script setup>
import { ref, onMounted, watch } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'

const reportTypes = [
  { key: 'inventory', label: 'Inventory' },
  { key: 'assignments', label: 'Assignments' },
  { key: 'transfers', label: 'Transfers' },
  { key: 'verifications', label: 'Verifications' },
  { key: 'returns', label: 'Returns' },
  { key: 'disposed', label: 'Disposed Assets' },
  { key: 'lost', label: 'Lost Assets' },
  { key: 'locations', label: 'Locations' },
  { key: 'qr-scans', label: 'QR Scans' },
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
  locations: [['name', 'Name'], ['type', 'Type'], ['asset_stocks_count', 'Stock Records']],
  'qr-scans': [['message', 'Scan'], ['created_at', 'Date']],
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
          <h1 class="text-xl font-bold text-ink tracking-tight">Reports</h1>
          <p class="text-gray-500 text-sm mt-0.5">Export and review asset data</p>
        </div>
        <button @click="exportCsv" class="border border-gray-200 text-gray-700 font-semibold text-sm px-5 py-2.5 rounded-xl hover:bg-gray-50 transition">
          Export CSV
        </button>
      </div>

      <div class="flex flex-wrap gap-2">
        <button v-for="t in reportTypes" :key="t.key" @click="selected = t.key"
          class="px-4 py-2 rounded-xl text-sm font-semibold transition"
          :class="selected === t.key ? 'bg-brand text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'">
          {{ t.label }}
        </button>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-gray-400 font-semibold bg-gray-50/70 border-b border-gray-100">
              <th v-for="col in columns[selected]" :key="col[0]" class="p-4 pl-5 first:pl-5">{{ col[1] }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(row, i) in rows" :key="i" class="hover:bg-gray-50/50">
              <td v-for="col in columns[selected]" :key="col[0]" class="p-4 pl-5 first:pl-5 text-gray-600">{{ cell(row, col) }}</td>
            </tr>
            <tr v-if="!loading && !rows.length">
              <td :colspan="columns[selected].length" class="p-8 text-center text-gray-400">No data for this report.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
