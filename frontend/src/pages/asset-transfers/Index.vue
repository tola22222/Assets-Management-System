<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import PageHeader from '../../components/ui/PageHeader.vue'
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
  items: transfers, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
  filters, hasActiveFilters, applyFilters, clearFilters,
} = useServerTable('/asset-transfers', { filterKeys: ['status'] })

const assets = ref([])
const locations = ref([])
const showModal = ref(false)
const form = reactive({ asset_id: '', from_location_id: '', to_location_id: '', reason: '', transfer_date: '' })

const headers = computed(() => [
  { title: t('common.asset'), key: 'asset', sortable: false },
  { title: t('asset_transfers.from'), key: 'from', sortable: false },
  { title: t('asset_transfers.to'), key: 'to', sortable: false },
  { title: t('asset_transfers.requester'), key: 'requester', sortable: false },
  { title: t('common.status'), key: 'status', sortable: true },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 90 },
])

async function loadOptions() {
  const [a, l] = await Promise.all([http.get('/assets/export'), http.get('/locations', { params: { per_page: 100 } })])
  assets.value = a.data.data
  locations.value = l.data.data ?? l.data
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
    await fetchPage()
  } catch (e) {
    toast.error(e.response?.data?.message || t('asset_transfers.submit_failed'))
  }
}

async function approve(id) {
  await http.post(`/asset-transfers/${id}/approve`)
  toast.success(t('asset_transfers.approved'))
  await fetchPage()
}

async function reject(id) {
  await http.post(`/asset-transfers/${id}/reject`)
  toast.success(t('asset_transfers.rejected'))
  await fetchPage()
}

onMounted(() => {
  fetchPage()
  loadOptions()
})
</script>

<template>
    <div class="p-8 max-w-6xl mx-auto space-y-6">
      <PageHeader :title="t('asset_transfers.title')" :subtitle="t('asset_transfers.subtitle')" :buttonText="t('asset_transfers.new')" @action="openCreate" />

      <AppDataTable
        :headers="headers"
        :items="transfers"
        :items-length="total"
        :loading="loading"
        :page="page"
        :items-per-page="perPage"
        :items-per-page-options="[10, 25, 50, 100]"
        :sort-by="sortByVuetify"
        :search="search"
        :empty-text="t('asset_transfers.empty')"
        @update:search="setSearch"
        @update:options="handleOptions"
      >
        <template #filters>
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
        <template #item.from="{ item }">{{ item.from_location?.name || t('common.n_a') }}</template>
        <template #item.to="{ item }">{{ item.to_location?.name || t('common.n_a') }}</template>
        <template #item.requester="{ item }">{{ item.requester?.name || t('common.n_a') }}</template>
        <template #item.status="{ item }"><StatusBadge :status="item.status" /></template>

        <template #item.actions="{ item }">
          <div v-if="item.status === 'pending' && auth.isOperationsHrManager" class="flex items-center justify-end gap-1.5">
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

    <Modal v-if="showModal" :title="t('asset_transfers.modal_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_transfers.asset_required') }}</label>
            <select v-model="form.asset_id" required class="input">
              <option value="">{{ t('common.select_asset') }}</option>
              <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }} ({{ a.asset_code }})</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_transfers.from_location_required') }}</label>
              <select v-model="form.from_location_id" required class="input">
                <option value="">{{ t('common.select_location') }}</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_transfers.to_location_required') }}</label>
              <select v-model="form.to_location_id" required class="input">
                <option value="">{{ t('common.select_location') }}</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_transfers.transfer_date_required') }}</label>
            <input v-model="form.transfer_date" type="date" required class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_transfers.reason') }}</label>
            <textarea v-model="form.reason" rows="2" class="input"></textarea>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ t('asset_transfers.submit_button') }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>
</template>
