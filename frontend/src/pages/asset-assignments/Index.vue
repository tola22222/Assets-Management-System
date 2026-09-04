<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import Modal from '../../components/ui/Modal.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
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
const { items: assignments, loading, fetchAll } = useApiCrud('/asset-assignments', { entityName: t('asset_assignments.entity') })
const toast = useToastStore()
const auth = useAuthStore()
const canManage = computed(() => ['operations_hr_manager', 'finance_manager'].includes(auth.user?.role))

const { search, filtered: searched } = useTableSearch(assignments, [(a) => a.asset?.name, (a) => a.asset?.asset_code, 'recipient_name'])
const { sortKey, sortDir, toggleSort, sorted: sortedAssignments } = useTableSort(searched, {
  defaultKey: 'assigned_date', defaultDir: 'desc',
  paths: { asset: 'asset.name', location: 'location.name' },
})

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
          <button v-if="canManage" @click="openCreate" class="btn-primary btn-sm flex-shrink-0">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ t('asset_assignments.new') }}
          </button>
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
                <td class="text-right whitespace-nowrap">
                  <template v-if="a.status !== 'returned' && canManage">
                    <div class="flex items-center justify-end gap-1.5">
                      <button @click="returningId = a.id; returnCondition = 'good'; returnRemark = ''; returnImageFile = null" :title="t('common.return')" class="btn-icon">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                      </button>
                      <button @click="cancelAssignment(a.id)" :title="t('common.cancel')" class="btn-icon-danger">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                      </button>
                    </div>
                  </template>
                </td>
              </tr>
              <tr v-if="!loading && !sortedAssignments.length">
                <td colspan="7" class="py-10 text-center text-faint">{{ t('asset_assignments.empty') }}</td>
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
  </AppLayout>
</template>
