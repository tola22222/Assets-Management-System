<script setup>
import { ref, computed, onMounted, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import { onRowClick } from '../../utils/rowClick'
import Modal from '../../components/ui/Modal.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import DetailModal from '../../components/ui/DetailModal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import LocationFilter from '../../components/ui/LocationFilter.vue'
import SearchSelect from '../../components/ui/SearchSelect.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableFilter } from '../../composables/useTableFilter'
import { useTableSort } from '../../composables/useTableSort'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'
import { usePermissions } from '../../composables/usePermissions'
import TablePagination from '../../components/ui/TablePagination.vue'
import { usePagination } from '../../composables/usePagination'
import { downloadExcel, exportNote } from '../../utils/excelExport'
import ImageField from '../../components/ui/ImageField.vue'

const { t } = useI18n()
const { items: verifications, loading, fetchAll, destroy } = useApiCrud('/asset-verifications', { entityName: t('asset_verifications.entity') })
const toast = useToastStore()
const auth = useAuthStore()
// Only OPM/Finance can submit a verification directly (role:operations_hr_manager,finance_manager
// on the store route) — staff submit condition reports via the QR scan flow instead, and ED has no
// direct-submit access either. Don't offer a button that would 403.
// A custom role granting Verification → Create gets it too (usePermissions().allows).
const { allows } = usePermissions()
const canCreate = computed(() => allows(['operations_hr_manager', 'finance_manager'], 'asset-verifications', 'create'))

const { search, filtered: searched } = useTableSearch(verifications, [(v) => v.asset?.name, (v) => v.asset?.asset_code, (v) => v.location?.name])
// Location filter (the drop-down beside search), applied after search and before sort.
const { filters, filtered: filteredVerifications } = useTableFilter(searched, {
  location: (v, val) => String(v.location_id) === val,
})
const { sortKey, sortDir, toggleSort, sorted: sortedVerifications } = useTableSort(filteredVerifications, {
  defaultKey: 'created_at', defaultDir: 'desc',
  paths: { asset: 'asset.name', location: 'location.name', verified_by: 'verified_by.name' },
})

// ---- CSV export -----------------------------------------------------------
// Exactly the rows on screen — after search, the location filter and sort —
// with the audit fields a verification keeps (before / after condition, how
// many units it took out of use, who and when, and the reason).
// ---- Excel export -----------------------------------------------------------
// Exactly the rows on screen — after search, the location filter and sort —
// grouped by location, with the audit fields a verification keeps (before /
// after condition, how many units it took out of use, who and when, why).
async function exportCsv() {
  const condition = (c) => (c ? t(`asset_verifications.condition_${c}`) : '')
  const location = filters.location
    ? sortedVerifications.value.find((v) => String(v.location_id) === String(filters.location))?.location?.name
    : null
  await downloadExcel({
    fileName: 'asset-verifications',
    title: t('asset_verifications.title'),
    subtitle: location || t('export.all_locations'),
    note: exportNote(t('export.generated'), [search.value && t('export.filter_search', { q: search.value })]),
    sheets: [{
      name: t('asset_verifications.title'),
      columns: [
        { key: 'date', header: t('common.date'), type: 'code', width: 17 },
        { key: 'asset', header: t('common.asset'), type: 'text' },
        { key: 'asset_code', header: t('assets.code'), type: 'code' },
        { key: 'quantity', header: t('common.quantity'), type: 'qty', width: 9 },
        { key: 'previous_condition', header: t('asset_verifications.previous_condition'), type: 'code' },
        { key: 'condition', header: t('asset_returns.condition'), type: 'code' },
        { key: 'out_of_use', header: t('asset_verifications.out_of_use'), type: 'qty', width: 10 },
        { key: 'verified_by', header: t('asset_verifications.verified_by'), type: 'text' },
        { key: 'remark', header: t('asset_verifications.remark'), type: 'text' },
      ],
      rows: sortedVerifications.value.map((v) => ({
        date: (v.verified_at || v.created_at || '').replace('T', ' ').slice(0, 16),
        asset: v.asset?.name || '',
        asset_code: v.asset?.asset_code || '',
        quantity: v.quantity_verified ?? 1,
        previous_condition: condition(v.previous_condition),
        condition: condition(v.condition),
        out_of_use: v.quantity_affected ?? 0,
        verified_by: v.verified_by?.name || '',
        remark: v.remark || '',
        _location: v.location?.name || t('stock.no_location'),
        _code: v.location?.code || '',
      })),
      group: {
        by: (r) => r._location,
        label: (key, rows) => (rows[0]._code ? `${key} ( ${rows[0]._code} )` : key),
        code: (key, rows) => rows[0]._code || key,
        codeColumn: 'asset_code',
      },
      totalLabel: t('export.total'),
    }],
  })
}

// View renders the row the table already holds — /asset-verifications has no
// show endpoint, and its index returns the asset, location and verifier.
const viewing = ref(null)
const deletingId = ref(null)

// destroy sits behind role:operations_hr_manager,finance_manager, so only HR / the Accountant get the button
// at all; there is no status guard on the server side for this one.
const canDelete = computed(() => allows(['operations_hr_manager', 'finance_manager'], 'asset-verifications', 'delete'))

const viewRows = computed(() => {
  const v = viewing.value
  if (!v) return []
  return [
    { label: t('common.asset'), value: v.asset?.name },
    { label: t('assets.code'), value: v.asset?.asset_code, type: 'code' },
    { label: t('common.location'), value: v.location?.name },
    { label: t('common.quantity'), value: v.quantity_verified },
    { label: t('asset_returns.condition'), value: v.condition, type: 'capitalize' },
    { label: t('asset_verifications.verified_by'), value: v.verified_by?.name },
    { label: t('common.date'), value: (v.verified_at || v.created_at || '').slice(0, 10) },
    { label: t('asset_verifications.remark'), value: v.remark, type: 'multiline' },
    { label: t('asset_verifications.photo'), value: v.image_url, type: 'image' },
  ]
})

async function confirmDelete() {
  const id = deletingId.value
  deletingId.value = null
  try {
    await destroy(id)
  } catch {
    // useApiCrud already surfaced the server's message.
  }
}

const assets = ref([])
const locations = ref([])
const showModal = ref(false)
const imageFile = ref(null)
const form = reactive({ asset_id: '', location_id: '', quantity_verified: 1, condition: 'good', remark: '' })
// An asset is verified where the register has it (moving it is a transfer),
// so picking one fills in its location. The server refuses a mismatch.
watch(() => form.asset_id, (id) => {
  const at = assets.value.find((a) => String(a.id) === String(id))?.location_id
  if (at) form.location_id = at
})

async function loadOptions() {
  try {
    const [a, l] = await Promise.all([http.get('/assets'), http.get('/locations')])
    assets.value = a.data
    locations.value = l.data
  } catch (e) {
    toast.error(errorMessage(e, t('asset_verifications.options_failed')))
  }
}

function openCreate() {
  Object.assign(form, { asset_id: '', location_id: '', quantity_verified: 1, condition: 'good', remark: '' })
  imageFile.value = null
  showModal.value = true
}

async function handleSubmit() {
  const fd = new FormData()
  Object.entries(form).forEach(([k, v]) => fd.append(k, v))
  if (imageFile.value) fd.append('image', imageFile.value)

  try {
    await http.post('/asset-verifications', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.success(t('asset_verifications.recorded'))
    showModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(errorMessage(e, t('asset_verifications.record_failed')))
  }
}

async function complete(id) {
  try {
    await http.post(`/asset-verifications/${id}/complete`)
    toast.success(t('asset_verifications.marked_complete'))
    await fetchAll()
  } catch (e) {
    // The route is OPM-only while the button renders for every role, so this
    // 403s for Finance, ED and Staff — previously in complete silence.
    toast.error(errorMessage(e, t('asset_verifications.complete_failed')))
  }
}

onMounted(() => {
  fetchAll()
  loadOptions()
})

// Pagination is the last step, applied to the finished list, so search
// and sort still consider every row rather than just the page on screen.
const { page, rowsPerPage, total, paged } = usePagination(sortedVerifications)
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 space-y-6">
      <div class="card p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('asset_verifications.title') }}</h1>
            <p class="text-muted text-sm mt-1">{{ t('asset_verifications.subtitle') }}</p>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <button @click="exportCsv" class="btn-ghost btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 12L12 16.5m0 0l4.5-4.5M12 16.5V3" /></svg>
              {{ t('assets.export_csv') }}
            </button>
            <button v-if="canCreate" @click="openCreate" class="btn-primary btn-sm">
              <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
              {{ t('asset_verifications.new') }}
            </button>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 mb-6">
          <div class="flex-1 min-w-[260px]">
            <SearchInput v-model="search" :placeholder="t('common.search')" />
          </div>
          <LocationFilter v-model="filters.location" />
        </div>

        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th class="th-sort" @click="toggleSort('asset')">{{ t('common.asset') }}<TableSortIcon :active="sortKey === 'asset'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('location')">{{ t('common.location') }}<TableSortIcon :active="sortKey === 'location'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('condition')">{{ t('asset_returns.condition') }}<TableSortIcon :active="sortKey === 'condition'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('verified_by')">{{ t('asset_verifications.verified_by') }}<TableSortIcon :active="sortKey === 'verified_by'" :direction="sortDir" /></th>
                <th>{{ t('asset_verifications.photo') }}</th>
                <th>{{ t('common.status') }}</th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="v in paged" :key="v.id" class="cursor-pointer" @click="onRowClick($event, () => viewing = v)">
                <td class="font-medium text-fg">{{ v.asset?.name || t('common.n_a') }}</td>
                <td>{{ v.location?.name || t('common.n_a') }}</td>
                <td class="capitalize">{{ v.condition }}</td>
                <td>{{ v.verified_by?.name || t('common.n_a') }}</td>
                <td>
                  <a v-if="v.image_url" :href="v.image_url" target="_blank"><img :src="v.image_url" class="w-9 h-9 rounded-lg object-cover border border-line" alt="" /></a>
                  <span v-else class="text-faint">—</span>
                </td>
                <td>
                  <span :class="v.verified_at ? 'badge-success' : 'badge-warning'">
                    {{ v.verified_at ? t('asset_verifications.complete') : t('asset_verifications.pending') }}
                  </span>
                </td>
                <td class="text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <button @click="viewing = v" :title="t('common.view')" :aria-label="t('common.view')" class="btn-icon-view">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                    </button>
                    <button v-if="!v.verified_at" @click="complete(v.id)" :title="t('common.mark_complete')" :aria-label="t('common.mark_complete')" class="btn-icon-success">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </button>
                    <button
                      v-if="canDelete"
                      @click="deletingId = v.id"
                      :title="t('common.delete')"
                      :aria-label="t('common.delete')"
                      class="btn-icon-danger"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!loading && !sortedVerifications.length">
                <td colspan="7" class="py-10 text-center text-faint">{{ t('asset_verifications.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <TablePagination v-model:page="page" v-model:rows-per-page="rowsPerPage" :count="total" />
      </div>
    </div>

    <Modal v-if="showModal" :title="t('asset_verifications.modal_title')" @close="showModal = false">
      <form class="modal-form" @submit.prevent="handleSubmit">
        <div class="modal-body space-y-4">
          <div class="form-group">
            <label class="label">{{ t('asset_verifications.asset_required') }}</label>
            <SearchSelect v-model="form.asset_id" required input-class="input" :placeholder="t('common.select_asset')"
              :options="assets.map((a) => ({ value: a.id, label: a.name, sub: a.asset_code }))" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
              <label class="label">{{ t('asset_verifications.location_required') }}</label>
              <SearchSelect v-model="form.location_id" required input-class="input" :placeholder="t('common.select_location')"
                :options="locations.map((l) => ({ value: l.id, label: l.name }))" />
            </div>
            <div class="form-group">
              <label class="label">{{ t('asset_verifications.quantity_verified_required') }}</label>
              <input v-model.number="form.quantity_verified" type="number" min="1" required class="input" />
            </div>
          </div>
          <div class="form-group">
            <label class="label">{{ t('asset_verifications.condition_required') }}</label>
            <select v-model="form.condition" class="input">
              <option value="good">{{ t('asset_verifications.condition_good') }}</option>
              <option value="fair">{{ t('asset_verifications.condition_fair') }}</option>
              <option value="broken">{{ t('asset_verifications.condition_broken') }}</option>
              <option value="lost">{{ t('asset_verifications.condition_lost') }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="label">{{ t('asset_verifications.remark') }}</label>
            <!-- Broken / lost takes the unit out of use: the reason is required. -->
            <textarea v-model="form.remark" rows="2" class="textarea"
              :required="['broken', 'lost'].includes(form.condition)"
              :placeholder="['broken', 'lost'].includes(form.condition) ? t('asset_verifications.reason_required') : ''"></textarea>
          </div>
          <div class="form-group">
            <label class="label">{{ t('asset_verifications.photo_reference') }}</label>
            <ImageField v-model="imageFile" :hint="t('image.field_hint')" />
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ t('asset_verifications.submit_button') }}
          </button>
        </div>
      </form>
    </Modal>
    <DetailModal
      v-if="viewing"
      :title="t('common.details')"
      :rows="viewRows"
      @close="viewing = null"
    />

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
