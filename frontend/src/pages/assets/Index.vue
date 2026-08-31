<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import FilterPills from '../../components/ui/FilterPills.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableSort } from '../../composables/useTableSort'
import { useTableFilter } from '../../composables/useTableFilter'
import { useBulkSelect } from '../../composables/useBulkSelect'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'

const { t } = useI18n()
const { items: assetsList, loading, fetchAll, destroy, destroyMany } = useApiCrud('/assets', { entityName: t('assets.entity') })
const toast = useToastStore()
const auth = useAuthStore()
const isOpm = computed(() => auth.user?.role === 'operations_hr_manager')
const canEdit = computed(() => isOpm.value || auth.user?.role === 'finance_manager')

const categories = ref([])
const locations = ref([])
const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const viewing = ref(null)
const imageFile = ref(null)
const submitting = ref(false)
const flagging = ref(null)
const flagNote = ref('')
const flagCondition = ref('')
const flagSubmitting = ref(false)

const emptyForm = () => ({
  name: '', category_id: '', location_id: '', description: '', model: '', brand: '',
  serial_number: '', purchase_date: '', purchase_price: '', condition: 'good', status: 'active',
})
const form = reactive(emptyForm())

const { search, filtered: searched } = useTableSearch(assetsList, [
  'name', 'asset_code', 'brand', 'model', (a) => a.category?.name, 'purchase_price', 'serial_number',
])
const { sortKey, sortDir, toggleSort, sorted: filtered } = useTableSort(searched, {
  defaultKey: 'name',
  paths: { category: 'category.name', location: 'location.name', price: 'purchase_price', code: 'asset_code' },
})
// Category filter pills, layered on top of the search+sort pipeline.
const { filters: catFilters, filtered: visible } = useTableFilter(filtered, {
  category: (row, val) => String(row.category_id) === String(val),
})
const categoryOptions = computed(() => categories.value.map((c) => ({ value: c.id, label: c.name })))
const { selectedIds, allSelected, toggleSelectAll, toggleSelect, clearSelection } = useBulkSelect(visible)
const confirmingBulkDelete = ref(false)

function money(v) {
  return v ? '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'
}

// Condition + lifecycle status collapsed into a single at-a-glance pill
// (mirrors the "In use / Needs repair / Retiring" states from the report-deck
// mockup) rather than two separate condition/status columns.
function assetState(asset) {
  if (asset.status === 'disposed') return { label: t('assets.state_retiring'), cls: 'badge-danger' }
  if (asset.condition === 'lost') return { label: t('assets.state_lost'), cls: 'badge-danger' }
  if (asset.condition === 'fair' || asset.condition === 'broken') return { label: t('assets.state_needs_repair'), cls: 'badge-warning' }
  return { label: t('assets.state_in_use'), cls: 'badge-success' }
}

function assetNote(asset) {
  return asset.description || [asset.brand, asset.model].filter(Boolean).join(' ')
}

function assignedTo(asset) {
  return asset.assignments?.[0]?.recipient_name || t('assets.unassigned')
}

function exportCsv() {
  const cols = [
    ['asset_code', t('assets.id_col')],
    ['name', t('common.name')],
    ['description', t('common.description')],
    ['category', t('assets.category')],
    ['location', t('assets.location_col')],
    ['assigned_to', t('assets.assigned_to')],
    ['status', t('common.status')],
    ['value', t('assets.value_col')],
  ]
  const row = (a) => ({
    asset_code: a.asset_code,
    name: a.name,
    description: a.description || '',
    category: a.category?.name || '',
    location: a.location?.name || '',
    assigned_to: assignedTo(a),
    status: assetState(a).label,
    value: a.purchase_price ?? '',
  })
  const lines = [cols.map(([, label]) => label).join(',')]
  visible.value.forEach((a) => {
    const r = row(a)
    lines.push(cols.map(([key]) => `"${String(r[key] ?? '').replace(/"/g, '""')}"`).join(','))
  })
  const blob = new Blob([lines.join('\n')], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `asset-register-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

async function loadCategories() {
  const { data } = await http.get('/categories')
  categories.value = data
}

async function loadLocations() {
  const { data } = await http.get('/locations')
  locations.value = data
}

function openCreate() {
  editingId.value = null
  Object.assign(form, emptyForm())
  imageFile.value = null
  showModal.value = true
}

function openEdit(asset) {
  editingId.value = asset.id
  Object.assign(form, {
    name: asset.name, category_id: asset.category_id, location_id: asset.location_id || '', description: asset.description || '',
    model: asset.model || '', brand: asset.brand || '', serial_number: asset.serial_number || '',
    purchase_date: asset.purchase_date || '', purchase_price: asset.purchase_price || '',
    condition: asset.condition, status: asset.status,
  })
  imageFile.value = null
  showModal.value = true
}

function handleFileChange(e) {
  imageFile.value = e.target.files[0] || null
}

function buildFormData() {
  const fd = new FormData()
  Object.entries(form).forEach(([key, value]) => {
    if (value !== null && value !== '') fd.append(key, value)
  })
  if (imageFile.value) fd.append('image', imageFile.value)
  return fd
}

async function handleSubmit() {
  submitting.value = true
  try {
    const fd = buildFormData()
    const config = { headers: { 'Content-Type': 'multipart/form-data' } }
    if (editingId.value) {
      fd.append('_method', 'PUT')
      await http.post(`/assets/${editingId.value}`, fd, config)
      toast.success(t('assets.updated'))
    } else {
      await http.post('/assets', fd, config)
      toast.success(t('assets.created'))
    }
    showModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || t('assets.save_failed'))
  } finally {
    submitting.value = false
  }
}

async function confirmDelete() {
  await destroy(deletingId.value)
  deletingId.value = null
}

async function confirmBulkDelete() {
  confirmingBulkDelete.value = false
  await destroyMany(selectedIds.value)
  clearSelection()
}

async function regenerateQr(asset) {
  await http.post(`/assets/${asset.id}/regenerate-qr`)
  toast.success(t('assets.qr_regenerated'))
  await fetchAll()
  if (viewing.value?.id === asset.id) {
    viewing.value = assetsList.value.find((a) => a.id === asset.id) || viewing.value
  }
}

function openFlag(asset) {
  flagging.value = asset
  flagNote.value = ''
  flagCondition.value = ''
}

async function submitFlag() {
  if (!flagNote.value.trim()) return
  flagSubmitting.value = true
  try {
    await http.post(`/assets/${flagging.value.id}/flag`, {
      note: flagNote.value,
      condition: flagCondition.value || undefined,
    })
    toast.success(t('assets.flagged_successfully'))
    flagging.value = null
    await fetchAll()
    if (viewing.value?.id) {
      viewing.value = assetsList.value.find((a) => a.id === viewing.value.id) || viewing.value
    }
  } catch (e) {
    toast.error(e.response?.data?.message || t('assets.flag_failed'))
  } finally {
    flagSubmitting.value = false
  }
}

function printQr(asset) {
  if (!asset.qr_code_url) return
  const w = window.open('', '_blank', 'width=420,height=560')
  w.document.write(`
    <html><head><title>QR — ${asset.asset_code}</title>
    <style>body{font-family:system-ui,sans-serif;text-align:center;padding:32px}
    img{width:260px;height:260px}h2{margin:8px 0 0}p{color:#666;font-family:monospace;margin:4px 0 0}</style>
    </head><body onload="window.print()">
    <img src="${asset.qr_code_url}" /><h2>${asset.name}</h2><p>${asset.asset_code}</p>
    </body></html>`)
  w.document.close()
}

onMounted(() => {
  fetchAll()
  loadCategories()
  loadLocations()
})
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 space-y-6">
      <div class="card p-6 sm:p-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('assets.title') }}</h1>
            <p class="text-muted text-sm mt-1">{{ t('assets.subtitle') }}</p>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <button v-if="isOpm && selectedIds.length" @click="confirmingBulkDelete = true" class="btn-danger btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
              {{ t('assets.delete_selected') }} ({{ selectedIds.length }})
            </button>
            <button @click="exportCsv" class="btn-ghost btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 12L12 16.5m0 0l4.5-4.5M12 16.5V3" /></svg>
              {{ t('assets.export_csv') }}
            </button>
            <RouterLink v-if="isOpm" to="/assets/import" class="btn-ghost btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
              {{ t('assets.import') }}
            </RouterLink>
            <button v-if="isOpm" @click="openCreate" class="btn-primary btn-sm">
              <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
              {{ t('assets.register') }}
            </button>
          </div>
        </div>

        <!-- Search & category filter pills -->
        <div class="flex flex-wrap items-center gap-3 mb-6">
          <div class="flex-1 min-w-[260px]">
            <SearchInput v-model="search" :placeholder="t('assets.search_placeholder')" />
          </div>
          <FilterPills v-model="catFilters.category" :options="categoryOptions" />
        </div>

        <div class="flex items-center justify-between gap-3 mb-2">
          <p class="text-sm text-faint">{{ t('assets.count_of', { filtered: visible.length, total: assetsList.length }) }}</p>
        </div>

        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th v-if="isOpm" class="w-10">
                  <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-line text-brand focus:ring-brand/30" />
                </th>
                <th class="th-sort" @click="toggleSort('code')">{{ t('assets.id_col') }}<TableSortIcon :active="sortKey === 'code'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('name')">{{ t('assets.description_col') }}<TableSortIcon :active="sortKey === 'name'" :direction="sortDir" /></th>
                <th class="th-sort text-center" @click="toggleSort('category')">{{ t('assets.category') }}<TableSortIcon :active="sortKey === 'category'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('location')">{{ t('assets.location_col') }}<TableSortIcon :active="sortKey === 'location'" :direction="sortDir" /></th>
                <th>{{ t('assets.assigned_to') }}</th>
                <th class="th-sort text-center" @click="toggleSort('status')">{{ t('common.status') }}<TableSortIcon :active="sortKey === 'status'" :direction="sortDir" /></th>
                <th class="th-sort text-right" @click="toggleSort('price')">{{ t('assets.value_col') }}<TableSortIcon :active="sortKey === 'price'" :direction="sortDir" /></th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="asset in visible" :key="asset.id">
                <td v-if="isOpm">
                  <input type="checkbox" :checked="selectedIds.includes(asset.id)" @change="toggleSelect(asset.id)" class="rounded border-line text-brand focus:ring-brand/30" />
                </td>
                <td class="whitespace-nowrap"><span class="id-chip">{{ asset.asset_code }}</span></td>
                <td>
                  <p class="font-semibold text-fg truncate">{{ asset.name }}</p>
                  <p v-if="assetNote(asset)" class="text-xs text-faint truncate mt-0.5">{{ assetNote(asset) }}</p>
                </td>
                <td class="text-center"><span class="tag">{{ asset.category?.name || '—' }}</span></td>
                <td class="font-medium text-fg">{{ asset.location?.name || '—' }}</td>
                <td class="text-muted">{{ assignedTo(asset) }}</td>
                <td class="text-center">
                  <span class="badge" :class="assetState(asset).cls">{{ assetState(asset).label }}</span>
                </td>
                <td class="font-medium text-fg text-right">{{ money(asset.purchase_price) }}</td>
                <td>
                  <div class="flex items-center justify-end gap-1.5">
                    <button @click="viewing = asset" :title="t('common.view')" class="w-7 h-7 rounded-lg bg-amber-500 text-white flex items-center justify-center hover:bg-amber-600 transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    </button>
                    <button v-if="canEdit" @click="openEdit(asset)" :title="t('common.edit')" class="w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z" /></svg>
                    </button>
                    <button v-if="isOpm" @click="deletingId = asset.id" :title="t('common.delete')" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!loading && !visible.length">
                <td :colspan="isOpm ? 9 : 8" class="py-12 text-center">
                  <div class="flex flex-col items-center gap-2">
                    <svg class="w-10 h-10 text-line-strong" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                    <p class="text-muted text-sm font-medium">{{ (search || catFilters.category) ? t('assets.empty_search') : t('assets.empty') }}</p>
                    <p class="text-xs text-faint">{{ (search || catFilters.category) ? t('assets.empty_search_hint') : t('assets.empty_hint') }}</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create / Edit -->
    <Modal v-if="showModal" :title="editingId ? t('assets.edit_title') : t('assets.create_title')" wide @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="label">{{ t('assets.name_required') }} <span class="text-red-500">*</span></label>
              <input v-model="form.name" required class="input" />
            </div>
            <div>
              <label class="label">{{ t('assets.category_required') }} <span class="text-red-500">*</span></label>
              <select v-model="form.category_id" required class="select">
                <option value="">{{ t('assets.select_category') }}</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="label">{{ t('assets.location_required') }} <span class="text-red-500">*</span></label>
              <select v-model="form.location_id" required class="select">
                <option value="">{{ t('assets.select_location') }}</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div>
              <label class="label">{{ t('assets.brand') }}</label>
              <input v-model="form.brand" class="input" />
            </div>
            <div>
              <label class="label">{{ t('assets.model') }}</label>
              <input v-model="form.model" class="input" />
            </div>
            <div>
              <label class="label">{{ t('assets.serial_number') }}</label>
              <input v-model="form.serial_number" class="input" />
            </div>
            <div>
              <label class="label">{{ t('assets.purchase_date') }}</label>
              <input v-model="form.purchase_date" type="date" class="input" />
            </div>
            <div>
              <label class="label">{{ t('assets.purchase_price') }}</label>
              <input v-model="form.purchase_price" type="number" step="0.01" class="input" />
            </div>
            <div>
              <label class="label">{{ t('assets.condition') }}</label>
              <select v-model="form.condition" class="select">
                <option value="good">{{ t('assets.condition_good') }}</option>
                <option value="fair">{{ t('assets.condition_fair') }}</option>
                <option value="broken">{{ t('assets.condition_broken') }}</option>
                <option value="lost">{{ t('assets.condition_lost') }}</option>
              </select>
            </div>
            <div>
              <label class="label">{{ t('common.status') }}</label>
              <select v-model="form.status" class="select">
                <option value="active">{{ t('status.active') }}</option>
                <option value="disposed">{{ t('status.disposed') }}</option>
              </select>
            </div>
            <div>
              <label class="label">{{ t('assets.photo') }}</label>
              <input type="file" accept="image/jpeg,image/png" @change="handleFileChange"
                class="w-full text-sm text-muted file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer" />
            </div>
          </div>
          <div>
            <label class="label">{{ t('common.description') }}</label>
            <textarea v-model="form.description" rows="2" class="textarea"></textarea>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" :disabled="submitting" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ submitting ? t('assets.saving') : (editingId ? t('assets.save_changes') : t('assets.register')) }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <!-- Detail view -->
    <Modal v-if="viewing" :title="t('assets.detail_title')" wide @close="viewing = null">
      <div class="p-6 space-y-6">
        <div class="flex items-start gap-5">
          <img v-if="viewing.image_url" :src="viewing.image_url" class="w-28 h-24 rounded-xl object-cover border border-line flex-shrink-0" alt="" />
          <div v-else class="w-28 h-24 rounded-xl bg-surface-2 border border-line flex items-center justify-center text-faint text-xs flex-shrink-0">{{ t('assets.no_image_full') }}</div>
          <div class="flex-1 min-w-0">
            <h4 class="text-xl font-bold text-fg truncate">{{ viewing.name }}</h4>
            <div class="mt-1.5"><span class="id-chip">{{ viewing.asset_code }}</span></div>
            <span class="badge mt-2" :class="viewing.status === 'active' ? 'badge-success' : 'badge-neutral'">{{ t(`status.${viewing.status}`) }}</span>
          </div>
          <div v-if="viewing.qr_code_url" class="flex-shrink-0 text-center">
            <img :src="viewing.qr_code_url" class="w-24 h-24 border border-line rounded-xl p-1.5 bg-white mx-auto" alt="QR" />
            <div class="flex items-center justify-center gap-2 mt-1.5 text-xs font-semibold">
              <button @click="printQr(viewing)" class="text-brand-600 dark:text-brand-300 hover:underline">{{ t('assets.print') }}</button>
              <span class="text-line-strong">|</span>
              <a :href="viewing.qr_code_url" :download="`qr-${viewing.asset_code}.png`" class="text-brand-600 dark:text-brand-300 hover:underline">{{ t('common.download') }}</a>
            </div>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-x-6 gap-y-4">
          <div><p class="text-xs font-semibold text-faint uppercase tracking-wide">{{ t('assets.category') }}</p><p class="font-semibold text-fg mt-0.5">{{ viewing.category?.name || '—' }}</p></div>
          <div><p class="text-xs font-semibold text-faint uppercase tracking-wide">{{ t('assets.condition') }}</p><p class="font-semibold text-fg mt-0.5 capitalize">{{ viewing.condition || '—' }}</p></div>
          <div><p class="text-xs font-semibold text-faint uppercase tracking-wide">{{ t('assets.brand') }}</p><p class="font-semibold text-fg mt-0.5">{{ viewing.brand || '—' }}</p></div>
          <div><p class="text-xs font-semibold text-faint uppercase tracking-wide">{{ t('assets.model') }}</p><p class="font-semibold text-fg mt-0.5">{{ viewing.model || '—' }}</p></div>
          <div><p class="text-xs font-semibold text-faint uppercase tracking-wide">{{ t('assets.serial_number') }}</p><p class="font-semibold text-fg mt-0.5">{{ viewing.serial_number || '—' }}</p></div>
          <div><p class="text-xs font-semibold text-faint uppercase tracking-wide">{{ t('assets.purchase_date') }}</p><p class="font-semibold text-fg mt-0.5">{{ viewing.purchase_date || '—' }}</p></div>
          <div><p class="text-xs font-semibold text-faint uppercase tracking-wide">{{ t('assets.purchase_price') }}</p><p class="font-semibold text-fg mt-0.5">{{ money(viewing.purchase_price) }}</p></div>
        </div>
        <div v-if="viewing.description" class="pt-3 border-t border-line">
          <p class="text-xs font-semibold text-faint uppercase tracking-wide mb-1">{{ t('common.description') }}</p>
          <p class="text-sm text-muted">{{ viewing.description }}</p>
        </div>
        <div class="flex items-center justify-end gap-3 pt-2">
          <button @click="openFlag(viewing)" class="btn-ghost btn-sm text-amber-600 dark:text-amber-400">{{ t('assets.flag_issue') }}</button>
          <button @click="regenerateQr(viewing)" class="btn-ghost btn-sm">{{ t('assets.regenerate_qr') }}</button>
          <button v-if="canEdit" @click="openEdit(viewing); viewing = null" class="btn-primary btn-sm">{{ t('assets.edit_asset') }}</button>
        </div>
      </div>
    </Modal>

    <!-- Flag Issue -->
    <Modal v-if="flagging" :title="t('assets.flag_issue')" @close="flagging = null">
      <form @submit.prevent="submitFlag">
        <div class="p-6 space-y-4">
          <p class="text-sm text-muted">{{ flagging.name }} <span class="font-mono text-xs text-faint">({{ flagging.asset_code }})</span></p>
          <div>
            <label class="label">{{ t('assets.flag_note_label') }} <span class="text-red-500">*</span></label>
            <textarea v-model="flagNote" rows="3" required class="textarea"></textarea>
          </div>
          <div>
            <label class="label">{{ t('assets.flag_condition_label') }}</label>
            <select v-model="flagCondition" class="select">
              <option value="">{{ t('assets.flag_condition_none') }}</option>
              <option value="broken">{{ t('assets.condition_broken') }}</option>
              <option value="lost">{{ t('assets.condition_lost') }}</option>
            </select>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" :disabled="flagSubmitting" class="btn-primary">
            {{ flagSubmitting ? t('assets.saving') : t('assets.flag_submit') }}
          </button>
          <button type="button" class="btn-ghost" @click="flagging = null">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
    <ConfirmDialog
      v-if="confirmingBulkDelete"
      :title="selectedIds.length === 1 ? t('assets.delete_confirm_title_one') : t('assets.delete_confirm_title_other', { count: selectedIds.length })"
      :message="t('assets.delete_confirm_message')"
      @confirm="confirmBulkDelete"
      @cancel="confirmingBulkDelete = false"
    />
  </AppLayout>
</template>
