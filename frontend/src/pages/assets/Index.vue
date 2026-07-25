<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ColumnVisibilityMenu from '../../components/ui/ColumnVisibilityMenu.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useServerTable } from '../../composables/useServerTable'
import { useColumnVisibility } from '../../composables/useColumnVisibility'
import { useConfirm } from '../../composables/useConfirm'
import { useToastStore } from '../../stores/toast'
import { exportCsv } from '../../utils/exportCsv'

const { t } = useI18n()
const toast = useToastStore()
const { confirm } = useConfirm()

const {
  items: assetsList, loading, page, perPage, total,
  search, setSearch, sortBy, sortDir, sortByVuetify, handleOptions,
  fetchPage,
} = useServerTable('/assets', {
  defaultSort: 'created_at',
  defaultDir: 'desc',
})

const categories = ref([])
const locations = ref([])
const showModal = ref(false)
const editingId = ref(null)
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

// --- Row selection (AppDataTable's show-select checkbox column) ---
const selected = ref([])
function clearSelection() {
  selected.value = []
}

const STATUS_CHIP_COLOR = { active: 'success', disposed: undefined }
const CONDITION_CHIP_COLOR = { good: 'success', fair: 'warning', broken: 'error', lost: 'error' }

const headers = computed(() => {
  const cols = [{ title: t('assets.asset_col'), key: 'name', sortable: true }]

  if (isVisible('code')) cols.push({ title: t('assets.code'), key: 'asset_code', sortable: true })
  if (isVisible('category')) cols.push({ title: t('assets.category'), key: 'category', sortable: false })
  if (isVisible('brand')) cols.push({ title: t('assets.brand'), key: 'brand', sortable: true })
  if (isVisible('model')) cols.push({ title: t('assets.model'), key: 'model', sortable: true })
  if (isVisible('serial_number')) cols.push({ title: t('common.serial_number'), key: 'serial_number', sortable: false })
  if (isVisible('condition')) cols.push({ title: t('assets.condition'), key: 'condition', sortable: true })
  if (isVisible('status')) cols.push({ title: t('common.status'), key: 'status', sortable: true })
  if (isVisible('assigned_to')) cols.push({ title: t('assets.assigned_to'), key: 'assigned_to', sortable: false })
  if (isVisible('location')) cols.push({ title: t('common.location'), key: 'location', sortable: false })
  if (isVisible('purchase_date')) cols.push({ title: t('assets.purchase_date'), key: 'purchase_date', sortable: true })
  if (isVisible('price')) cols.push({ title: t('common.price'), key: 'purchase_price', sortable: true, align: 'end' })

  cols.push({ title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 60 })
  return cols
})

const emptyForm = () => ({
  name: '', category_id: '', location_id: '', description: '', model: '', brand: '',
  serial_number: '', purchase_date: '', purchase_price: '', condition: 'good', status: 'active',
})
const form = reactive(emptyForm())

function money(v) {
  return v ? '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'
}

// per_page:100 — these endpoints now paginate for their own table pages, but this
// form dropdown needs the full lookup list (both are small reference tables, well
// under 100 rows for this org).
async function loadCategories() {
  const { data } = await http.get('/categories', { params: { per_page: 100 } })
  categories.value = data.data ?? data
}

async function loadLocations() {
  const { data } = await http.get('/locations', { params: { per_page: 100 } })
  locations.value = data.data ?? data
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

async function handleDelete(item) {
  const ok = await confirm({
    title: t('confirm.delete_title'),
    message: t('confirm.delete_message'),
    color: 'error',
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return
  try {
    await http.delete(`/assets/${item.id}`)
    toast.success(t('common.deleted_successfully', { entity: t('assets.entity') }))
    selected.value = selected.value.filter((id) => id !== item.id)
    await fetchPage()
  } catch (e) {
    toast.error(e.response?.data?.message || t('assets.save_failed'))
  }
}

async function bulkDelete() {
  bulkDeleting.value = true
  try {
    await http.post('/assets/bulk-delete', { ids: selected.value })
    toast.success(t('assets.bulk_deleted', { count: selected.value.length }))
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
  const rows = assetsList.value.filter((a) => selected.value.includes(a.id))
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
  <v-container fluid class="pa-0">
      <AppPageHeader
        :title="t('assets.title')"
        :subtitle="t('assets.subtitle')"
        :actions="[{ label: t('assets.register'), icon: 'mdi-plus', onClick: openCreate }]"
      />

      <AppDataTable
        :headers="headers"
        :items="assetsList"
        :items-length="total"
        :loading="loading"
        :page="page"
        :items-per-page="perPage"
        :items-per-page-options="[10, 25, 50, 100]"
        :sort-by="sortByVuetify"
        :search="search"
        :search-label="t('assets.search_placeholder')"
        show-select
        item-value="id"
        :selected="selected"
        @update:selected="(v) => (selected = v)"
        @update:search="setSearch"
        @update:options="handleOptions"
      >
        <template #toolbar-end>
          <ColumnVisibilityMenu :columns="ALL_COLUMNS" :isVisible="isVisible" @toggle="toggleColumn" @reset="resetColumns" />
          <v-btn variant="outlined" size="small" prepend-icon="mdi-tray-arrow-down" @click="exportFiltered">{{ t('common.export') }}</v-btn>
          <v-btn variant="outlined" size="small" prepend-icon="mdi-tray-arrow-up" to="/assets/import">{{ t('assets.import') }}</v-btn>
        </template>

        <template #bulk-actions="{ selected: sel, clear }">
          <span class="font-semibold text-body-2">{{ t('common.n_selected', { count: sel.length }) }}</span>
          <v-btn variant="outlined" size="small" @click="exportSelected">{{ t('common.export') }}</v-btn>
          <v-btn variant="flat" color="error" size="small" :loading="bulkDeleting" @click="bulkDelete">{{ t('common.delete') }}</v-btn>
          <v-btn variant="text" size="small" class="ml-auto" @click="clear">{{ t('common.clear_selection') }}</v-btn>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center ga-3">
            <v-avatar v-if="item.image_url" :image="item.image_url" rounded="lg" size="36" />
            <v-avatar v-else rounded="lg" size="36" color="surface-variant">
              <span class="text-caption">{{ t('common.n_a') }}</span>
            </v-avatar>
            <div class="min-w-0 flex-grow-1" style="min-width: 0">
              <button class="font-medium text-truncate text-decoration-none d-block text-left" style="width: 100%" @click="viewing = item">{{ item.name }}</button>
              <p v-if="item.brand || item.model" class="text-caption text-medium-emphasis text-truncate">{{ [item.brand, item.model].filter(Boolean).join(' ') }}</p>
            </div>
          </div>
        </template>

        <template #item.asset_code="{ item }"><span class="font-mono text-caption">{{ item.asset_code }}</span></template>
        <template #item.category="{ item }">{{ item.category?.name || '—' }}</template>
        <template #item.brand="{ item }">{{ item.brand || '—' }}</template>
        <template #item.model="{ item }">{{ item.model || '—' }}</template>
        <template #item.serial_number="{ item }"><span class="font-mono text-caption">{{ item.serial_number || '—' }}</span></template>
        <template #item.condition="{ item }">
          <v-chip size="small" :color="CONDITION_CHIP_COLOR[item.condition]" variant="tonal">{{ t(`assets.condition_${item.condition}`) || item.condition }}</v-chip>
        </template>
        <template #item.status="{ item }">
          <v-chip size="small" :color="STATUS_CHIP_COLOR[item.status]" variant="tonal">{{ t(`status.${item.status}`) }}</v-chip>
        </template>
        <template #item.assigned_to="{ item }">{{ item.current_assignee || t('common.unassigned') }}</template>
        <template #item.location="{ item }">{{ item.location?.name || '—' }}</template>
        <template #item.purchase_date="{ item }">{{ item.purchase_date || '—' }}</template>
        <template #item.purchase_price="{ item }"><span class="font-medium">{{ money(item.purchase_price) }}</span></template>

        <template #item.actions="{ item }">
          <v-menu>
            <template #activator="{ props: menuProps }">
              <v-btn icon="mdi-dots-vertical" size="small" variant="text" v-bind="menuProps" />
            </template>
            <v-list density="compact">
              <v-list-item :title="t('common.view')" @click="viewing = item" />
              <v-list-item :title="t('common.edit')" @click="openEdit(item)" />
              <v-list-item :title="t('assets.flag_issue')" @click="openFlag(item)" />
              <v-list-item :title="t('assets.regenerate_qr')" @click="regenerateQr(item)" />
              <v-list-item :title="t('assets.print')" @click="printQr(item)" />
              <v-divider />
              <v-list-item :title="t('common.delete')" class="text-error" @click="handleDelete(item)" />
            </v-list>
          </v-menu>
        </template>

        <template #no-data>
          <div class="d-flex flex-column align-center ga-2 py-10">
            <v-icon icon="mdi-package-variant-closed" size="40" class="text-medium-emphasis" />
            <p class="text-body-2 font-weight-medium">{{ search ? t('assets.empty_search') : t('assets.empty') }}</p>
            <p class="text-caption text-medium-emphasis">{{ search ? t('assets.empty_search_hint') : t('assets.empty_hint') }}</p>
          </div>
        </template>
      </AppDataTable>
  </v-container>

    <!-- Create / Edit -->
    <Modal v-if="showModal" :title="editingId ? t('assets.edit_title') : t('assets.create_title')" wide @close="showModal = false">
      <v-form @submit.prevent="handleSubmit">
        <v-card-text class="d-flex flex-column ga-1">
          <v-row dense>
            <v-col cols="12" sm="6">
              <v-text-field v-model="form.name" :label="t('assets.name_required')" required />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.category_id"
                :label="t('assets.category_required')"
                :items="categories.map((c) => ({ title: c.name, value: c.id }))"
                required
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.location_id"
                :label="t('assets.location_required')"
                :items="locations.map((l) => ({ title: l.name, value: l.id }))"
                required
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field v-model="form.brand" :label="t('assets.brand')" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field v-model="form.model" :label="t('assets.model')" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field v-model="form.serial_number" :label="t('assets.serial_number')" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field v-model="form.purchase_date" type="date" :label="t('assets.purchase_date')" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field v-model="form.purchase_price" type="number" step="0.01" :label="t('assets.purchase_price')" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.condition"
                :label="t('assets.condition')"
                :items="[
                  { title: t('assets.condition_good'), value: 'good' },
                  { title: t('assets.condition_fair'), value: 'fair' },
                  { title: t('assets.condition_broken'), value: 'broken' },
                  { title: t('assets.condition_lost'), value: 'lost' },
                ]"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.status"
                :label="t('common.status')"
                :items="[
                  { title: t('status.active'), value: 'active' },
                  { title: t('status.disposed'), value: 'disposed' },
                ]"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-file-input
                v-model="imageFile"
                :label="t('assets.photo')"
                accept="image/jpeg,image/png"
                variant="outlined"
                density="comfortable"
                prepend-icon=""
                prepend-inner-icon="mdi-camera-outline"
              />
            </v-col>
          </v-row>
          <v-textarea v-model="form.description" :label="t('common.description')" rows="2" />
        </v-card-text>
        <v-card-actions class="px-4 pb-4">
          <v-btn type="submit" color="primary" variant="flat" prepend-icon="mdi-plus" :loading="submitting">
            {{ editingId ? t('assets.save_changes') : t('assets.register') }}
          </v-btn>
          <v-btn variant="text" @click="showModal = false">{{ t('common.cancel') }}</v-btn>
        </v-card-actions>
      </v-form>
    </Modal>

    <!-- Detail view -->
    <Modal v-if="viewing" :title="t('assets.detail_title')" wide @close="viewing = null">
      <v-card-text class="d-flex flex-column ga-6">
        <div class="d-flex align-start ga-5">
          <v-img v-if="viewing.image_url" :src="viewing.image_url" width="112" height="96" rounded="lg" cover class="flex-shrink-0" />
          <v-sheet v-else width="112" height="96" rounded="lg" color="surface-variant" class="d-flex align-center justify-center text-caption flex-shrink-0">
            {{ t('assets.no_image_full') }}
          </v-sheet>
          <div class="flex-grow-1" style="min-width: 0">
            <h4 class="text-h6 font-weight-bold text-truncate">{{ viewing.name }}</h4>
            <p class="text-body-2 font-mono text-medium-emphasis mt-1">{{ viewing.asset_code }}</p>
            <v-chip size="small" class="mt-2" :color="viewing.status === 'active' ? 'success' : undefined" variant="tonal">
              {{ t(`status.${viewing.status}`) }}
            </v-chip>
          </div>
          <div v-if="viewing.qr_code_url" class="flex-shrink-0 text-center">
            <v-img :src="viewing.qr_code_url" width="96" height="96" rounded="lg" class="border bg-white mx-auto pa-1" />
            <div class="d-flex align-center justify-center ga-2 mt-1 text-caption font-weight-bold">
              <button class="text-primary" @click="printQr(viewing)">{{ t('assets.print') }}</button>
              <span>|</span>
              <a :href="viewing.qr_code_url" :download="`qr-${viewing.asset_code}.png`" class="text-primary">{{ t('common.download') }}</a>
            </div>
          </div>
        </div>
        <v-row dense>
          <v-col cols="6"><p class="text-caption font-weight-semibold text-medium-emphasis text-uppercase">{{ t('assets.category') }}</p><p class="font-weight-semibold mt-1">{{ viewing.category?.name || '—' }}</p></v-col>
          <v-col cols="6"><p class="text-caption font-weight-semibold text-medium-emphasis text-uppercase">{{ t('assets.condition') }}</p><p class="font-weight-semibold text-capitalize mt-1">{{ viewing.condition || '—' }}</p></v-col>
          <v-col cols="6"><p class="text-caption font-weight-semibold text-medium-emphasis text-uppercase">{{ t('assets.brand') }}</p><p class="font-weight-semibold mt-1">{{ viewing.brand || '—' }}</p></v-col>
          <v-col cols="6"><p class="text-caption font-weight-semibold text-medium-emphasis text-uppercase">{{ t('assets.model') }}</p><p class="font-weight-semibold mt-1">{{ viewing.model || '—' }}</p></v-col>
          <v-col cols="6"><p class="text-caption font-weight-semibold text-medium-emphasis text-uppercase">{{ t('assets.serial_number') }}</p><p class="font-weight-semibold mt-1">{{ viewing.serial_number || '—' }}</p></v-col>
          <v-col cols="6"><p class="text-caption font-weight-semibold text-medium-emphasis text-uppercase">{{ t('common.location') }}</p><p class="font-weight-semibold mt-1">{{ viewing.location?.name || '—' }}</p></v-col>
          <v-col cols="6"><p class="text-caption font-weight-semibold text-medium-emphasis text-uppercase">{{ t('assets.assigned_to') }}</p><p class="font-weight-semibold mt-1">{{ viewing.current_assignee || t('common.unassigned') }}</p></v-col>
          <v-col cols="6"><p class="text-caption font-weight-semibold text-medium-emphasis text-uppercase">{{ t('assets.purchase_date') }}</p><p class="font-weight-semibold mt-1">{{ viewing.purchase_date || '—' }}</p></v-col>
          <v-col cols="6"><p class="text-caption font-weight-semibold text-medium-emphasis text-uppercase">{{ t('assets.purchase_price') }}</p><p class="font-weight-semibold mt-1">{{ money(viewing.purchase_price) }}</p></v-col>
        </v-row>
        <div v-if="viewing.description" class="pt-3 border-t">
          <p class="text-caption font-weight-semibold text-medium-emphasis text-uppercase mb-1">{{ t('common.description') }}</p>
          <p class="text-body-2 text-medium-emphasis">{{ viewing.description }}</p>
        </div>
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-spacer />
        <v-btn variant="text" color="warning" @click="openFlag(viewing)">{{ t('assets.flag_issue') }}</v-btn>
        <v-btn variant="text" @click="regenerateQr(viewing)">{{ t('assets.regenerate_qr') }}</v-btn>
        <v-btn variant="flat" color="primary" @click="openEdit(viewing); viewing = null">{{ t('assets.edit_asset') }}</v-btn>
      </v-card-actions>
    </Modal>

    <!-- Flag Issue -->
    <Modal v-if="flagging" :title="t('assets.flag_issue')" @close="flagging = null">
      <v-form @submit.prevent="submitFlag">
        <v-card-text class="d-flex flex-column ga-1">
          <p class="text-body-2 text-medium-emphasis">{{ flagging.name }} <span class="font-mono text-caption">({{ flagging.asset_code }})</span></p>
          <v-textarea v-model="flagNote" :label="t('assets.flag_note_label')" rows="3" required />
          <v-select
            v-model="flagCondition"
            :label="t('assets.flag_condition_label')"
            :items="[
              { title: t('assets.flag_condition_none'), value: '' },
              { title: t('assets.condition_broken'), value: 'broken' },
              { title: t('assets.condition_lost'), value: 'lost' },
            ]"
          />
        </v-card-text>
        <v-card-actions class="px-4 pb-4">
          <v-btn type="submit" color="primary" variant="flat" :loading="flagSubmitting">{{ t('assets.flag_submit') }}</v-btn>
          <v-btn variant="text" @click="flagging = null">{{ t('common.cancel') }}</v-btn>
        </v-card-actions>
      </v-form>
    </Modal>
</template>
