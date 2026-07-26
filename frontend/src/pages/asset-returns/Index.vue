<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
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

const {
  items: returnsList, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
  filters, hasActiveFilters, applyFilters, clearFilters,
} = useServerTable('/asset-returns', { filterKeys: ['status', 'condition'] })

const assignments = ref([])
const showModal = ref(false)
const form = reactive({ assignment_id: '', asset_id: '', condition: 'good', damage_notes: '', return_date: '' })

const headers = computed(() => [
  { title: t('common.asset'), key: 'asset', sortable: false },
  { title: t('asset_returns.condition'), key: 'condition', sortable: true },
  { title: t('asset_returns.returned_by'), key: 'returned_by', sortable: false },
  { title: t('common.status'), key: 'status', sortable: true },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 90 },
])

async function loadOptions() {
  const { data } = await http.get('/asset-assignments', { params: { per_page: 100 } })
  assignments.value = (data.data ?? data).filter((a) => a.status !== 'returned')
}

function openCreate() {
  Object.assign(form, { assignment_id: '', asset_id: '', condition: 'good', damage_notes: '', return_date: new Date().toISOString().slice(0, 10) })
  showModal.value = true
}

function onAssignmentChange() {
  const assignment = assignments.value.find((a) => a.id == form.assignment_id)
  form.asset_id = assignment?.asset_id || ''
}

async function handleSubmit() {
  try {
    await http.post('/asset-returns', form)
    toast.success(t('asset_returns.submitted'))
    showModal.value = false
    await fetchPage()
  } catch (e) {
    toast.error(e.response?.data?.message || t('asset_returns.submit_failed'))
  }
}

async function approve(id) {
  const ok = await confirm({
    title: t('common.confirm_approve_title'),
    message: t('common.confirm_approve_message'),
    confirmText: t('common.approve'),
  })
  if (!ok) return

  await http.post(`/asset-returns/${id}/approve`)
  toast.success(t('asset_returns.approved'))
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
    await http.post(`/asset-returns/${rejectingId.value}/reject`, { admin_notes: reason })
    toast.success(t('asset_returns.rejected'))
    rejectingId.value = null
    await fetchPage()
  } finally {
    rejecting.value = false
  }
}

onMounted(() => {
  fetchPage()
  loadOptions()
})
</script>

<template>
  <v-container fluid class="pa-0">
      <AppPageHeader
        :title="t('asset_returns.title')"
        :subtitle="t('asset_returns.subtitle')"
        :actions="[{ label: t('asset_returns.new'), icon: 'mdi-plus', onClick: openCreate }]"
      />

      <AppDataTable
        :headers="headers"
        :items="returnsList"
        :items-length="total"
        :loading="loading"
        :page="page"
        :items-per-page="perPage"
        :items-per-page-options="[10, 25, 50, 100]"
        :sort-by="sortByVuetify"
        :search="search"
        :empty-text="t('asset_returns.empty')"
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
          <v-select
            v-model="filters.condition"
            :label="t('asset_returns.condition')"
            :items="[
              { title: t('common.all'), value: '' },
              { title: t('asset_returns.condition_good'), value: 'good' },
              { title: t('asset_returns.condition_fair'), value: 'fair' },
              { title: t('asset_returns.condition_broken'), value: 'broken' },
              { title: t('asset_returns.condition_lost'), value: 'lost' },
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
        <template #item.condition="{ item }"><span class="text-capitalize">{{ item.condition }}</span></template>
        <template #item.returned_by="{ item }">{{ item.returned_by?.name || t('common.n_a') }}</template>
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

  <Modal v-if="showModal" :title="t('asset_returns.modal_title')" @close="showModal = false">
    <v-form @submit.prevent="handleSubmit">
      <v-card-text class="d-flex flex-column ga-1">
        <v-select
          v-model="form.assignment_id"
          :label="t('asset_returns.assignment_required')"
          :items="assignments.map((a) => ({ title: `${a.asset?.name} — ${a.recipient_name}`, value: a.id }))"
          required
          @update:model-value="onAssignmentChange"
        />
        <v-select
          v-model="form.condition"
          :label="t('asset_returns.condition_required')"
          :items="[
            { title: t('asset_returns.condition_good'), value: 'good' },
            { title: t('asset_returns.condition_fair'), value: 'fair' },
            { title: t('asset_returns.condition_broken'), value: 'broken' },
            { title: t('asset_returns.condition_lost'), value: 'lost' },
          ]"
        />
        <v-text-field v-model="form.return_date" type="date" :label="t('asset_returns.return_date_required')" required />
        <v-textarea v-model="form.damage_notes" :label="t('asset_returns.damage_notes')" rows="2" />
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn type="submit" color="primary" variant="flat" prepend-icon="mdi-plus">{{ t('asset_returns.submit_button') }}</v-btn>
        <v-btn variant="text" @click="showModal = false">{{ t('common.cancel') }}</v-btn>
      </v-card-actions>
    </v-form>
  </Modal>
</template>
