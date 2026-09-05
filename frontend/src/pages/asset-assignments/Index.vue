<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import Modal from '../../components/ui/Modal.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import DetailModal from '../../components/ui/DetailModal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableSort } from '../../composables/useTableSort'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'
import TablePagination from '../../components/ui/TablePagination.vue'
import { usePagination } from '../../composables/usePagination'

const { t } = useI18n()
const { items: assignments, loading, fetchAll, update, destroy } = useApiCrud('/asset-assignments', { entityName: t('asset_assignments.entity') })
const toast = useToastStore()
const auth = useAuthStore()
const canManage = computed(() => ['operations_hr_manager', 'finance_manager'].includes(auth.user?.role))

const { search, filtered: searched } = useTableSearch(assignments, [(a) => a.asset?.name, (a) => a.asset?.asset_code, 'recipient_name'])
const { sortKey, sortDir, toggleSort, sorted: sortedAssignments } = useTableSort(searched, {
  defaultKey: 'assigned_date', defaultDir: 'desc',
  paths: { asset: 'asset.name', location: 'location.name' },
})

// View renders the row the table already holds — /asset-assignments has no
// show endpoint, and its index returns the asset and location.
const viewing = ref(null)
const deletingId = ref(null)

// The server refuses to delete anything still out on loan (422); an assignment
// has to come back first. Deleting at all is OPM/Finance only.
const canDelete = (a) => canManage.value && a.status === 'returned'

const viewRows = computed(() => {
  const a = viewing.value
  if (!a) return []
  return [
    { label: t('common.asset'), value: a.asset?.name },
    { label: t('assets.code'), value: a.asset?.asset_code, type: 'code' },
    { label: t('asset_assignments.recipient'), value: a.recipient_name },
    { label: t('common.location'), value: a.location?.name },
    { label: t('common.quantity'), value: a.quantity },
    { label: t('asset_assignments.assigned_date'), value: (a.assigned_date || '').slice(0, 10) },
    { label: t('asset_assignments.due_date'), value: (a.due_date || '').slice(0, 10) },
    { label: t('common.status'), value: a.status, type: 'status' },
    { label: t('asset_assignments.remark'), value: a.remark, type: 'multiline' },
    { label: t('asset_assignments.photo'), value: a.image_url, type: 'image' },
  ]
})

// Edit is limited to the three fields AssetAssignmentController::update
// validates — location, due date and status. Asset, recipient and quantity are
// not editable server-side, so they are shown as read-only context instead of
// offered as inputs that would be silently dropped.
const editingId = ref(null)
const editContext = ref(null)
const editForm = reactive({ location_id: '', due_date: '', status: 'assigned' })

function openEdit(a) {
  editingId.value = a.id
  editContext.value = a
  Object.assign(editForm, {
    location_id: a.location_id || a.location?.id || '',
    due_date: (a.due_date || '').slice(0, 10),
    status: a.status,
  })
}

async function submitEdit() {
  try {
    await update(editingId.value, { ...editForm, due_date: editForm.due_date || null })
    toast.success(t('asset_assignments.updated'))
    editingId.value = null
  } catch (e) {
    toast.error(errorMessage(e, t('asset_assignments.update_failed')))
  }
}

async function confirmDelete() {
  const id = deletingId.value
  deletingId.value = null
  try {
    await destroy(id)
  } catch {
    // useApiCrud already surfaced the server's refusal message.
  }
}

const assets = ref([])
const locations = ref([])
const staffList = ref([])
const programs = ref([])
const showModal = ref(false)
const returningId = ref(null)
const returnCondition = ref('good')
const returnRemark = ref('')
const returnImageFile = ref(null)

function handleReturnFileChange(e) {
  returnImageFile.value = e.target.files[0] || null
}

const form = reactive({ asset_id: '', assigned_to_type: 'staff', assigned_to_id: '', location_id: '', quantity: 1, assigned_date: '', due_date: '' })

async function loadOptions() {
  try {
    const [a, l, s, p] = await Promise.all([
      http.get('/assets'), http.get('/locations'), http.get('/staff').catch(() => ({ data: [] })), http.get('/programs').catch(() => ({ data: [] })),
    ])
    assets.value = a.data
    locations.value = l.data
    staffList.value = s.data
    programs.value = p.data
  } catch (e) {
    toast.error(errorMessage(e, t('asset_assignments.options_failed')))
  }
}

function openCreate() {
  Object.assign(form, { asset_id: '', assigned_to_type: 'staff', assigned_to_id: '', location_id: '', quantity: 1, assigned_date: new Date().toISOString().slice(0, 10), due_date: '' })
  showModal.value = true
}

async function handleSubmit() {
  try {
    await http.post('/asset-assignments', form)
    toast.success(t('asset_assignments.assigned_successfully'))
    showModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(errorMessage(e, t('asset_assignments.assign_failed')))
  }
}

async function cancelAssignment(id) {
  try {
    await http.post(`/asset-assignments/${id}/cancel`)
    toast.success(t('asset_assignments.cancelled'))
    await fetchAll()
  } catch (e) {
    toast.error(errorMessage(e, t('asset_assignments.cancel_failed')))
  }
}

async function submitReturn() {
  const fd = new FormData()
  fd.append('condition', returnCondition.value)
  fd.append('remark', returnRemark.value)
  if (returnImageFile.value) fd.append('image', returnImageFile.value)
  try {
    await http.post(`/asset-assignments/${returningId.value}/return`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.success(t('asset_assignments.returned_successfully'))
    returningId.value = null
    await fetchAll()
  } catch (e) {
    // Keep the modal open so the entered condition and remark are not lost.
    toast.error(errorMessage(e, t('asset_assignments.return_failed')))
  }
}

onMounted(() => {
  fetchAll()
  loadOptions()
})

// Pagination is the last step, applied to the finished list, so search
// and sort still consider every row rather than just the page on screen.
const { page, rowsPerPage, total, paged } = usePagination(sortedAssignments)
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 space-y-6">
      <div class="card p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('asset_assignments.title') }}</h1>
            <p class="text-muted text-sm mt-1">{{ t('asset_assignments.subtitle') }}</p>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <button v-if="canManage" @click="openCreate" class="btn-primary btn-sm">
              <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
              {{ t('asset_assignments.new') }}
            </button>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 mb-6">
          <div class="flex-1 min-w-[260px]">
            <SearchInput v-model="search" :placeholder="t('common.search')" />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th class="th-sort" @click="toggleSort('asset')">{{ t('common.asset') }}<TableSortIcon :active="sortKey === 'asset'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('recipient_name')">{{ t('asset_assignments.recipient') }}<TableSortIcon :active="sortKey === 'recipient_name'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('location')">{{ t('common.location') }}<TableSortIcon :active="sortKey === 'location'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('quantity')">{{ t('asset_assignments.qty') }}<TableSortIcon :active="sortKey === 'quantity'" :direction="sortDir" /></th>
                <th>{{ t('asset_assignments.photo') }}</th>
                <th class="th-sort" @click="toggleSort('status')">{{ t('common.status') }}<TableSortIcon :active="sortKey === 'status'" :direction="sortDir" /></th>
                <th>{{ t('common.status_actions') }}</th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="a in paged" :key="a.id">
                <td class="font-medium text-fg">{{ a.asset?.name || t('common.n_a') }}</td>
                <td>{{ a.recipient_name }}</td>
                <td>{{ a.location?.name || t('common.n_a') }}</td>
                <td>{{ a.quantity }}</td>
                <td>
                  <a v-if="a.image_url" :href="a.image_url" target="_blank"><img :src="a.image_url" class="w-9 h-9 rounded-lg object-cover border border-line" alt="" /></a>
                  <span v-else class="text-faint">—</span>
                </td>
                <td><StatusBadge :status="a.status" /></td>
                <!-- Status actions: the transitions that move this assignment
                     through its lifecycle, kept apart from the row-management
                     actions on the right. -->
                <td class="whitespace-nowrap">
                  <div class="flex items-center gap-1.5">
                    <template v-if="a.status !== 'returned' && canManage">
                      <button @click="returningId = a.id; returnCondition = 'good'; returnRemark = ''; returnImageFile = null" :title="t('common.return')" :aria-label="t('common.return')" class="btn-icon">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                      </button>
                      <button @click="cancelAssignment(a.id)" :title="t('common.cancel')" :aria-label="t('common.cancel')" class="btn-icon-danger">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                      </button>
                    </template>
                    <span v-else class="text-faint">—</span>
                  </div>
                </td>
                <td class="text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <button @click="viewing = a" :title="t('common.view')" :aria-label="t('common.view')" class="btn-icon">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                    </button>
                    <button v-if="canManage" @click="openEdit(a)" :title="t('common.edit')" :aria-label="t('common.edit')" class="btn-icon">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
                    </button>
                    <button
                      v-if="canManage"
                      @click="deletingId = a.id"
                      :disabled="!canDelete(a)"
                      :title="canDelete(a) ? t('common.delete') : t('asset_assignments.delete_returned_only')"
                      :aria-label="t('common.delete')"
                      class="btn-icon-danger"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!loading && !sortedAssignments.length">
                <td colspan="8" class="py-10 text-center text-faint">{{ t('asset_assignments.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <TablePagination v-model:page="page" v-model:rows-per-page="rowsPerPage" :count="total" />
      </div>
    </div>

    <Modal v-if="showModal" :title="t('asset_assignments.modal_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.asset_required') }}</label>
            <select v-model="form.asset_id" required class="input">
              <option value="">{{ t('common.select_asset') }}</option>
              <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }} ({{ a.asset_code }})</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.assign_to') }}</label>
              <select v-model="form.assigned_to_type" class="input">
                <option value="staff">{{ t('asset_assignments.staff') }}</option>
                <option value="program">{{ t('asset_assignments.program') }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.recipient_required') }}</label>
              <select v-model="form.assigned_to_id" required class="input">
                <option value="">{{ t('asset_assignments.select_recipient') }}</option>
                <option v-for="r in (form.assigned_to_type === 'staff' ? staffList : programs)" :key="r.id" :value="r.id">{{ r.full_name || r.name }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.location_required') }}</label>
              <select v-model="form.location_id" required class="input">
                <option value="">{{ t('common.select_location') }}</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.quantity_required') }}</label>
              <input v-model.number="form.quantity" type="number" min="1" required class="input" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.assigned_date') }}</label>
              <input v-model="form.assigned_date" type="date" required class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.due_date') }}</label>
              <input v-model="form.due_date" type="date" class="input" />
            </div>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ t('asset_assignments.assign_button') }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <Modal v-if="returningId" :title="t('asset_assignments.return_title')" @close="returningId = null">
      <form @submit.prevent="submitReturn">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.condition_required') }}</label>
            <select v-model="returnCondition" class="input">
              <option value="good">{{ t('asset_assignments.condition_good') }}</option>
              <option value="fair">{{ t('asset_assignments.condition_fair') }}</option>
              <option value="broken">{{ t('asset_assignments.condition_broken') }}</option>
              <option value="lost">{{ t('asset_assignments.condition_lost') }}</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.remark') }}</label>
            <textarea v-model="returnRemark" rows="2" class="input"></textarea>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.photo_reference') }}</label>
            <input type="file" accept="image/jpeg,image/png" @change="handleReturnFileChange" class="w-full text-sm" />
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">{{ t('asset_assignments.confirm_return') }}</button>
          <button type="button" class="btn-ghost" @click="returningId = null">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>
    <DetailModal
      v-if="viewing"
      :title="t('common.details')"
      :rows="viewRows"
      @close="viewing = null"
    />

    <!-- Edit covers exactly the three fields the update endpoint validates.
         Asset, recipient and quantity are fixed once assigned, so they are
         shown as context rather than as inputs the server would ignore. -->
    <Modal v-if="editingId" :title="t('asset_assignments.edit_title')" @close="editingId = null">
      <form @submit.prevent="submitEdit">
        <div class="p-6 space-y-4">
          <div class="rounded-xl border border-line bg-surface-2 px-3.5 py-3 text-sm">
            <p class="font-semibold text-fg">{{ editContext?.asset?.name }}</p>
            <p class="text-muted text-[13px] mt-0.5">
              {{ t('asset_assignments.recipient') }}: {{ editContext?.recipient_name }} · {{ t('asset_assignments.qty') }}: {{ editContext?.quantity }}
            </p>
          </div>
          <div>
            <label class="label">{{ t('asset_assignments.location_required') }}</label>
            <select v-model="editForm.location_id" required class="select">
              <option value="">{{ t('common.select_location') }}</option>
              <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="label">{{ t('asset_assignments.due_date') }}</label>
              <input v-model="editForm.due_date" type="date" class="input" />
            </div>
            <div>
              <label class="label">{{ t('common.status') }}</label>
              <select v-model="editForm.status" required class="select">
                <option value="assigned">{{ t('status.assigned') }}</option>
                <option value="active">{{ t('status.active') }}</option>
                <option value="returned">{{ t('status.returned') }}</option>
              </select>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">{{ t('common.save') }}</button>
          <button type="button" class="btn-ghost" @click="editingId = null">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
