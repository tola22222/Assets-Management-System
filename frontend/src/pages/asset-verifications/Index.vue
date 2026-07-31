<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import Modal from '../../components/ui/Modal.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableSort } from '../../composables/useTableSort'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'

const { t } = useI18n()
const { items: verifications, loading, fetchAll } = useApiCrud('/asset-verifications', { entityName: t('asset_verifications.entity') })
const toast = useToastStore()
const auth = useAuthStore()
// Staff submit condition reports only via the QR scan flow — the backend
// now rejects a direct POST here, so don't offer a button that would 403.
const canCreate = computed(() => auth.user?.role !== 'staff')

const { search, filtered: searched } = useTableSearch(verifications, [(v) => v.asset?.name, (v) => v.asset?.asset_code, (v) => v.location?.name])
const { sortKey, sortDir, toggleSort, sorted: sortedVerifications } = useTableSort(searched, {
  defaultKey: 'verified_at', defaultDir: 'desc',
  paths: { asset: 'asset.name', location: 'location.name', verified_by: 'verified_by.name' },
})

const assets = ref([])
const locations = ref([])
const showModal = ref(false)
const imageFile = ref(null)
const form = reactive({ asset_id: '', location_id: '', quantity_verified: 1, condition: 'good', remark: '' })

async function loadOptions() {
  const [a, l] = await Promise.all([http.get('/assets'), http.get('/locations')])
  assets.value = a.data
  locations.value = l.data
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
    toast.error(e.response?.data?.message || t('asset_verifications.record_failed'))
  }
}

async function complete(id) {
  await http.post(`/asset-verifications/${id}/complete`)
  toast.success(t('asset_verifications.marked_complete'))
  await fetchAll()
}

onMounted(() => {
  fetchAll()
  loadOptions()
})
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
          <button v-if="canCreate" @click="openCreate" class="btn-primary btn-sm flex-shrink-0">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ t('asset_verifications.new') }}
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
                <th class="th-sort" @click="toggleSort('location')">{{ t('common.location') }}<TableSortIcon :active="sortKey === 'location'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('condition')">{{ t('asset_returns.condition') }}<TableSortIcon :active="sortKey === 'condition'" :direction="sortDir" /></th>
                <th class="th-sort" @click="toggleSort('verified_by')">{{ t('asset_verifications.verified_by') }}<TableSortIcon :active="sortKey === 'verified_by'" :direction="sortDir" /></th>
                <th>{{ t('asset_verifications.photo') }}</th>
                <th>{{ t('common.status') }}</th>
                <th class="text-right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="v in sortedVerifications" :key="v.id">
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
                <td class="text-right">
                  <button v-if="!v.verified_at" @click="complete(v.id)" :title="t('common.mark_complete')" class="w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  </button>
                </td>
              </tr>
              <tr v-if="!loading && !sortedVerifications.length">
                <td colspan="7" class="py-10 text-center text-faint">{{ t('asset_verifications.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
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
  </AppLayout>
</template>
