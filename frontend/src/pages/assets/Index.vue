<script setup>
import { ref, h, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { NDataTable, NDropdown, NButton, NTag, NIcon } from 'naive-ui'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import ColumnVisibilityMenu from '../../components/ui/ColumnVisibilityMenu.vue'
import { useServerTable } from '../../composables/useServerTable'
import { useColumnVisibility } from '../../composables/useColumnVisibility'
import { useToastStore } from '../../stores/toast'
import { exportCsv } from '../../utils/exportCsv'

const { t } = useI18n()
const toast = useToastStore()

const {
  items: assetsList, loading, page, perPage, total, lastPage,
  search, setSearch, sortBy, sortDir,
  goToPage, setPerPage, fetchPage,
} = useServerTable('/assets', {
  defaultSort: 'created_at',
  defaultDir: 'desc',
})

const categories = ref([])
const locations = ref([])
const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const bulkDeleting = ref(false)
const viewing = ref(null)
const imageFile = ref(null)
const submitting = ref(false)
const flagging = ref(null)
const flagNote = ref('')
const flagCondition = ref('')
const flagSubmitting = ref(false)

// --- Columns ---
const ALL_COLUMNS = [
  { key: 'code', label: t('assets.code') },
  { key: 'category', label: t('assets.category') },
  { key: 'brand', label: t('assets.brand') },
  { key: 'model', label: t('assets.model') },
  { key: 'serial_number', label: t('common.serial_number') },
  { key: 'condition', label: t('assets.condition') },
  { key: 'status', label: t('common.status') },
  { key: 'assigned_to', label: t('assets.assigned_to') },
  { key: 'location', label: t('common.location') },
  { key: 'purchase_date', label: t('assets.purchase_date') },
  { key: 'price', label: t('common.price') },
]
const { isVisible, toggle: toggleColumn, reset: resetColumns } = useColumnVisibility(
  'assets.visibleColumns',
  ALL_COLUMNS.map((c) => c.key),
  ['brand', 'model', 'serial_number', 'location', 'purchase_date'],
)

// --- Row selection (n-data-table's native checkbox column) ---
const checkedRowKeys = ref([])
function clearSelection() {
  checkedRowKeys.value = []
}

// --- Server-side sort <-> n-data-table's controlled sortOrder ---
function sortOrderFor(key) {
  return sortBy.value === key ? (sortDir.value === 'asc' ? 'ascend' : 'descend') : false
}
function handleUpdateSorter(sorter) {
  if (!sorter || !sorter.order) {
    sortBy.value = 'created_at'
    sortDir.value = 'desc'
  } else {
    sortBy.value = sorter.columnKey
    sortDir.value = sorter.order === 'ascend' ? 'asc' : 'desc'
  }
  page.value = 1
  fetchPage()
}

const STATUS_TAG_TYPE = { active: 'success', disposed: 'default' }
const CONDITION_TAG_TYPE = { good: 'success', fair: 'warning', broken: 'error', lost: 'error' }

function rowActions(asset) {
  return [
    { label: t('common.view'), key: 'view' },
    { label: t('common.edit'), key: 'edit' },
    { label: t('assets.flag_issue'), key: 'flag' },
    { label: t('assets.regenerate_qr'), key: 'regenerate_qr' },
    { label: t('assets.print'), key: 'print' },
    { type: 'divider', key: 'd1' },
    { label: t('common.delete'), key: 'delete' },
  ]
}
function handleRowAction(key, asset) {
  if (key === 'view') viewing.value = asset
  else if (key === 'edit') openEdit(asset)
  else if (key === 'flag') openFlag(asset)
  else if (key === 'regenerate_qr') regenerateQr(asset)
  else if (key === 'print') printQr(asset)
  else if (key === 'delete') deletingId.value = asset.id
}

const columns = computed(() => {
  const cols = [
    { type: 'selection' },
    {
      title: t('assets.asset_col'),
      key: 'name',
      sorter: true,
      sortOrder: sortOrderFor('name'),
      render(asset) {
        return h('div', { class: 'flex items-center gap-3' }, [
          asset.image_url
            ? h('img', { src: asset.image_url, class: 'w-9 h-9 rounded-lg object-cover border border-line flex-shrink-0' })
            : h('span', { class: 'w-9 h-9 rounded-lg bg-surface-3 border border-line flex items-center justify-center text-faint text-[10px] flex-shrink-0' }, 'No'),
          h('div', { class: 'min-w-0' }, [
            h('button', {
              class: 'font-medium text-fg truncate hover:text-brand hover:underline text-left block',
              onClick: () => { viewing.value = asset },
            }, asset.name),
            (asset.brand || asset.model)
              ? h('p', { class: 'text-xs text-faint truncate' }, [asset.brand, asset.model].filter(Boolean).join(' '))
              : null,
          ]),
        ])
      },
    },
  ]

  if (isVisible('code')) {
    cols.push({
      title: t('assets.code'), key: 'asset_code', sorter: true, sortOrder: sortOrderFor('asset_code'),
      render: (a) => h('span', { class: 'font-mono text-xs' }, a.asset_code),
    })
  }
  if (isVisible('category')) {
    cols.push({ title: t('assets.category'), key: 'category', render: (a) => a.category?.name || '—' })
  }
  if (isVisible('brand')) {
    cols.push({ title: t('assets.brand'), key: 'brand', sorter: true, sortOrder: sortOrderFor('brand'), render: (a) => a.brand || '—' })
  }
  if (isVisible('model')) {
    cols.push({ title: t('assets.model'), key: 'model', sorter: true, sortOrder: sortOrderFor('model'), render: (a) => a.model || '—' })
  }
  if (isVisible('serial_number')) {
    cols.push({ title: t('common.serial_number'), key: 'serial_number', render: (a) => h('span', { class: 'font-mono text-xs' }, a.serial_number || '—') })
  }
  if (isVisible('condition')) {
    cols.push({
      title: t('assets.condition'), key: 'condition', sorter: true, sortOrder: sortOrderFor('condition'),
      render: (a) => h(NTag, { type: CONDITION_TAG_TYPE[a.condition] || 'default', size: 'small', round: true }, { default: () => t(`assets.condition_${a.condition}`) || a.condition }),
    })
  }
  if (isVisible('status')) {
    cols.push({
      title: t('common.status'), key: 'status', sorter: true, sortOrder: sortOrderFor('status'),
      render: (a) => h(NTag, { type: STATUS_TAG_TYPE[a.status] || 'default', size: 'small', round: true }, { default: () => t(`status.${a.status}`) }),
    })
  }
  if (isVisible('assigned_to')) {
    cols.push({ title: t('assets.assigned_to'), key: 'assigned_to', render: (a) => a.current_assignee || t('common.unassigned') })
  }
  if (isVisible('location')) {
    cols.push({ title: t('common.location'), key: 'location', render: (a) => a.location?.name || '—' })
  }
  if (isVisible('purchase_date')) {
    cols.push({ title: t('assets.purchase_date'), key: 'purchase_date', sorter: true, sortOrder: sortOrderFor('purchase_date'), render: (a) => a.purchase_date || '—' })
  }
  if (isVisible('price')) {
    cols.push({
      title: t('common.price'), key: 'purchase_price', sorter: true, sortOrder: sortOrderFor('purchase_price'),
      render: (a) => h('span', { class: 'font-medium text-fg' }, money(a.purchase_price)),
    })
  }

  cols.push({
    title: t('common.actions'),
    key: 'actions',
    width: 70,
    render(asset) {
      return h('div', { class: 'flex justify-end' }, [
        h(NDropdown, { trigger: 'click', options: rowActions(asset), onSelect: (key) => handleRowAction(key, asset) }, {
          default: () => h(NButton, { quaternary: true, circle: true, size: 'small' }, {
            icon: () => h(NIcon, null, { default: () => h('svg', { viewBox: '0 0 24 24', fill: 'currentColor' }, [h('circle', { cx: 12, cy: 5, r: 1.75 }), h('circle', { cx: 12, cy: 12, r: 1.75 }), h('circle', { cx: 12, cy: 19, r: 1.75 })]) }),
          }),
        }),
      ])
    },
  })

  return cols
})

const naivePagination = computed(() => ({
  page: page.value,
  pageSize: perPage.value,
  pageCount: Math.max(lastPage.value, 1),
  itemCount: total.value,
  showSizePicker: true,
  pageSizes: [10, 25, 50, 100],
  onUpdatePage: (p) => goToPage(p),
  onUpdatePageSize: (n) => setPerPage(n),
}))

const emptyForm = () => ({
  name: '', category_id: '', location_id: '', description: '', model: '', brand: '',
  serial_number: '', purchase_date: '', purchase_price: '', condition: 'good', status: 'active',
})
const form = reactive(emptyForm())

function money(v) {
  return v ? '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'
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
    await fetchPage()
  } catch (e) {
    toast.error(e.response?.data?.message || t('assets.save_failed'))
  } finally {
    submitting.value = false
  }
}

async function confirmDelete() {
  try {
    await http.delete(`/assets/${deletingId.value}`)
    toast.success(t('common.deleted_successfully', { entity: t('assets.entity') }))
    checkedRowKeys.value = checkedRowKeys.value.filter((id) => id !== deletingId.value)
    await fetchPage()
  } catch (e) {
    toast.error(e.response?.data?.message || t('assets.save_failed'))
  } finally {
    deletingId.value = null
  }
}

async function bulkDelete() {
  bulkDeleting.value = true
  try {
    await http.post('/assets/bulk-delete', { ids: checkedRowKeys.value })
    toast.success(t('assets.bulk_deleted', { count: checkedRowKeys.value.length }))
    clearSelection()
    await fetchPage()
  } catch (e) {
    toast.error(e.response?.data?.message || t('assets.save_failed'))
  } finally {
    bulkDeleting.value = false
  }
}

function rowsToCsvColumns() {
  return [
    { key: 'asset_code', label: t('assets.code') },
    { key: 'name', label: t('common.name') },
    { key: 'category', label: t('assets.category'), value: (r) => r.category?.name || '' },
    { key: 'brand', label: t('assets.brand') },
    { key: 'model', label: t('assets.model') },
    { key: 'serial_number', label: t('common.serial_number') },
    { key: 'condition', label: t('assets.condition') },
    { key: 'status', label: t('common.status') },
    { key: 'assigned_to', label: t('assets.assigned_to'), value: (r) => r.current_assignee || '' },
    { key: 'location', label: t('common.location'), value: (r) => r.location?.name || '' },
    { key: 'purchase_date', label: t('assets.purchase_date') },
    { key: 'purchase_price', label: t('assets.purchase_price') },
  ]
}

function exportSelected() {
  const rows = assetsList.value.filter((a) => checkedRowKeys.value.includes(a.id))
  exportCsv(`assets-selected-${Date.now()}.csv`, rows, rowsToCsvColumns())
}

async function exportFiltered() {
  try {
    const params = { sort_by: sortBy.value, sort_dir: sortDir.value }
    if (search.value) params.search = search.value
    const { data } = await http.get('/assets/export', { params })
    exportCsv(`assets-${Date.now()}.csv`, data.data, rowsToCsvColumns())
  } catch (e) {
    toast.error(t('assets.export_failed'))
  }
}

async function regenerateQr(asset) {
  await http.post(`/assets/${asset.id}/regenerate-qr`)
  toast.success(t('assets.qr_regenerated'))
  await fetchPage()
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
    await fetchPage()
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
  fetchPage()
  loadCategories()
  loadLocations()
})
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 max-w-7xl mx-auto space-y-6">
      <PageHeader :title="t('assets.title')" :subtitle="t('assets.subtitle')" :buttonText="t('assets.register')" @action="openCreate" />

      <div class="table-wrap">
        <div class="table-toolbar">
          <div class="w-full sm:max-w-xs">
            <SearchInput :modelValue="search" @update:modelValue="setSearch" :placeholder="t('assets.search_placeholder')" />
          </div>
          <div class="flex items-center gap-2 sm:ml-auto">
            <ColumnVisibilityMenu :columns="ALL_COLUMNS" :isVisible="isVisible" @toggle="toggleColumn" @reset="resetColumns" />
            <button @click="exportFiltered" class="btn-ghost btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 12L12 16.5m0 0L16.5 12M12 16.5V3" /></svg>
              {{ t('common.export') }}
            </button>
            <RouterLink to="/assets/import" class="btn-ghost btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
              {{ t('assets.import') }}
            </RouterLink>
          </div>
        </div>

        <!-- Bulk action bar -->
        <div v-if="checkedRowKeys.length > 0" class="flex items-center gap-3 px-4 py-2.5 bg-brand-50 dark:bg-brand-900/30 border-b border-line text-sm">
          <span class="font-semibold text-fg">{{ t('common.n_selected', { count: checkedRowKeys.length }) }}</span>
          <button @click="exportSelected" class="btn-ghost btn-sm">{{ t('common.export') }}</button>
          <button @click="bulkDelete" :disabled="bulkDeleting" class="btn-danger btn-sm">{{ t('common.delete') }}</button>
          <button @click="clearSelection" class="btn-subtle btn-sm ml-auto">{{ t('common.clear_selection') }}</button>
        </div>

        <n-data-table
          :columns="columns"
          :data="assetsList"
          :loading="loading"
          :row-key="(row) => row.id"
          :pagination="naivePagination"
          :remote="true"
          v-model:checked-row-keys="checkedRowKeys"
          max-height="65vh"
          :bordered="false"
          @update:sorter="handleUpdateSorter"
        >
          <template #empty>
            <div class="flex flex-col items-center gap-2 py-10">
              <svg class="w-10 h-10 text-line-strong" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
              <p class="text-muted text-sm font-medium">{{ search ? t('assets.empty_search') : t('assets.empty') }}</p>
              <p class="text-xs text-faint">{{ search ? t('assets.empty_search_hint') : t('assets.empty_hint') }}</p>
            </div>
          </template>
        </n-data-table>
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
            <p class="text-sm font-mono text-faint mt-1">{{ viewing.asset_code }}</p>
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
          <div><p class="text-xs font-semibold text-faint uppercase tracking-wide">{{ t('common.location') }}</p><p class="font-semibold text-fg mt-0.5">{{ viewing.location?.name || '—' }}</p></div>
          <div><p class="text-xs font-semibold text-faint uppercase tracking-wide">{{ t('assets.assigned_to') }}</p><p class="font-semibold text-fg mt-0.5">{{ viewing.current_assignee || t('common.unassigned') }}</p></div>
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
          <button @click="openEdit(viewing); viewing = null" class="btn-primary btn-sm">{{ t('assets.edit_asset') }}</button>
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
  </AppLayout>
</template>
