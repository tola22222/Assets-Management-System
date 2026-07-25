<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useServerTable } from '../../composables/useServerTable'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'

const { t } = useI18n()
const toast = useToastStore()
const auth = useAuthStore()

const {
  items: disposals, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
  filters, hasActiveFilters, applyFilters, clearFilters,
} = useServerTable('/asset-disposals', { filterKeys: ['status', 'recommended_action'] })

const assets = ref([])
const showModal = ref(false)
const imageFile = ref(null)
const form = reactive({ asset_id: '', recommended_action: 'disposal', reason: '' })

const headers = computed(() => [
  { title: t('common.asset'), key: 'asset', sortable: false },
  { title: t('asset_disposals.action_col'), key: 'recommended_action', sortable: true },
  { title: t('asset_disposals.reason_col'), key: 'reason', sortable: false },
  { title: t('asset_disposals.photo'), key: 'image_url', sortable: false },
  { title: t('asset_disposals.requested_by'), key: 'requester', sortable: false },
  { title: t('common.status'), key: 'status', sortable: true },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 90 },
])

async function loadAssets() {
  const { data } = await http.get('/assets/export')
  assets.value = data.data.filter((a) => a.status === 'active')
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
    await fetchPage()
  } catch (e) {
    toast.error(e.response?.data?.message || t('asset_disposals.submit_failed'))
  }
}

async function approve(id) {
  await http.post(`/asset-disposals/${id}/approve`)
  toast.success(t('asset_disposals.approved'))
  await fetchPage()
}

async function reject(id) {
  await http.post(`/asset-disposals/${id}/reject`)
  toast.success(t('asset_disposals.rejected'))
  await fetchPage()
}

onMounted(() => {
  fetchPage()
  loadAssets()
})
</script>

<template>
    <div class="p-8 max-w-6xl mx-auto space-y-6">
      <AppPageHeader
        :title="t('asset_disposals.title')"
        :subtitle="t('asset_disposals.subtitle')"
        :actions="[{ label: t('asset_disposals.new'), icon: 'mdi-plus', onClick: openCreate }]"
      />

      <AppDataTable
        :headers="headers"
        :items="disposals"
        :items-length="total"
        :loading="loading"
        :page="page"
        :items-per-page="perPage"
        :items-per-page-options="[10, 25, 50, 100]"
        :sort-by="sortByVuetify"
        :search="search"
        :empty-text="t('asset_disposals.empty')"
        @update:search="setSearch"
        @update:options="handleOptions"
      >
        <template #filters>
          <v-select
            v-model="filters.recommended_action"
            :label="t('asset_disposals.action_col')"
            :items="[
              { title: t('common.all'), value: '' },
              { title: t('asset_disposals.action_repair'), value: 'repair' },
              { title: t('asset_disposals.action_disposal'), value: 'disposal' },
              { title: t('asset_disposals.action_replacement'), value: 'replacement' },
            ]"
            density="compact"
            variant="outlined"
            hide-details
            style="max-width: 220px"
            @update:model-value="applyFilters"
          />
          <v-select
            v-model="filters.status"
            :label="t('common.status')"
            :items="[
              { title: t('common.all'), value: '' },
              { title: t('status.pending'), value: 'pending' },
              { title: t('status.approved'), value: 'approved' },
              { title: t('status.rejected'), value: 'rejected' },
            ]"
            density="compact"
            variant="outlined"
            hide-details
            style="max-width: 220px"
            @update:model-value="applyFilters"
          />
          <v-btn v-if="hasActiveFilters" variant="text" size="small" @click="clearFilters">{{ t('common.clear_filters') }}</v-btn>
        </template>

        <template #item.asset="{ item }">{{ item.asset?.name || t('common.n_a') }}</template>
        <template #item.recommended_action="{ item }"><span class="capitalize">{{ item.recommended_action }}</span></template>
        <template #item.reason="{ item }"><span class="max-w-xs truncate block" :title="item.reason">{{ item.reason }}</span></template>
        <template #item.image_url="{ item }">
          <a v-if="item.image_url" :href="item.image_url" target="_blank">
            <img :src="item.image_url" class="w-9 h-9 rounded-lg object-cover border border-line" alt="" />
          </a>
          <span v-else class="text-faint">—</span>
        </template>
        <template #item.requester="{ item }">{{ item.requester?.name || t('common.n_a') }}</template>
        <template #item.status="{ item }"><StatusBadge :status="item.status" /></template>

        <template #item.actions="{ item }">
          <div v-if="item.status === 'pending' && auth.canApproveDisposal" class="flex items-center justify-end gap-1.5">
            <button @click="approve(item.id)" :title="t('common.approve')" class="w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </button>
            <button @click="reject(item.id)" :title="t('common.reject')" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </button>
          </div>
        </template>
      </AppDataTable>
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
</template>
