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
const { items: transfers, loading, fetchAll, destroy } = useApiCrud('/asset-transfers', { entityName: t('asset_transfers.entity') })
const toast = useToastStore()
const auth = useAuthStore()

const { search, filtered: searched } = useTableSearch(transfers, [(r) => r.asset?.name, (r) => r.asset?.asset_code, (r) => r.requester?.name])
const { sortKey, sortDir, toggleSort, sorted: sortedTransfers } = useTableSort(searched, {
  defaultKey: 'transfer_date', defaultDir: 'desc',
  paths: { asset: 'asset.name', from: 'from_location.name', to: 'to_location.name', requester: 'requester.name' },
})

// View renders the row already held by the table — /asset-transfers has no
// show endpoint, and its index returns the asset, both locations and requester.
const viewing = ref(null)
const deletingId = ref(null)

// Once the destination has been asked to accept it the request is theirs to
// answer, and the server refuses the delete (422) — rejecting is the
// audit-visible way to kill it.
const canDelete = (r) => r.status === 'pending_approval' || r.status === 'rejected'

// can_confirm / can_decline / can_return come from the API. Who answers for a
// site runs through School → Program → Responsible Staff, which the SPA can't
// work out on its own; the server re-checks every one of these on the action.
const returning = ref(null)
const returnForm = reactive({ reason: '', transfer_date: '' })
const rejecting = ref(null)
const rejectForm = reactive({ rejection_reason: '' })

const viewRows = computed(() => {
  const r = viewing.value
  if (!r) return []
  return [
    { label: t('common.asset'), value: r.asset?.name },
    { label: t('assets.code'), value: r.asset?.asset_code, type: 'code' },
    { label: t('asset_transfers.from'), value: r.from_location?.name },
    { label: t('asset_transfers.to'), value: r.to_location?.name },
    { label: t('asset_transfers.requester'), value: r.requester?.name },
    { label: t('common.date'), value: (r.transfer_date || '').slice(0, 10) },
    { label: t('asset_transfers.reason'), value: r.reason, type: 'multiline' },
    { label: t('asset_transfers.reject_reason'), value: r.rejection_reason, type: 'multiline' },
    // Who signed for it, and when — blank until the destination accepts.
    { label: t('asset_transfers.received_by'), value: r.receiver?.name },
    { label: t('asset_transfers.received_at'), value: (r.received_at || '').slice(0, 10) },
    { label: t('common.status'), value: r.status, type: 'status' },
  ]
})

async function confirmDelete() {
  const id = deletingId.value
  deletingId.value = null
  try {
    await destroy(id)
  } catch {
    // useApiCrud already surfaced the server's own refusal message.
  }
}

const assets = ref([])
const locations = ref([])
const showModal = ref(false)
const form = reactive({ asset_id: '', from_location_id: '', to_location_id: '', reason: '', transfer_date: '' })

async function loadOptions() {
  try {
    const [a, l] = await Promise.all([http.get('/assets'), http.get('/locations')])
    assets.value = a.data
    locations.value = l.data
  } catch (e) {
    // Without this the asset and location dropdowns render empty and the form
    // looks broken, with nothing saying the lookup failed.
    toast.error(errorMessage(e, t('asset_transfers.options_failed')))
  }
}

function openCreate() {
  Object.assign(form, { asset_id: '', from_location_id: '', to_location_id: '', reason: '', transfer_date: new Date().toISOString().slice(0, 10) })
  showModal.value = true
}

async function handleSubmit() {
  try {
    await http.post('/asset-transfers', form)
    toast.success(t('asset_transfers.submitted'))
    showModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(errorMessage(e, t('asset_transfers.submit_failed')))
  }
}

async function approve(id) {
  try {
    await http.post(`/asset-transfers/${id}/approve`)
    toast.success(t('asset_transfers.approved'))
    await fetchAll()
  } catch (e) {
    // Approving your own request is refused with a 403, which used to produce
    // no message at all — the row simply stayed pending with no explanation.
    toast.error(errorMessage(e, t('asset_transfers.approve_failed')))
  }
}

// Two different rejections share one dialog: OPM refusing to release a request
// (/reject), and the destination refusing to take the asset (/decline). The
// row's own state says which endpoint applies.
function openReject(row) {
  rejectForm.rejection_reason = ''
  rejecting.value = row
}

async function submitReject() {
  const row = rejecting.value
  const endpoint = row.status === 'pending_approval' ? 'reject' : 'decline'
  try {
    await http.post(`/asset-transfers/${row.id}/${endpoint}`, rejectForm)
    toast.success(t('asset_transfers.rejected'))
    rejecting.value = null
    await fetchAll()
  } catch (e) {
    toast.error(errorMessage(e, t('asset_transfers.reject_failed')))
  }
}

// Accepting is what actually moves the asset in the register, so the row only
// leaves "pending" once the destination's responsible staff acts.
async function confirmReceipt(id) {
  try {
    await http.post(`/asset-transfers/${id}/confirm`)
    toast.success(t('asset_transfers.received'))
    await fetchAll()
  } catch (e) {
    toast.error(errorMessage(e, t('asset_transfers.confirm_failed')))
  }
}

function openReturn(row) {
  Object.assign(returnForm, { reason: '', transfer_date: new Date().toISOString().slice(0, 10) })
  returning.value = row
}

async function submitReturn() {
  try {
    await http.post(`/asset-transfers/${returning.value.id}/return`, returnForm)
    toast.success(t('asset_transfers.return_submitted'))
    returning.value = null
    await fetchAll()
  } catch (e) {
    toast.error(errorMessage(e, t('asset_transfers.return_failed')))
  }
}

onMounted(() => {
  fetchAll()
  loadOptions()
})

// Pagination is the last step, applied to the finished list, so search
// and sort still consider every row rather than just the page on screen.
const { page, rowsPerPage, total, paged } = usePagination(sortedTransfers)
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 space-y-6">
      <div class="card p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('asset_transfers.title') }}</h1>
            <p class="text-muted text-sm mt-1">{{ t('asset_transfers.subtitle') }}</p>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <button @click="openCreate" class="btn-primary btn-sm">
              <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
              {{ t('asset_transfers.new') }}
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
                <th class="th-sort" @click="toggleSort('from')">{{ t('asset_transfers.from') }}<TableSortIcon :active="sortKey === 'from'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('to')">{{ t('asset_transfers.to') }}<TableSortIcon :active="sortKey === 'to'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('requester')">{{ t('asset_transfers.requester') }}<TableSortIcon :active="sortKey === 'requester'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('status')">{{ t('common.status') }}<TableSortIcon :active="sortKey === 'status'" :direction="sortDir" /></th>
                <th>{{ t('common.status_actions') }}</th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t2 in paged" :key="t2.id">
                <td class="font-medium text-fg">{{ t2.asset?.name || t('common.n_a') }}</td>
                <td>{{ t2.from_location?.name || t('common.n_a') }}</td>
                <td>{{ t2.to_location?.name || t('common.n_a') }}</td>
                <td>{{ t2.requester?.name || t('common.n_a') }}</td>
                <td><StatusBadge :status="t2.status" /></td>
                <!-- Status actions: the approval transitions, kept apart from
                     the row-management actions on the right. -->
                <td class="whitespace-nowrap">
                  <div class="flex items-center gap-1.5">
                    <!-- OPM releasing a request that has not reached the
                         destination yet. -->
                    <!-- OPM releasing a request that has not reached the
                         destination yet: tick to release, cross to refuse. -->
                    <template v-if="t2.status === 'pending_approval' && auth.user?.role === 'operations_hr_manager' && t2.requester?.id !== auth.user?.id">
                      <button @click="approve(t2.id)" :title="t('common.approve')" :aria-label="t('common.approve')" class="btn-icon-success">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12.75 11.25 15 15 9.75" /><circle cx="12" cy="12" r="9" /></svg>
                      </button>
                      <button @click="openReject(t2)" :title="t('common.reject')" :aria-label="t('common.reject')" class="btn-icon-danger">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5" /><circle cx="12" cy="12" r="9" /></svg>
                      </button>
                    </template>
                    <!-- The receiving site answers. Accept is an arrow into a
                         tray and Return is the same arrow coming back out — a
                         matched pair, so the two directions read at a glance
                         and neither can be mistaken for the plain tick above. -->
                    <template v-else-if="t2.can_confirm || t2.can_decline">
                      <button v-if="t2.can_confirm" @click="confirmReceipt(t2.id)" :title="t('asset_transfers.confirm_receipt')" :aria-label="t('asset_transfers.confirm_receipt')" class="btn-icon-success">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v9m0 0 3.5-3.5M12 12 8.5 8.5" /><path d="M3.5 14.5h4l1.2 2.2h6.6l1.2-2.2h4" /><path d="M3.5 14.5 5.8 19a2 2 0 0 0 1.8 1.1h8.8a2 2 0 0 0 1.8-1.1l2.3-4.5" /></svg>
                      </button>
                      <!-- Plain x-circle rather than a crossed-out tray: at
                           18px the cross and the tray fought each other and
                           neither resolved. Refusing means the same thing here
                           as it does above, so it looks the same. -->
                      <button v-if="t2.can_decline" @click="openReject(t2)" :title="t('asset_transfers.reject_delivery')" :aria-label="t('asset_transfers.reject_delivery')" class="btn-icon-danger">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5" /><circle cx="12" cy="12" r="9" /></svg>
                      </button>
                    </template>
                    <!-- Finished with it: send it back where it came from. -->
                    <button v-else-if="t2.can_return" @click="openReturn(t2)" :title="t('asset_transfers.return_asset')" :aria-label="t('asset_transfers.return_asset')" class="btn-icon-info">
                      <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12V3m0 0L8.5 6.5M12 3l3.5 3.5" /><path d="M3.5 14.5h4l1.2 2.2h6.6l1.2-2.2h4" /><path d="M3.5 14.5 5.8 19a2 2 0 0 0 1.8 1.1h8.8a2 2 0 0 0 1.8-1.1l2.3-4.5" /></svg>
                    </button>
                    <span v-else class="text-faint">—</span>
                  </div>
                </td>
                <td class="text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <button @click="viewing = t2" :title="t('common.view')" :aria-label="t('common.view')" class="btn-icon-view">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                    </button>
                    <button
                      @click="deletingId = t2.id"
                      :disabled="!canDelete(t2)"
                      :title="canDelete(t2) ? t('common.delete') : t('asset_transfers.delete_dispatched_blocked')"
                      :aria-label="t('common.delete')"
                      class="btn-icon-danger"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!loading && !sortedTransfers.length">
                <td colspan="7" class="py-10 text-center text-faint">{{ t('asset_transfers.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <TablePagination v-model:page="page" v-model:rows-per-page="rowsPerPage" :count="total" />
      </div>
    </div>

    <Modal v-if="rejecting" :title="t('asset_transfers.reject_modal_title')" @close="rejecting = null">
      <form class="modal-form" @submit.prevent="submitReject">
        <div class="modal-body space-y-4">
          <p class="text-sm text-muted">
            {{ t('asset_transfers.reject_explainer', { asset: rejecting.asset?.name || t('common.n_a') }) }}
          </p>
          <div class="form-group">
            <label class="label">{{ t('asset_transfers.reject_reason') }}</label>
            <textarea v-model="rejectForm.rejection_reason" rows="2" class="textarea" :placeholder="t('asset_transfers.reject_reason_placeholder')"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" @click="rejecting = null">{{ t('common.cancel') }}</button>
          <button type="submit" class="btn-danger">{{ t('common.reject') }}</button>
        </div>
      </form>
    </Modal>

    <Modal v-if="returning" :title="t('asset_transfers.return_modal_title')" @close="returning = null">
      <form class="modal-form" @submit.prevent="submitReturn">
        <div class="modal-body space-y-4">
          <p class="text-sm text-muted">
            {{ t('asset_transfers.return_explainer', { asset: returning.asset?.name || t('common.n_a'), location: returning.from_location?.name || t('common.n_a') }) }}
          </p>
          <div class="form-group">
            <label class="label">{{ t('asset_transfers.transfer_date_required') }}</label>
            <input v-model="returnForm.transfer_date" type="date" required class="input" />
          </div>
          <div class="form-group">
            <label class="label">{{ t('asset_transfers.return_reason') }}</label>
            <textarea v-model="returnForm.reason" rows="2" class="textarea" :placeholder="t('asset_transfers.return_reason_placeholder')"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" @click="returning = null">{{ t('common.cancel') }}</button>
          <button type="submit" class="btn-primary">{{ t('asset_transfers.return_submit') }}</button>
        </div>
      </form>
    </Modal>

    <Modal v-if="showModal" :title="t('asset_transfers.modal_title')" @close="showModal = false">
      <form class="modal-form" @submit.prevent="handleSubmit">
        <div class="modal-body space-y-4">
          <div class="form-group">
            <label class="label">{{ t('asset_transfers.asset_required') }}</label>
            <select v-model="form.asset_id" required class="input">
              <option value="">{{ t('common.select_asset') }}</option>
              <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }} ({{ a.asset_code }})</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
              <label class="label">{{ t('asset_transfers.from_location_required') }}</label>
              <select v-model="form.from_location_id" required class="input">
                <option value="">{{ t('common.select_location') }}</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="label">{{ t('asset_transfers.to_location_required') }}</label>
              <select v-model="form.to_location_id" required class="input">
                <option value="">{{ t('common.select_location') }}</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="label">{{ t('asset_transfers.transfer_date_required') }}</label>
            <input v-model="form.transfer_date" type="date" required class="input" />
          </div>
          <div class="form-group">
            <label class="label">{{ t('asset_transfers.reason') }}</label>
            <textarea v-model="form.reason" rows="2" class="textarea"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ t('asset_transfers.submit_button') }}
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
