<script setup>
import { ref, computed, onMounted, reactive, watch } from 'vue'
import { useRoute } from 'vue-router'
import http from '../../api/http'
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

const toast = useToastStore()
const auth = useAuthStore()
const route = useRoute()
const canManage = computed(() => ['operations_hr_manager', 'finance_manager'].includes(auth.user?.role))

const items = ref([])
const locations = ref([])
const categories = ref([])
const loading = ref(true)

async function fetchAll() {
  loading.value = true
  const { data } = await http.get('/stock-items')
  items.value = data
  loading.value = false
}
async function loadLocations() {
  const { data } = await http.get('/locations')
  locations.value = data
}
async function loadCategories() {
  const { data } = await http.get('/categories')
  categories.value = data
}

// Unique existing item names, for the Receive Stock "pick an existing item" dropdown.
const uniqueItemNames = computed(() => [...new Set(items.value.map((i) => i.name))].sort((a, b) => a.localeCompare(b)))

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
const statusOptions = [
  { value: 'low', label: 'Low' },
  { value: 'normal', label: 'Normal' },
  { value: 'high', label: 'High' },
]

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

// ---- Dashboard cards --------------------------------------------------
const lowItems = computed(() =>
  items.value.filter((i) => i.status === 'low').sort((a, b) => (a.balance - (a.min_threshold ?? 0)) - (b.balance - (b.min_threshold ?? 0)))
)
const normalCount = computed(() => items.value.filter((i) => i.status === 'normal').length)
const highItems = computed(() => items.value.filter((i) => i.status === 'high'))

// ---- Receive Stock ------------------------------------------------------
const showReceiveModal = ref(false)
const receiving = ref(false)
const emptyReceiveForm = () => ({
  name: '', category: '', unit: '', quantity: '', location_id: '',
  min_threshold: '', max_threshold: '', reason: '', transaction_date: new Date().toISOString().slice(0, 10),
})
const receiveForm = reactive(emptyReceiveForm())

// Item Name is a strict dropdown of existing items, plus a "+ Add new item"
// choice that reveals a free-text box — this is the only way to still
// create a brand-new stock item while keeping the normal case a real select.
const NEW_ITEM_VALUE = '__new__'
const receiveNameSelection = ref('')
watch(receiveNameSelection, (v) => {
  if (v !== NEW_ITEM_VALUE) receiveForm.name = v
})

function openReceive(prefill = null) {
  Object.assign(receiveForm, emptyReceiveForm())
  if (prefill) {
    Object.assign(receiveForm, {
      name: prefill.name, category: prefill.category || '', unit: prefill.unit,
      location_id: prefill.location_id, min_threshold: prefill.min_threshold ?? '', max_threshold: prefill.max_threshold ?? '',
    })
    receiveNameSelection.value = prefill.name
  } else {
    receiveNameSelection.value = uniqueItemNames.value.length ? '' : NEW_ITEM_VALUE
  }
  showReceiveModal.value = true
}

async function submitReceive() {
  receiving.value = true
  try {
    await http.post('/stock-items/receive', receiveForm)
    toast.success(`${receiveForm.quantity} ${receiveForm.unit} of "${receiveForm.name}" received.`)
    showReceiveModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not receive stock.')
  } finally {
    receiving.value = false
  }
}

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
    toast.success(`${issueForm.quantity} ${issuing.value.unit} of "${issuing.value.name}" issued.`)
    issuing.value = null
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not issue stock.')
  } finally {
    issueSubmitting.value = false
  }
}

// ---- Detail / transaction history ---------------------------------------
const viewing = ref(null)
async function openDetail(item) {
  const { data } = await http.get(`/stock-items/${item.id}`)
  viewing.value = data
}

// ---- Delete ---------------------------------------------------------------
const deletingId = ref(null)
async function confirmDelete() {
  try {
    await http.delete(`/stock-items/${deletingId.value}`)
    toast.success('Stock item deleted.')
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not delete this stock item.')
  } finally {
    deletingId.value = null
  }
}

async function confirmBulkDelete() {
  confirmingBulkDelete.value = false
  const ids = selectedIds.value
  const results = await Promise.allSettled(ids.map((id) => http.delete(`/stock-items/${id}`)))
  const failed = results.filter((r) => r.status === 'rejected').length
  if (failed) toast.error(`${failed} item(s) couldn't be deleted (already have transaction history).`)
  toast.success(`${ids.length - failed} stock item(s) deleted.`)
  clearSelection()
  await fetchAll()
}

// ---- CSV export -----------------------------------------------------------
function exportCsv() {
  const cols = [
    ['stock_code', 'Stock ID'], ['name', 'Item Name'], ['category', 'Category'],
    ['location', 'Location'], ['balance', 'Balance'], ['unit', 'Unit'],
    ['status', 'Status'], ['min_threshold', 'Min Threshold'], ['updated_at', 'Last Transaction'],
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
  loadCategories()
})
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 space-y-6">

      <!-- Page heading -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="font-display text-3xl font-bold text-fg tracking-tight">Stock</h1>
          <p class="text-muted text-sm mt-1">Bulk consumables — toner, paper, cables, and other items with no individual tag</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <button v-if="canManage && selectedIds.length" @click="confirmingBulkDelete = true" class="btn-danger btn-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
            Delete Selected ({{ selectedIds.length }})
          </button>
          <button @click="exportCsv" class="btn-ghost btn-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 12L12 16.5m0 0l4.5-4.5M12 16.5V3" /></svg>
            Export CSV
          </button>
          <button v-if="canManage" @click="openReceive()" class="btn-primary btn-sm">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            Receive Stock
          </button>
        </div>
      </div>

      <!-- Dashboard cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- Low Stock -->
        <div class="card p-5">
          <div class="flex items-center justify-between mb-3">
            <h2 class="font-display text-base font-bold text-fg">Low Stock</h2>
            <span class="badge badge-danger">{{ lowItems.length }}</span>
          </div>
          <div v-if="lowItems.length" class="space-y-1 max-h-56 overflow-y-auto">
            <button v-for="i in lowItems" :key="i.id" @click="canManage && openReceive(i)"
              class="w-full text-left px-2.5 py-2 rounded-lg hover:bg-surface-2 transition flex items-center justify-between gap-2">
              <div class="min-w-0">
                <p class="text-sm font-semibold text-fg truncate">{{ i.name }}</p>
                <p class="text-xs text-faint truncate">{{ i.location?.name }}</p>
              </div>
              <div class="text-right flex-shrink-0">
                <p class="text-sm font-bold text-red-600 dark:text-red-400">{{ i.balance }} {{ i.unit }}</p>
                <p class="text-[11px] text-faint">min {{ i.min_threshold }}</p>
              </div>
            </button>
          </div>
          <p v-else class="text-sm text-faint">Nothing running low.</p>
        </div>

        <!-- Normal -->
        <div class="card p-5">
          <div class="flex items-center justify-between">
            <h2 class="font-display text-base font-bold text-fg">Normal</h2>
            <span class="badge badge-success">{{ normalCount }}</span>
          </div>
          <p class="text-sm text-muted mt-2">Items within their configured range.</p>
        </div>

        <!-- Overstock -->
        <div class="card p-5">
          <div class="flex items-center justify-between mb-3">
            <h2 class="font-display text-base font-bold text-fg">Hight</h2>
            <span class="badge badge-warning">{{ highItems.length }}</span>
          </div>
          <div v-if="highItems.length" class="space-y-1 max-h-56 overflow-y-auto">
            <div v-for="i in highItems" :key="i.id" class="px-2.5 py-2 rounded-lg flex items-center justify-between gap-2">
              <div class="min-w-0">
                <p class="text-sm font-semibold text-fg truncate">{{ i.name }}</p>
                <p class="text-xs text-faint truncate">{{ i.location?.name }}</p>
              </div>
              <p class="text-sm font-bold text-amber-600 dark:text-amber-400 flex-shrink-0">{{ i.balance }} {{ i.unit }}</p>
            </div>
          </div>
          <p v-else class="text-sm text-faint">Nothing over its max.</p>
        </div>
      </div>

      <!-- Grid -->
      <div class="card p-6 sm:p-8">
        <div class="flex flex-wrap items-center gap-3 mb-6">
          <div class="flex-1 min-w-[260px]">
            <SearchInput v-model="search" placeholder="Search by item name or Stock ID…" />
          </div>
          <select v-model="filters.location" class="filter-select">
            <option value="">All locations</option>
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
                <th>Stock ID</th>
                <th class="th-sort" @click="toggleSort('name')">Item Name<TableSortIcon :active="sortKey === 'name'" :direction="sortDir" /></th>
                <th>Category</th>
                <th class="th-sort" @click="toggleSort('location')">Location<TableSortIcon :active="sortKey === 'location'" :direction="sortDir" /></th>
                <th class="th-sort text-right" @click="toggleSort('balance')">Balance<TableSortIcon :active="sortKey === 'balance'" :direction="sortDir" /></th>
                <th>Unit</th>
                <th class="th-sort text-center" @click="toggleSort('status_rank')">Status<TableSortIcon :active="sortKey === 'status_rank'" :direction="sortDir" /></th>
                <th class="th-sort text-right" @click="toggleSort('threshold')">Min Threshold<TableSortIcon :active="sortKey === 'threshold'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('updated')">Last Transaction<TableSortIcon :active="sortKey === 'updated'" :direction="sortDir" /></th>
                <th class="text-right">Actions</th>
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
                    <button @click="openIssue(i)" title="Issue" class="w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                    </button>
                    <button @click="deletingId = i.id" title="Delete" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                    </button>
                  </div>
                  <span v-else class="text-faint text-xs">—</span>
                </td>
              </tr>
              <tr v-if="!loading && !visible.length">
                <td :colspan="canManage ? 11 : 10" class="py-10 text-center text-faint">
                  {{ locationFilterLabel ? `No stock items found for ${locationFilterLabel}.` : (search || filters.status ? 'No stock items match your search.' : 'No stock items found.') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Receive Stock -->
    <Modal v-if="showReceiveModal" title="Receive Stock" @close="showReceiveModal = false">
      <form @submit.prevent="submitReceive">
        <div class="p-6 space-y-4">
          <div class="rounded-xl bg-surface-2 border border-line px-3.5 py-2.5 text-xs text-muted flex items-start gap-2">
            <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-faint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
            <span>This is the <strong class="text-fg font-semibold">Stock</strong> list — bulk consumables like toner, paper, and cables tracked only by name and running balance. It's separate from the <strong class="text-fg font-semibold">Asset Register</strong>, so nothing here can be selected from or will affect a tagged asset.</span>
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">Stock Item Name <span class="text-red-500">*</span></label>
            <select v-model="receiveNameSelection" required class="select">
              <option value="" disabled>Select an existing stock item…</option>
              <option v-for="n in uniqueItemNames" :key="n" :value="n">{{ n }}</option>
              <option :value="NEW_ITEM_VALUE">+ Create a new stock item</option>
            </select>
            <input v-if="receiveNameSelection === NEW_ITEM_VALUE" v-model="receiveForm.name" required class="input mt-2" placeholder="e.g. A4 Paper, Toner Cartridge, USB Cable" />
            <p class="text-xs text-faint">This list is Stock items only — it does not include anything from the Asset Register. Picking an existing name adds to that item's balance at the location below; creating a new one starts a fresh Stock ID at 0.</p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">Category</label>
              <select v-model="receiveForm.category" class="select">
                <option value="">No category</option>
                <option v-for="c in categories" :key="c.id" :value="c.name">{{ c.name }}</option>
              </select>
              <p class="text-xs text-faint">Reuses the Asset Register's category list for convenience — this is just a label on the stock item, it doesn't link it to any asset.</p>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">Unit <span class="text-red-500">*</span></label>
              <input v-model="receiveForm.unit" required class="input" placeholder="pcs / box / pack / liter" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">Quantity Received <span class="text-red-500">*</span></label>
              <input v-model="receiveForm.quantity" type="number" step="0.01" min="0.01" required class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">Location <span class="text-red-500">*</span></label>
              <select v-model="receiveForm.location_id" required class="select">
                <option value="">Select location</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">Min Threshold</label>
              <input v-model="receiveForm.min_threshold" type="number" step="0.01" min="0" class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">Max Threshold</label>
              <input v-model="receiveForm.max_threshold" type="number" step="0.01" min="0" class="input" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">Source / Reason</label>
              <input v-model="receiveForm.reason" class="input" placeholder="Donor, purchase order #…" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">Date</label>
              <input v-model="receiveForm.transaction_date" type="date" class="input" />
            </div>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" :disabled="receiving" class="btn-primary">
            {{ receiving ? 'Saving…' : 'Receive Stock' }}
          </button>
          <button type="button" class="btn-ghost" @click="showReceiveModal = false">Cancel</button>
        </div>
      </form>
    </Modal>

    <!-- Issue Stock -->
    <Modal v-if="issuing" title="Issue Stock" @close="issuing = null">
      <form @submit.prevent="submitIssue">
        <div class="p-6 space-y-4">
          <p class="text-sm text-muted">{{ issuing.name }} <span class="font-mono text-xs text-faint">({{ issuing.stock_code }})</span> — {{ issuing.balance }} {{ issuing.unit }} on hand</p>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">Quantity to Issue <span class="text-red-500">*</span></label>
            <input v-model="issueForm.quantity" type="number" step="0.01" min="0.01" :max="issuing.balance" required class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">Issued To</label>
            <input v-model="issueForm.reason" class="input" placeholder="Site, person, or department" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">Date</label>
            <input v-model="issueForm.transaction_date" type="date" class="input" />
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" :disabled="issueSubmitting" class="btn-primary">
            {{ issueSubmitting ? 'Saving…' : 'Issue Stock' }}
          </button>
          <button type="button" class="btn-ghost" @click="issuing = null">Cancel</button>
        </div>
      </form>
    </Modal>

    <!-- Detail / transaction history -->
    <Modal v-if="viewing" title="Transaction History" wide @close="viewing = null">
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
                <th>Date</th>
                <th>Type</th>
                <th class="text-right">Quantity</th>
                <th>Source / Reason</th>
                <th>Recorded By</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in viewing.transactions" :key="t.id">
                <td>{{ t.transaction_date }}</td>
                <td><span class="badge" :class="t.type === 'in' ? 'badge-success' : 'badge-info'">{{ t.type === 'in' ? 'Stock In' : 'Stock Out' }}</span></td>
                <td class="text-right font-medium" :class="t.type === 'in' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                  {{ t.type === 'in' ? '+' : '-' }}{{ t.quantity }}
                </td>
                <td>{{ t.reason || '—' }}</td>
                <td>{{ t.recorded_by?.name || '—' }}</td>
              </tr>
              <tr v-if="!viewing.transactions?.length">
                <td colspan="5" class="py-8 text-center text-faint">No transactions recorded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
    <ConfirmDialog
      v-if="confirmingBulkDelete"
      :title="`Delete ${selectedIds.length} stock item${selectedIds.length === 1 ? '' : 's'}?`"
      message="Items with transaction history will be skipped."
      @confirm="confirmBulkDelete"
      @cancel="confirmingBulkDelete = false"
    />
  </AppLayout>
</template>
