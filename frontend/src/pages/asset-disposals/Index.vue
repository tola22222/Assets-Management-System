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
  const ok = await confirm({
    title: t('common.confirm_approve_title'),
    message: t('common.confirm_approve_message'),
    confirmText: t('common.approve'),
  })
  if (!ok) return

  await http.post(`/asset-disposals/${id}/approve`)
  toast.success(t('asset_disposals.approved'))
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
    await http.post(`/asset-disposals/${rejectingId.value}/reject`, { review_notes: reason })
    toast.success(t('asset_disposals.rejected'))
    rejectingId.value = null
    await fetchPage()
  } finally {
    rejecting.value = false
  }
}

onMounted(() => {
  fetchPage()
  loadAssets()
  if (route.query.create === '1') {
    openCreate()
    router.replace({ query: { ...route.query, create: undefined } })
  }
})
</script>

<template>
  <v-container fluid class="pa-0">
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
        <template #item.recommended_action="{ item }"><span class="text-capitalize">{{ item.recommended_action }}</span></template>
        <template #item.reason="{ item }"><span class="text-truncate d-block" style="max-width: 240px" :title="item.reason">{{ item.reason }}</span></template>
        <template #item.image_url="{ item }">
          <a v-if="item.image_url" :href="item.image_url" target="_blank">
            <v-img :src="item.image_url" width="36" height="36" rounded="lg" cover />
          </a>
          <span v-else class="text-medium-emphasis">—</span>
        </template>
        <template #item.requester="{ item }">{{ item.requester?.name || t('common.n_a') }}</template>
        <template #item.status="{ item }"><StatusBadge :status="item.status" /></template>

        <template #item.actions="{ item }">
          <div v-if="item.status === 'pending' && auth.canApproveDisposal" class="d-flex align-center justify-end ga-1">
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

  <Modal v-if="showModal" :title="t('asset_disposals.modal_title')" @close="showModal = false">
    <v-form @submit.prevent="handleSubmit">
      <v-card-text class="d-flex flex-column ga-1">
        <v-select
          v-model="form.asset_id"
          :label="t('asset_disposals.asset_required')"
          :items="assets.map((a) => ({ title: `${a.name} (${a.asset_code})`, value: a.id }))"
          required
        />
        <v-select
          v-model="form.recommended_action"
          :label="t('asset_disposals.recommended_action_required')"
          :items="[
            { title: t('asset_disposals.action_repair'), value: 'repair' },
            { title: t('asset_disposals.action_disposal'), value: 'disposal' },
            { title: t('asset_disposals.action_replacement'), value: 'replacement' },
          ]"
        />
        <v-textarea v-model="form.reason" :label="t('asset_disposals.reason_required')" :placeholder="t('asset_disposals.reason_placeholder')" rows="3" required />
        <v-file-input
          v-model="imageFile"
          :label="t('asset_disposals.photo_reference')"
          accept="image/jpeg,image/png"
          variant="outlined"
          density="comfortable"
          prepend-icon=""
          prepend-inner-icon="mdi-camera-outline"
        />
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn type="submit" color="primary" variant="flat" prepend-icon="mdi-plus">{{ t('asset_disposals.submit_button') }}</v-btn>
        <v-btn variant="text" @click="showModal = false">{{ t('common.cancel') }}</v-btn>
      </v-card-actions>
    </v-form>
  </Modal>
</template>
