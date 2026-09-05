<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import Modal from '../../components/ui/Modal.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import DetailModal from '../../components/ui/DetailModal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableSort } from '../../composables/useTableSort'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'
import TablePagination from '../../components/ui/TablePagination.vue'
import { usePagination } from '../../composables/usePagination'

const { t } = useI18n()
const { items: verifications, loading, fetchAll, destroy } = useApiCrud('/asset-verifications', { entityName: t('asset_verifications.entity') })
const toast = useToastStore()
const auth = useAuthStore()
// Only OPM/Finance can submit a verification directly (role:operations_hr_manager,finance_manager
// on the store route) — staff submit condition reports via the QR scan flow instead, and ED has no
// direct-submit access either. Don't offer a button that would 403.
const canCreate = computed(() => ['operations_hr_manager', 'finance_manager'].includes(auth.user?.role))

const { search, filtered: searched } = useTableSearch(verifications, [(v) => v.asset?.name, (v) => v.asset?.asset_code, (v) => v.location?.name])
const { sortKey, sortDir, toggleSort, sorted: sortedVerifications } = useTableSort(searched, {
  defaultKey: 'verified_at', defaultDir: 'desc',
  paths: { asset: 'asset.name', location: 'location.name', verified_by: 'verified_by.name' },
})

// View renders the row the table already holds — /asset-verifications has no
// show endpoint, and its index returns the asset, location and verifier.
const viewing = ref(null)
const deletingId = ref(null)

// destroy sits behind role:operations_hr_manager, so only OPM gets the button
// at all; there is no status guard on the server side for this one.
const canDelete = computed(() => auth.user?.role === 'operations_hr_manager')

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

function handleFileChange(e) {
  imageFile.value = e.target.files[0] || null
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
              <tr v-for="v in paged" :key="v.id">
                <td class="font-medium text-fg">{{ v.asset?.name || t('common.n_a') }}</td>
                <td>{{ v.location?.name || t('common.n_a') }}</td>
                <td class="capitalize">{{ v.condition }}</td>
                <td>{{ v.verified_by?.name || t('common.n_a') }}</td>
                <td>
                  <a v-if="v.image_url" :href="v.image_url" target="_blank"><img :src="v.image_url" class="w-9 h-9 rounded-lg object-cover border border-line" alt="" /></a>
                  <span v-else class="text-faint">—</span>
                </td>
                <td>
                  <span class="px-2.5 py-1 rounded-lg text-xs font-bold" :class="v.verified_at ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                    {{ v.verified_at ? t('asset_verifications.complete') : t('asset_verifications.pending') }}
                  </span>
                </td>
                <td class="text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <button @click="viewing = v" :title="t('common.view')" :aria-label="t('common.view')" class="btn-icon">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                    </button>
                    <button v-if="!v.verified_at" @click="complete(v.id)" :title="t('common.mark_complete')" :aria-label="t('common.mark_complete')" class="btn-icon">
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
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_verifications.asset_required') }}</label>
            <select v-model="form.asset_id" required class="input">
              <option value="">{{ t('common.select_asset') }}</option>
              <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }} ({{ a.asset_code }})</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_verifications.location_required') }}</label>
              <select v-model="form.location_id" required class="input">
                <option value="">{{ t('common.select_location') }}</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_verifications.quantity_verified_required') }}</label>
              <input v-model.number="form.quantity_verified" type="number" min="1" required class="input" />
            </div>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_verifications.condition_required') }}</label>
            <select v-model="form.condition" class="input">
              <option value="good">{{ t('asset_verifications.condition_good') }}</option>
              <option value="fair">{{ t('asset_verifications.condition_fair') }}</option>
              <option value="broken">{{ t('asset_verifications.condition_broken') }}</option>
              <option value="lost">{{ t('asset_verifications.condition_lost') }}</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_verifications.remark') }}</label>
            <textarea v-model="form.remark" rows="2" class="input"></textarea>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_verifications.photo_reference') }}</label>
            <input type="file" accept="image/jpeg,image/png" @change="handleFileChange" class="w-full text-sm" />
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ t('asset_verifications.submit_button') }}
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
