<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import http from '../../api/http'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import RejectReasonDialog from '../../components/dialogs/RejectReasonDialog.vue'
import { useServerTable } from '../../composables/useServerTable'
import { useConfirm } from '../../composables/useConfirm'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'

const { t } = useI18n()
const toast = useToastStore()
const auth = useAuthStore()
const { confirm } = useConfirm()
const route = useRoute()
const router = useRouter()

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
  const ok = await confirm({
    title: t('common.confirm_approve_title'),
    message: t('common.confirm_approve_message'),
    confirmText: t('common.approve'),
  })
  if (!ok) return

  await http.post(`/asset-transfers/${id}/approve`)
  toast.success(t('asset_transfers.approved'))
  await fetchPage()
}

const rejectingId = ref(null)
const rejecting = ref(false)

function openReject(id) {
  rejectingId.value = id
}

async function confirmReject(reason) {
  rejecting.value = true
  try {
    await http.post(`/asset-transfers/${rejectingId.value}/reject`, { rejection_reason: reason })
    toast.success(t('asset_transfers.rejected'))
    rejectingId.value = null
    await fetchPage()
  } finally {
    rejecting.value = false
  }
}

onMounted(() => {
  fetchPage()
  loadOptions()
  if (route.query.create === '1') {
    openCreate()
    router.replace({ query: { ...route.query, create: undefined } })
  }
})
</script>

<template>
  <v-container fluid class="pa-0">
      <AppPageHeader
        :title="t('asset_transfers.title')"
        :subtitle="t('asset_transfers.subtitle')"
        :actions="[{ label: t('asset_transfers.new'), icon: 'mdi-plus', onClick: openCreate }]"
      />

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
          <div v-if="item.status === 'pending' && auth.isOperationsHrManager" class="d-flex align-center justify-end ga-1">
            <v-btn icon="mdi-check" size="small" variant="text" color="success" :title="t('common.approve')" @click="approve(item.id)" />
            <v-btn icon="mdi-close" size="small" variant="text" color="error" :title="t('common.reject')" @click="openReject(item.id)" />
          </div>
        </template>
      </AppDataTable>
  </v-container>

  <RejectReasonDialog
    :model-value="rejectingId !== null"
    :loading="rejecting"
    @update:model-value="(v) => !v && (rejectingId = null)"
    @confirm="confirmReject"
  />

  <Modal v-if="showModal" :title="t('asset_transfers.modal_title')" @close="showModal = false">
    <v-form @submit.prevent="handleSubmit">
      <v-card-text class="d-flex flex-column ga-1">
        <v-select
          v-model="form.asset_id"
          :label="t('asset_transfers.asset_required')"
          :items="assets.map((a) => ({ title: `${a.name} (${a.asset_code})`, value: a.id }))"
          required
        />
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select
              v-model="form.from_location_id"
              :label="t('asset_transfers.from_location_required')"
              :items="locations.map((l) => ({ title: l.name, value: l.id }))"
              required
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="form.to_location_id"
              :label="t('asset_transfers.to_location_required')"
              :items="locations.map((l) => ({ title: l.name, value: l.id }))"
              required
            />
          </v-col>
        </v-row>
        <v-text-field v-model="form.transfer_date" type="date" :label="t('asset_transfers.transfer_date_required')" required />
        <v-textarea v-model="form.reason" :label="t('asset_transfers.reason')" rows="2" />
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn type="submit" color="primary" variant="flat" prepend-icon="mdi-plus">{{ t('asset_transfers.submit_button') }}</v-btn>
        <v-btn variant="text" @click="showModal = false">{{ t('common.cancel') }}</v-btn>
      </v-card-actions>
    </v-form>
  </Modal>
</template>
