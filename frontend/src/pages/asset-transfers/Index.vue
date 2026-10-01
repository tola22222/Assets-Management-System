<script setup>
import { ref, computed, onMounted, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import StockAvailability from '../../components/ui/StockAvailability.vue'
import Modal from '../../components/ui/Modal.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import DetailModal from '../../components/ui/DetailModal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import SearchSelect from '../../components/ui/SearchSelect.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import LocationFilter from '../../components/ui/LocationFilter.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableFilter } from '../../composables/useTableFilter'
import { useTableSort } from '../../composables/useTableSort'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'
import TablePagination from '../../components/ui/TablePagination.vue'
import { usePagination } from '../../composables/usePagination'
import { usePermissions } from '../../composables/usePermissions'

const { t } = useI18n()
const { items: transfers, loading, fetchAll, destroy } = useApiCrud('/asset-transfers', { entityName: t('asset_transfers.entity') })
const toast = useToastStore()
const auth = useAuthStore()
// Staff get a simpler Actions column: Accept / Reject while a transfer waits
// on them, otherwise just View (see the template).
const isStaff = computed(() => auth.user?.role === 'staff')
// New Transfer: HR, the Accountant and the ED. The default Staff role only
// answers transfers (the server refuses it too); a custom role granting
// Transfers → Create brings the button back. Staff wait for their permissions
// to load rather than seeing the button flash up first.
const { can, allows, loaded: permissionsLoaded } = usePermissions()
const canCreate = computed(() => can('asset-transfers', 'create') && (!isStaff.value || permissionsLoaded.value))
// Mirrors the role: guard on /approve and /reject.
// A custom role granting Transfers → Update gets it too (usePermissions().allows).
const canApprove = computed(() => allows(['operations_hr_manager', 'finance_manager', 'executive_director'], 'asset-transfers', 'update'))
// Naming a staff member / program on a transfer is an assignment, so it takes
// the Assignment form's roles — AssetTransferController enforces the same.
const canAssign = computed(() => allows(['operations_hr_manager', 'finance_manager'], 'asset-assignments', 'create'))

const { search, filtered: searched } = useTableSearch(transfers, [(r) => r.asset?.name, (r) => r.asset?.asset_code, (r) => r.requester?.name, 'recipient_name'])
// Location filter (the drop-down beside search), applied after search and
// before sort. A transfer touches two sites, so picking one shows everything
// leaving it and everything headed to it.
const { filters, filtered: filteredTransfers } = useTableFilter(searched, {
  location: (r, v) => String(r.from_location_id) === v || String(r.to_location_id) === v,
})
const { sortKey, sortDir, toggleSort, sorted: sortedTransfers } = useTableSort(filteredTransfers, {
  defaultKey: 'created_at', defaultDir: 'desc',
  paths: { asset: 'asset.name', from: 'from_location.name', to: 'to_location.name', requester: 'requester.name' },
})

// View renders the row already held by the table — /asset-transfers has no
// show endpoint, and its index returns the asset, both locations and requester.
const viewing = ref(null)
const deletingId = ref(null)

// Once the destination has been asked to accept it the request is theirs to
// answer, and the server refuses the delete (422) — rejecting is the
// audit-visible way to kill it.
// The server says per row whether this viewer may delete it (requester or
// OPM, and only while awaiting approval or rejected).
// Status as the outcome of what was done: Accept → ACCEPTED, Reject →
// REJECTED, and once HR/Finance send it back, RETURNED. The stored status
// stays `received`; this is display only.
// A return row (parent_transfer_id set) that went through is RETURNED too —
// it is the return itself, not a delivery someone accepted.
// A delivery waiting on staff to verify it shows PENDING; one they confirmed
// shows RECEIVED (a return leg, or a returned one, RETURNED).
const displayStatus = (r) => {
  const isReturn = !!(r.return_transfer || r.parent_transfer_id)
  if (r.status === 'received') return isReturn ? 'returned' : 'received'
  return r.status
}
const conditionLabel = (c) => (c ? t(`asset_transfers.condition_${c}`) : null)

const canDelete = (r) => r.can_delete ?? (r.status === 'pending_approval' || r.status === 'rejected')

// can_confirm / can_decline / can_return come from the API. Who answers for a
// site runs through School → Program → Responsible Staff, which the SPA can't
// work out on its own; the server re-checks every one of these on the action.
const returning = ref(null)
const returnForm = reactive({ reason: '', transfer_date: '', do_return: false, assigned_to_type: '', assigned_to_id: '' })
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
    { label: t('asset_assignments.recipient'), value: r.recipient_name },
    { label: t('common.quantity'), value: r.quantity },
    // The exact tags on this transfer — what the receiver checks before accepting.
    // Codes the receiver left unticked are marked as not accepted.
    { label: t('asset_transfers.asset_codes'), value: (r.units?.length ? r.units : [r.asset]).filter(Boolean)
      .map((u) => (u.pivot?.status === 'declined' ? `${u.asset_code} — ${t('asset_transfers.not_accepted')}` : u.asset_code)).join('\n'), type: 'multiline' },
    { label: t('asset_transfers.requester'), value: r.requester?.name },
    { label: t('common.date'), value: (r.transfer_date || '').slice(0, 10) },
    { label: t('asset_transfers.reason'), value: r.reason, type: 'multiline' },
    { label: t('asset_transfers.reject_reason'), value: r.rejection_reason, type: 'multiline' },
    // Who signed for it, and when — blank until the destination accepts.
    { label: t('asset_transfers.received_by'), value: r.receiver?.name },
    { label: t('asset_transfers.received_at'), value: (r.received_at || '').slice(0, 10) },
    // The staff verification: who answered, when, and the condition found.
    { label: t('asset_transfers.verified_by'), value: r.verifier?.name },
    { label: t('asset_transfers.verified_at'), value: r.verified_at ? r.verified_at.slice(0, 16).replace('T', ' ') : null },
    { label: t('asset_transfers.received_condition'), value: conditionLabel(r.received_condition) },
    { label: t('common.status'), value: displayStatus(r), type: 'status' },
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
const staffList = ref([])
const programs = ref([])
const showModal = ref(false)
// assigned_to_type '' = a plain site-to-site transfer, for nobody in particular.
const emptyForm = () => ({ asset_id: '', from_location_id: '', to_location_id: '', reason: '', transfer_date: new Date().toISOString().slice(0, 10), assigned_to_type: '', assigned_to_id: '', quantity: 1, asset_ids: [] })
const form = reactive(emptyForm())

// ---- Which exact units go -------------------------------------------------
// HR ticks the asset codes of the chosen model at the From site; the
// receiver reviews those codes before accepting. Quantity is the tick count.
// The server re-checks every rule below (AssetTransferController::assertUnitsCanMove).
const chosenAsset = computed(() => assets.value.find((a) => String(a.id) === String(form.asset_id)))
const travellingIds = computed(() => new Set(
  transfers.value
    .filter((r) => r.status === 'pending_approval' || r.status === 'pending')
    .flatMap((r) => (r.units?.length ? r.units.map((u) => u.id) : [r.asset_id])),
))
const stock = ref(null)
// Every location holds its own stock: the From site's numbers, and only its
// free units — the server's available_ids leaves out units assigned there.
const stockLocationId = computed(() => form.from_location_id || chosenAsset.value?.location_id || '')
const tickableUnits = computed(() => {
  const model = chosenAsset.value
  if (!model) return []
  const from = String(stockLocationId.value)
  const free = stock.value?.available_ids ? new Set(stock.value.available_ids) : null
  return assets.value.filter((a) =>
    a.name === model.name && a.category_id === model.category_id &&
    String(a.location_id) === from && a.status !== 'disposed' &&
    !['lost', 'broken'].includes(a.condition) && !travellingIds.value.has(a.id) &&
    (!free || free.has(a.id)))
})
// Picking an asset starts the ticks with that one unit.
watch(() => form.asset_id, (id) => { form.asset_ids = id ? [Number(id)] : [] })
// Changing From drops ticks that are no longer at that site.
watch(tickableUnits, (units) => {
  const ok = new Set(units.map((u) => u.id))
  form.asset_ids = form.asset_ids.filter((id) => ok.has(id))
})
watch(() => form.asset_ids.length, (n) => { form.quantity = Math.max(n, 1) })
function toggleAllUnits(on) {
  form.asset_ids = on ? tickableUnits.value.map((u) => u.id) : []
}
// Stock of the chosen asset's model (StockAvailability loads it). Submit is
// held back while the quantity is over what's available; the server refuses
// it regardless.
const overStock = computed(() => !form.asset_ids.length || (!!stock.value && Number(form.quantity) > stock.value.available))

// Picking a staff member or program sets "To" to their site — the transfer
// goes to where they are. The server refuses a mismatched pair.
// The schools a recipient covers: a program's linked schools, or a staff
// member's program's schools (their old single site before they had one).
function recipientSchools(type, r) {
  if (!r) return []
  const linked = type === 'program' ? r.locations : r.program?.locations
  return (linked?.length ? linked.map((l) => l.id) : [r.location_id]).filter(Boolean).map(String)
}

// Picking a recipient points "To" at one of their schools — kept if it is
// already one of them, otherwise their first. The server refuses a mismatch.
const locationOptions = computed(() => locations.value.map((l) => ({ value: l.id, label: l.name })))

function pickRecipient() {
  const list = form.assigned_to_type === 'staff' ? staffList.value : programs.value
  const schools = recipientSchools(form.assigned_to_type, list.find((r) => String(r.id) === String(form.assigned_to_id)))
  if (schools.length && !schools.includes(String(form.to_location_id))) form.to_location_id = Number(schools[0])
}

async function loadOptions() {
  try {
    const [a, l, s, p] = await Promise.all([
      // Every site's name, even for staff (who otherwise only see their own
      // site), so they can pick where to send a transfer.
      http.get('/assets'), http.get('/locations', { params: { scope: 'destinations' } }),
      http.get('/staff').catch(() => ({ data: [] })), http.get('/programs').catch(() => ({ data: [] })),
    ])
    assets.value = a.data
    locations.value = l.data
    staffList.value = s.data
    programs.value = p.data
  } catch (e) {
    // Without this the asset and location dropdowns render empty and the form
    // looks broken, with nothing saying the lookup failed.
    toast.error(errorMessage(e, t('asset_transfers.options_failed')))
  }
}

function openCreate() {
  Object.assign(form, emptyForm())
  showModal.value = true
}

async function handleSubmit() {
  // No recipient: send the plain transfer fields only.
  const { assigned_to_type, assigned_to_id, ...plain } = form
  try {
    await http.post('/asset-transfers', assigned_to_type ? { ...plain, assigned_to_type, assigned_to_id } : plain)
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

// Staff turning a delivery away must say why; OPM refusing a request may.
const rejectIsDecline = computed(() => !!rejecting.value && rejecting.value.status !== 'pending_approval')

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
// Accepting: the receiver reviews the asset codes and ticks the ones that
// actually arrived (all ticked to start). Unticked codes are declined and
// stay where they were; the server records each code's outcome.
const accepting = ref(null)
// condition: the staff member's verification — New / Good / Fair. Confirm
// stays disabled until one is picked; the server requires it too.
const RECEIVED_CONDITIONS = ['new', 'good', 'fair']
const acceptForm = reactive({ asset_ids: [], rejection_reason: '', condition: '' })
const acceptUnits = computed(() => {
  const r = accepting.value
  if (!r) return []
  return r.units?.length ? r.units : (r.asset ? [r.asset] : [])
})

function openAccept(row) {
  accepting.value = row
  Object.assign(acceptForm, { asset_ids: acceptUnits.value.map((u) => u.id), rejection_reason: '', condition: '' })
}

async function confirmReceipt() {
  const row = accepting.value
  const all = acceptForm.asset_ids.length === acceptUnits.value.length
  try {
    await http.post(`/asset-transfers/${row.id}/confirm`, all
      ? { condition: acceptForm.condition }
      : { asset_ids: acceptForm.asset_ids, rejection_reason: acceptForm.rejection_reason, condition: acceptForm.condition })
    toast.success(t('asset_transfers.received'))
    accepting.value = null
    await fetchAll()
  } catch (e) {
    toast.error(errorMessage(e, t('asset_transfers.confirm_failed')))
  }
}

// Edit (HR / Finance, on an accepted transfer): either change who holds the
// assets at the destination (Assignment), or send them back (Return).
function openReturn(row) {
  Object.assign(returnForm, {
    // A return already on its way can only be finished, so start on Return.
    reason: '', transfer_date: new Date().toISOString().slice(0, 10), do_return: !!row.can_complete_return,
    assigned_to_type: row.assigned_to_type || '', assigned_to_id: row.assigned_to_id || '',
  })
  returning.value = row
}


// The codes a return sends back to stock: the ones that were accepted
// (never the ones the receiver declined).
const returnCodes = computed(() => {
  const r = returning.value
  if (!r) return []
  const units = r.units?.length ? r.units.filter((u) => u.pivot?.status !== 'declined') : [r.asset].filter(Boolean)
  return units.map((u) => u.asset_code)
})

// Recipients must be at the transfer's destination (or have no site yet).
const editRecipients = computed(() => {
  const site = String(returning.value?.to_location_id)
  const type = returnForm.assigned_to_type
  const list = type === 'staff' ? staffList.value : programs.value
  return list.filter((r) => {
    const schools = recipientSchools(type, r)
    return !schools.length || schools.includes(site)
  })
})

async function submitReturn() {
  const row = returning.value
  try {
    if (returnForm.do_return) {
      await http.post(`/asset-transfers/${row.id}/return`, { reason: returnForm.reason, transfer_date: returnForm.transfer_date })
      toast.success(t('asset_transfers.return_submitted'))
    } else {
      await http.put(`/asset-transfers/${row.id}/assignment`, returnForm.assigned_to_type
        ? { assigned_to_type: returnForm.assigned_to_type, assigned_to_id: returnForm.assigned_to_id }
        : { assigned_to_type: null })
      toast.success(t('asset_transfers.assignment_updated'))
    }
    returning.value = null
    await fetchAll()
  } catch (e) {
    toast.error(errorMessage(e, t(returnForm.do_return ? 'asset_transfers.return_failed' : 'asset_transfers.assignment_update_failed')))
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
            <button v-if="canCreate" @click="openCreate" class="btn-primary btn-sm">
              <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
              {{ t('asset_transfers.new') }}
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
                <th class="th-sort" @click="toggleSort('from')">{{ t('asset_transfers.from') }}<TableSortIcon :active="sortKey === 'from'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('to')">{{ t('asset_transfers.to') }}<TableSortIcon :active="sortKey === 'to'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('requester')">{{ t('asset_transfers.requester') }}<TableSortIcon :active="sortKey === 'requester'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('status')">{{ t('common.status') }}<TableSortIcon :active="sortKey === 'status'" :direction="sortDir" /></th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t2 in paged" :key="t2.id">
                <td class="font-medium text-fg">
                  {{ t2.asset?.name || t('common.n_a') }}<span v-if="t2.quantity > 1" class="text-muted font-normal"> × {{ t2.quantity }}</span>
                </td>
                <td>{{ t2.from_location?.name || t('common.n_a') }}</td>
                <td>
                  {{ t2.to_location?.name || t('common.n_a') }}
                  <p v-if="t2.recipient_name" class="text-xs text-muted mt-0.5">{{ t2.recipient_name }}</p>
                </td>
                <td>{{ t2.requester?.name || t('common.n_a') }}</td>
                <td><StatusBadge :status="displayStatus(t2)" /></td>
                <!-- One Actions column: the workflow step this viewer may take
                     (if any) first, then View / Delete. -->
                <td class="text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <!-- OPM or the ED releasing a request that has not reached
                         the destination yet: Approve to release, Reject to refuse. -->
                    <template v-if="t2.status === 'pending_approval' && canApprove && t2.requester?.id !== auth.user?.id">
                      <button @click="approve(t2.id)" class="btn-success btn-sm">
                        {{ t('common.approve') }}
                      </button>
                      <button @click="openReject(t2)" class="btn-danger btn-sm">
                        {{ t('common.reject') }}
                      </button>
                    </template>
                    <!-- The receiving site answers, with worded buttons so the
                         decision can't be misread: Accept (green) / Reject (red). -->
                    <template v-else-if="t2.can_confirm || t2.can_decline">
                      <button v-if="t2.can_confirm" @click="openAccept(t2)" :title="t('asset_transfers.confirm_receipt')" class="btn-accept btn-sm gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                        {{ t('asset_transfers.accept') }}
                      </button>
                      <button v-if="t2.can_decline" @click="openReject(t2)" :title="t('asset_transfers.reject_delivery')" class="btn-reject btn-sm gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
                        {{ t('common.reject') }}
                      </button>
                    </template>
                    <!-- HR / Finance on an accepted transfer: Edit opens the
                         Assignment / Return dialog. -->
                    <button v-else-if="t2.can_return || t2.can_complete_return" @click="openReturn(t2)" :title="t('common.edit')" :aria-label="t('common.edit')" class="btn-icon-edit">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
                    </button>
                    <!-- Staff: once they have accepted or rejected (or on any
                         row not waiting on them), View is the only action. -->
                    <button v-if="!isStaff || !(t2.can_confirm || t2.can_decline)" @click="viewing = t2" :title="t('common.view')" :aria-label="t('common.view')" class="btn-icon-view">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                    </button>
                    <!-- Staff only see Delete where the server says they may
                         (a custom role granting Transfers → Delete). -->
                    <button
                      v-if="!isStaff || t2.can_delete"
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
                <td colspan="6" class="py-10 text-center text-faint">{{ t('asset_transfers.empty') }}</td>
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
            <label class="label">{{ rejectIsDecline ? t('asset_transfers.reject_reason_required') : t('asset_transfers.reject_reason') }}</label>
            <textarea v-model="rejectForm.rejection_reason" rows="2" class="textarea" :required="rejectIsDecline" :placeholder="t('asset_transfers.reject_reason_placeholder')"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" @click="rejecting = null">{{ t('common.cancel') }}</button>
          <button type="submit" class="btn-danger" :disabled="rejectIsDecline && !rejectForm.rejection_reason.trim()">{{ t('common.reject') }}</button>
        </div>
      </form>
    </Modal>

    <!-- Accept: review the asset codes and tick the ones that arrived. -->
    <!-- Laid out like the ui-example/dialog_form.html mockup: subtitle in
         the header, the route as its own card, then the codes. -->
    <Modal
      v-if="accepting"
      :title="t('asset_transfers.confirm_receipt')"
      :subtitle="(accepting.asset?.name || '') + (acceptUnits.length > 1 ? ` × ${acceptUnits.length}` : '') + (accepting.recipient_name ? ` · ${accepting.recipient_name}` : '')"
      @close="accepting = null"
    >
      <form class="modal-form" @submit.prevent="confirmReceipt">
        <div class="modal-body space-y-5">
          <!-- Where it is coming from and going to. -->
          <div class="flex items-center gap-3 rounded-xl border border-line bg-surface-2 px-4 py-3">
            <div class="min-w-0 flex-1">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-faint">{{ t('asset_transfers.from') }}</p>
              <p class="text-sm font-semibold text-fg truncate">{{ accepting.from_location?.name || t('common.n_a') }}</p>
            </div>
            <span class="text-muted flex-shrink-0">→</span>
            <div class="min-w-0 flex-1 text-right">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-faint">{{ t('asset_transfers.to') }}</p>
              <p class="text-sm font-semibold text-fg truncate">{{ accepting.to_location?.name || t('common.n_a') }}</p>
            </div>
          </div>

          <div class="form-group">
            <div class="flex items-end justify-between gap-3 mb-2">
              <div>
                <label class="label !mb-0">{{ t('asset_transfers.asset_codes') }}</label>
                <p class="text-xs text-faint mt-0.5">{{ t('asset_transfers.accept_hint') }}</p>
              </div>
              <span class="text-xs text-muted whitespace-nowrap">
                {{ t('asset_transfers.ticked_count', { n: acceptForm.asset_ids.length, total: acceptUnits.length }) }}
                <button type="button" class="text-brand-600 dark:text-brand-300 font-semibold hover:underline ml-2"
                  @click="acceptForm.asset_ids = acceptForm.asset_ids.length < acceptUnits.length ? acceptUnits.map((u) => u.id) : []">
                  {{ acceptForm.asset_ids.length < acceptUnits.length ? t('asset_transfers.tick_all') : t('asset_transfers.tick_none') }}
                </button>
              </span>
            </div>
            <div class="max-h-56 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-2">
              <label
                v-for="u in acceptUnits" :key="u.id"
                class="flex items-center gap-2.5 h-[42px] px-3 rounded-lg border cursor-pointer transition-colors duration-150"
                :class="acceptForm.asset_ids.includes(u.id) ? 'border-brand bg-brand/5 dark:border-brand-200' : 'border-line bg-surface hover:bg-surface-2'"
              >
                <input v-model="acceptForm.asset_ids" type="checkbox" :value="u.id" class="rounded border-line text-brand focus:ring-brand/30" />
                <span class="font-mono text-[13px] text-fg truncate">{{ u.asset_code }}</span>
              </label>
            </div>
          </div>

          <!-- Verify the condition the assets arrived in. -->
          <div class="form-group">
            <label class="label">{{ t('asset_transfers.condition_label') }} <span class="text-red-500">*</span></label>
            <select v-model="acceptForm.condition" required class="select">
              <option value="" disabled>{{ t('asset_transfers.condition_placeholder') }}</option>
              <option v-for="c in RECEIVED_CONDITIONS" :key="c" :value="c">{{ t(`asset_transfers.condition_${c}`) }}</option>
            </select>
            <p class="text-xs text-faint mt-1">{{ t('asset_transfers.condition_hint') }}</p>
          </div>

          <div v-if="acceptForm.asset_ids.length && acceptForm.asset_ids.length < acceptUnits.length" class="form-group">
            <label class="label">{{ t('asset_transfers.not_accepted_reason') }}</label>
            <textarea v-model="acceptForm.rejection_reason" rows="2" class="textarea" :placeholder="t('asset_transfers.not_accepted_placeholder')"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" @click="accepting = null">{{ t('common.cancel') }}</button>
          <button type="submit" class="btn-accept" :disabled="!acceptForm.asset_ids.length || !acceptForm.condition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
            {{ t('asset_transfers.confirm_transfer') }}
          </button>
        </div>
      </form>
    </Modal>

    <!-- Edit an accepted transfer (HR / Finance): change the assignment, or
         return the assets to where they came from. -->
    <Modal v-if="returning" :title="t('asset_transfers.edit_title')" @close="returning = null">
      <form class="modal-form" @submit.prevent="submitReturn">
        <div class="modal-body space-y-4">
          <div class="rounded-xl border border-line bg-surface-2 px-3.5 py-3 text-sm">
            <p class="font-semibold text-fg">{{ returning.asset?.name }}<span v-if="returning.quantity > 1" class="text-muted font-normal"> × {{ returning.quantity }}</span></p>
            <p class="text-muted text-[13px] mt-0.5">{{ returning.from_location?.name }} → {{ returning.to_location?.name }}</p>
          </div>

          <!-- Action: change the assignment, or return the assets to stock. -->
          <div class="form-group">
            <label class="label">{{ t('asset_transfers.edit_action') }}</label>
            <select v-model="returnForm.do_return" class="input">
              <option v-if="!returning.can_complete_return" :value="false">{{ t('asset_transfers.section_assignment') }}</option>
              <option :value="true">{{ t('asset_transfers.section_return') }}</option>
            </select>
          </div>

          <!-- Assignment -->
          <div v-if="!returnForm.do_return" class="space-y-2">
            <div class="grid grid-cols-2 gap-4">
              <div class="form-group">
                <label class="label">{{ t('asset_transfers.assign_to') }}</label>
                <select v-model="returnForm.assigned_to_type" @change="returnForm.assigned_to_id = ''" class="input">
                  <option value="">{{ t('asset_transfers.assign_none') }}</option>
                  <option value="staff">{{ t('asset_assignments.staff') }}</option>
                  <option value="program">{{ t('asset_assignments.program') }}</option>
                </select>
              </div>
              <div class="form-group">
                <label class="label">{{ t('asset_assignments.recipient') }}</label>
                <SearchSelect v-model="returnForm.assigned_to_id" input-class="input" :placeholder="t('asset_assignments.select_recipient')"
                  :required="!!returnForm.assigned_to_type && !returnForm.do_return" :disabled="!returnForm.assigned_to_type"
                  :options="editRecipients.map((r) => ({ value: r.id, label: r.full_name || r.name }))" />
              </div>
            </div>
          </div>

          <!-- Return: the asset codes going back to stock. -->
          <div v-else class="space-y-3">
            <div class="form-group">
              <label class="label">{{ t('asset_transfers.return_codes', { n: returnCodes.length }) }}</label>
              <div class="max-h-44 overflow-y-auto rounded-xl border border-line bg-surface-2 p-2 grid grid-cols-2 gap-1">
                <span v-for="code in returnCodes" :key="code" class="px-2 py-1 font-mono text-[13px] text-fg">{{ code }}</span>
              </div>
            </div>
            <div class="form-group">
              <label class="label">{{ t('asset_transfers.transfer_date_required') }}</label>
              <input v-model="returnForm.transfer_date" type="date" required class="input" />
            </div>
            <div class="form-group">
              <label class="label">{{ t('asset_transfers.return_reason') }}</label>
              <textarea v-model="returnForm.reason" rows="2" class="textarea" :placeholder="t('asset_transfers.return_reason_placeholder')"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" @click="returning = null">{{ t('common.cancel') }}</button>
          <button type="submit" class="btn-primary">{{ returnForm.do_return ? t('asset_transfers.return_submit') : t('common.save') }}</button>
        </div>
      </form>
    </Modal>

    <Modal v-if="showModal" :title="t('asset_transfers.modal_title')" @close="showModal = false">
      <form class="modal-form" @submit.prevent="handleSubmit">
        <div class="modal-body space-y-4">
          <div class="form-group">
            <label class="label">{{ t('asset_transfers.asset_required') }}</label>
            <SearchSelect v-model="form.asset_id" required input-class="input" :placeholder="t('common.select_asset')"
              :options="assets.map((a) => ({ value: a.id, label: a.name, sub: a.asset_code }))" />
          </div>
          <StockAvailability :asset-id="form.asset_id" :location-id="stockLocationId" :quantity="form.quantity" @loaded="stock = $event" />
          <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
              <label class="label">{{ t('asset_transfers.from_location_required') }}</label>
              <SearchSelect v-model="form.from_location_id" required input-class="input" :placeholder="t('common.select_location')"
                :options="locationOptions" />
            </div>
            <div class="form-group">
              <label class="label">{{ t('asset_transfers.to_location_required') }}</label>
              <SearchSelect v-model="form.to_location_id" required input-class="input" :placeholder="t('common.select_location')"
                :options="locationOptions" />
            </div>
          </div>
          <!-- The exact tags that go: every usable unit of this model at the
               From site, ticked by HR. The receiver sees this same list. -->
          <div v-if="chosenAsset" class="form-group">
            <div class="flex items-end justify-between gap-3 mb-2">
              <label class="label !mb-0">{{ t('asset_transfers.asset_codes') }} <span class="text-red-500">*</span></label>
              <span class="text-xs text-muted whitespace-nowrap">
                {{ t('asset_transfers.ticked_count', { n: form.asset_ids.length, total: tickableUnits.length }) }}
                <button type="button" class="text-brand-600 dark:text-brand-300 font-semibold hover:underline ml-2" @click="toggleAllUnits(form.asset_ids.length < tickableUnits.length)">
                  {{ form.asset_ids.length < tickableUnits.length ? t('asset_transfers.tick_all') : t('asset_transfers.tick_none') }}
                </button>
              </span>
            </div>
            <!-- Same rows as the Accept dialog: a ticked code takes the brand
                 border and tint, so what is going is readable at a glance. -->
            <div v-if="tickableUnits.length" class="max-h-56 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-2">
              <label
                v-for="u in tickableUnits" :key="u.id"
                class="flex items-center gap-2.5 h-[42px] px-3 rounded-lg border cursor-pointer transition-colors duration-150"
                :class="form.asset_ids.includes(u.id) ? 'border-brand bg-brand/5 dark:border-brand-200' : 'border-line bg-surface hover:bg-surface-2'"
              >
                <input v-model="form.asset_ids" type="checkbox" :value="u.id" class="rounded border-line text-brand focus:ring-brand/30" />
                <span class="font-mono text-[13px] text-fg truncate">{{ u.asset_code }}</span>
              </label>
            </div>
            <p v-else class="text-xs text-danger">{{ t('asset_transfers.no_units_here') }}</p>
          </div>
          <!-- Optional, same picker as the Assignment form: who at the
               destination it is for. Assigned when the site accepts it. -->
          <div v-if="canAssign" class="grid grid-cols-2 gap-4">
            <div class="form-group">
              <label class="label">{{ t('asset_transfers.assign_to') }}</label>
              <select v-model="form.assigned_to_type" @change="form.assigned_to_id = ''" class="input">
                <option value="">{{ t('asset_transfers.assign_none') }}</option>
                <option value="staff">{{ t('asset_assignments.staff') }}</option>
                <option value="program">{{ t('asset_assignments.program') }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="label">{{ t('asset_assignments.recipient') }}</label>
              <SearchSelect v-model="form.assigned_to_id" input-class="input" :placeholder="t('asset_assignments.select_recipient')"
                :required="!!form.assigned_to_type" :disabled="!form.assigned_to_type" @change="pickRecipient"
                :options="(form.assigned_to_type === 'staff' ? staffList : programs).map((r) => ({ value: r.id, label: r.full_name || r.name }))" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
              <label class="label">{{ t('asset_transfers.quantity_required') }}</label>
              <!-- Set by the ticked asset codes above. -->
              <input :value="form.asset_ids.length" type="number" readonly class="input bg-surface-2" />
            </div>
            <div class="form-group">
              <label class="label">{{ t('asset_transfers.transfer_date_required') }}</label>
              <input v-model="form.transfer_date" type="date" required class="input" />
            </div>
          </div>
          <div class="form-group">
            <label class="label">{{ t('asset_transfers.reason') }}</label>
            <textarea v-model="form.reason" rows="2" class="textarea"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
          <button type="submit" class="btn-primary" :disabled="overStock">
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
