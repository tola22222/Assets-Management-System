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
const { items: disposals, loading, fetchAll, destroy } = useApiCrud('/asset-disposals', { entityName: t('asset_disposals.entity') })
const toast = useToastStore()
const auth = useAuthStore()

const { search, filtered: searched } = useTableSearch(disposals, [(d) => d.asset?.name, (d) => d.asset?.asset_code, (d) => d.requester?.name, 'reason'])
const { sortKey, sortDir, toggleSort, sorted: sortedDisposals } = useTableSort(searched, {
  defaultKey: 'created_at', defaultDir: 'desc',
  paths: { asset: 'asset.name', action: 'recommended_action', requester: 'requester.name' },
})

// View reads the row already in the table: /asset-disposals has no show
// endpoint, and its index already returns the asset, requester and image.
const viewing = ref(null)
const deletingId = ref(null)

// The server refuses to delete anything already reviewed (422), so the button
// is only live while the request is still pending.
const canDelete = (d) => d.status === 'pending'

const viewRows = computed(() => {
  const d = viewing.value
  if (!d) return []
  return [
    { label: t('common.asset'), value: d.asset?.name },
    { label: t('assets.code'), value: d.asset?.asset_code, type: 'code' },
    { label: t('asset_disposals.action_col'), value: d.recommended_action, type: 'capitalize' },
    { label: t('asset_disposals.reason_col'), value: d.reason, type: 'multiline' },
    { label: t('asset_disposals.requested_by'), value: d.requester?.name },
    { label: t('common.status'), value: d.status, type: 'status' },
    { label: t('common.date'), value: (d.created_at || '').slice(0, 10) },
    { label: t('asset_disposals.photo'), value: d.image_url, type: 'image' },
  ]
})

async function confirmDelete() {
  const id = deletingId.value
  deletingId.value = null
  try {
    await destroy(id)
  } catch {
    // useApiCrud surfaces the server's own message (e.g. "Cannot delete a
    // reviewed disposal request."); nothing to add here.
  }
}

const assets = ref([])
const showModal = ref(false)
const imageFile = ref(null)
const form = reactive({ asset_id: '', recommended_action: 'disposal', reason: '' })

// canApproveDisposal() on the backend is ED-only, not "OPM or ED" — the manual requires
// OPM to submit a disposal report to the ED for independent review, so OPM approving its
// own submission would defeat that check. Don't offer buttons that would 403.
const canApprove = () => auth.user?.role === 'executive_director'

async function loadAssets() {
  try {
    const { data } = await http.get('/assets')
    assets.value = data.filter((a) => a.status === 'active')
  } catch (e) {
    toast.error(errorMessage(e, t('asset_disposals.load_failed')))
  }
}

function openCreate() {
  Object.assign(form, { asset_id: '', recommended_action: 'disposal', reason: '' })
  imageFile.value = null
  showModal.value = true
}

function handleFileChange(e) {
  imageFile.value = e.target.files[0] || null
}

async function handleSubmit() {
  const fd = new FormData()
  Object.entries(form).forEach(([k, v]) => fd.append(k, v))
  if (imageFile.value) fd.append('image', imageFile.value)

  try {
    await http.post('/asset-disposals', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.success(t('asset_disposals.submitted'))
    showModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(errorMessage(e, t('asset_disposals.submit_failed')))
  }
}

async function approve(id) {
  try {
    await http.post(`/asset-disposals/${id}/approve`)
    toast.success(t('asset_disposals.approved'))
    await fetchAll()
  } catch (e) {
    // Only the Executive Director may approve; everyone else gets a 403 whose
    // message explains exactly that, so surface what the server said.
    toast.error(errorMessage(e, t('asset_disposals.approve_failed')))
  }
}

async function reject(id) {
  try {
    await http.post(`/asset-disposals/${id}/reject`)
    toast.success(t('asset_disposals.rejected'))
    await fetchAll()
  } catch (e) {
    toast.error(errorMessage(e, t('asset_disposals.reject_failed')))
  }
}

onMounted(() => {
  fetchAll()
  loadAssets()
})

// Pagination is the last step, applied to the finished list, so search
// and sort still consider every row rather than just the page on screen.
const { page, rowsPerPage, total, paged } = usePagination(sortedDisposals)
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 space-y-6">
      <div class="card p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('asset_disposals.title') }}</h1>
            <p class="text-muted text-sm mt-1">{{ t('asset_disposals.subtitle') }}</p>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <button @click="openCreate" class="btn-primary btn-sm">
              <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
              {{ t('asset_disposals.new') }}
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
                <th class="th-sort" @click="toggleSort('action')">{{ t('asset_disposals.action_col') }}<TableSortIcon :active="sortKey === 'action'" :direction="sortDir" /></th>
                <th>{{ t('asset_disposals.reason_col') }}</th>
                <th>{{ t('asset_disposals.photo') }}</th>
                <th class="th-sort" @click="toggleSort('requester')">{{ t('asset_disposals.requested_by') }}<TableSortIcon :active="sortKey === 'requester'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('status')">{{ t('common.status') }}<TableSortIcon :active="sortKey === 'status'" :direction="sortDir" /></th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in paged" :key="d.id">
                <td class="font-medium text-fg">{{ d.asset?.name || t('common.n_a') }}</td>
                <td class="capitalize">{{ d.recommended_action }}</td>
                <td class="max-w-xs truncate" :title="d.reason">{{ d.reason }}</td>
                <td>
                  <a v-if="d.image_url" :href="d.image_url" target="_blank"><img :src="d.image_url" class="w-9 h-9 rounded-lg object-cover border border-line" alt="" /></a>
                  <span v-else class="text-faint">—</span>
                </td>
                <td>{{ d.requester?.name || t('common.n_a') }}</td>
                <td><StatusBadge :status="d.status" /></td>
                <td class="text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <button @click="viewing = d" :title="t('common.view')" :aria-label="t('common.view')" class="btn-icon">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                    </button>
                    <template v-if="d.status === 'pending' && canApprove()">
                      <button @click="approve(d.id)" :title="t('common.approve')" :aria-label="t('common.approve')" class="btn-icon">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      </button>
                      <button @click="reject(d.id)" :title="t('common.reject')" :aria-label="t('common.reject')" class="btn-icon-danger">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      </button>
                    </template>
                    <button
                      @click="deletingId = d.id"
                      :disabled="!canDelete(d)"
                      :title="canDelete(d) ? t('common.delete') : t('asset_disposals.delete_pending_only')"
                      :aria-label="t('common.delete')"
                      class="btn-icon-danger"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!loading && !sortedDisposals.length">
                <td colspan="7" class="py-10 text-center text-faint">{{ t('asset_disposals.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <TablePagination v-model:page="page" v-model:rows-per-page="rowsPerPage" :count="total" />
      </div>
    </div>

    <Modal v-if="showModal" :title="t('asset_disposals.modal_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_disposals.asset_required') }}</label>
            <select v-model="form.asset_id" required class="input">
              <option value="">{{ t('common.select_asset') }}</option>
              <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }} ({{ a.asset_code }})</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_disposals.recommended_action_required') }}</label>
            <select v-model="form.recommended_action" class="input">
              <option value="repair">{{ t('asset_disposals.action_repair') }}</option>
              <option value="disposal">{{ t('asset_disposals.action_disposal') }}</option>
              <option value="replacement">{{ t('asset_disposals.action_replacement') }}</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_disposals.reason_required') }}</label>
            <textarea v-model="form.reason" rows="3" required :placeholder="t('asset_disposals.reason_placeholder')" class="input"></textarea>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_disposals.photo_reference') }}</label>
            <input type="file" accept="image/jpeg,image/png" @change="handleFileChange" class="w-full text-sm" />
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ t('asset_disposals.submit_button') }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
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
