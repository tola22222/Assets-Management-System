<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import PepyDialog from '../../components/ui/PepyDialog.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useServerTable } from '../../composables/useServerTable'
import { useApiCrud } from '../../composables/useApiCrud'
import { useConfirm } from '../../composables/useConfirm'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'

const { t } = useI18n()
const toast = useToastStore()
const auth = useAuthStore()
const { confirm } = useConfirm()

const {
  items: records, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
  filters, hasActiveFilters, applyFilters, clearFilters,
} = useServerTable('/asset-maintenance', { filterKeys: ['status'] })
const { create, update, destroy } = useApiCrud('/asset-maintenance', { entityName: t('maintenance.entity'), refetch: fetchPage })

const assets = ref([])
const staffList = ref([])
const showModal = ref(false)
const editingId = ref(null)
const submitting = ref(false)

const emptyForm = () => ({ asset_id: '', issue: '', reported_date: new Date().toISOString().slice(0, 10), cost: '', staff_id: '' })
const form = reactive(emptyForm())

const headers = computed(() => [
  { title: t('common.asset'), key: 'asset', sortable: false },
  { title: t('maintenance.issue'), key: 'issue', sortable: false },
  { title: t('maintenance.reported_date'), key: 'reported_date', sortable: true },
  { title: t('common.status'), key: 'status', sortable: true },
  { title: t('maintenance.cost'), key: 'cost', sortable: true, align: 'end' },
  { title: t('maintenance.assigned_staff'), key: 'staff', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 120 },
])

async function loadOptions() {
  const [a, s] = await Promise.all([
    http.get('/assets/export'),
    http.get('/staff', { params: { per_page: 100 } }).catch(() => ({ data: [] })),
  ])
  assets.value = a.data.data
  staffList.value = s.data.data ?? s.data
}

function money(v) {
  return v ? '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'
}

function formatDate(v) {
  return v ? new Date(v).toLocaleDateString() : '—'
}

function openCreate() {
  editingId.value = null
  Object.assign(form, emptyForm())
  showModal.value = true
}

function openEdit(record) {
  editingId.value = record.id
  Object.assign(form, {
    asset_id: record.asset_id, issue: record.issue, reported_date: record.reported_date,
    cost: record.cost || '', staff_id: record.staff_id || '',
  })
  showModal.value = true
}

async function handleSubmit() {
  submitting.value = true
  try {
    if (editingId.value) {
      await update(editingId.value, form)
    } else {
      await create(form)
    }
    showModal.value = false
  } catch (e) {
    toast.error(e.response?.data?.message || t('maintenance.save_failed'))
  } finally {
    submitting.value = false
  }
}

async function handleDelete(item) {
  const ok = await confirm({
    title: t('confirm.delete_title'),
    message: t('confirm.delete_message'),
    color: 'error',
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return
  await destroy(item.id)
}

async function startMaintenance(item) {
  await http.post(`/asset-maintenance/${item.id}/start`)
  toast.success(t('maintenance.started'))
  await fetchPage()
}

async function completeMaintenance(item) {
  const ok = await confirm({
    title: t('maintenance.confirm_complete_title'),
    message: t('maintenance.confirm_complete_message'),
    confirmText: t('common.mark_complete'),
  })
  if (!ok) return
  await http.post(`/asset-maintenance/${item.id}/complete`)
  toast.success(t('maintenance.completed'))
  await fetchPage()
}

onMounted(() => {
  fetchPage()
  loadOptions()
})
</script>

<template>
  <v-container fluid class="pa-0">
    <AppPageHeader
      :title="t('maintenance.title')"
      :subtitle="t('maintenance.subtitle')"
      :actions="[{ label: t('maintenance.report_issue'), icon: 'mdi-plus', onClick: openCreate }]"
    />

    <AppDataTable
      :headers="headers"
      :items="records"
      :items-length="total"
      :loading="loading"
      :page="page"
      :items-per-page="perPage"
      :items-per-page-options="[10, 25, 50, 100]"
      :sort-by="sortByVuetify"
      :search="search"
      :empty-text="t('maintenance.empty')"
      :show-view="false"
      :show-edit="false"
      :show-delete="false"
      @update:search="setSearch"
      @update:options="handleOptions"
    >
      <template #filters>
        <v-select
          v-model="filters.status"
          :label="t('common.status')"
          :items="[
            { title: t('maintenance.status_pending'), value: 'pending' },
            { title: t('maintenance.status_in_progress'), value: 'in_progress' },
            { title: t('maintenance.status_completed'), value: 'completed' },
          ]"
          clearable
          density="compact"
          variant="outlined"
          hide-details
          style="max-width: 200px"
          @update:model-value="applyFilters"
        />
        <v-btn v-if="hasActiveFilters" variant="text" size="small" @click="clearFilters">{{ t('common.clear_filters') }}</v-btn>
      </template>

      <template #item.asset="{ item }">
        <p class="font-weight-medium">{{ item.asset?.name || t('common.n_a') }}</p>
        <p class="font-mono font-mono-tag text-caption text-medium-emphasis">{{ item.asset?.asset_code }}</p>
      </template>
      <template #item.issue="{ item }"><span class="text-truncate d-block" style="max-width: 260px" :title="item.issue">{{ item.issue }}</span></template>
      <template #item.reported_date="{ item }">{{ formatDate(item.reported_date) }}</template>
      <template #item.status="{ item }"><StatusBadge :status="item.status" /></template>
      <template #item.cost="{ item }">{{ money(item.cost) }}</template>
      <template #item.staff="{ item }">{{ item.staff?.full_name || t('common.unassigned') }}</template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center justify-end ga-1">
          <v-btn v-if="item.status === 'pending' && auth.isOperationsHrManager" icon="mdi-play" size="small" variant="text" color="primary" :title="t('maintenance.start')" @click="startMaintenance(item)" />
          <v-btn v-if="item.status === 'in_progress' && auth.isOperationsHrManager" icon="mdi-check" size="small" variant="text" color="success" :title="t('common.mark_complete')" @click="completeMaintenance(item)" />
          <v-menu>
            <template #activator="{ props: menuProps }">
              <v-btn icon="mdi-dots-vertical" size="small" variant="text" v-bind="menuProps" />
            </template>
            <v-list density="compact">
              <v-list-item :title="t('common.edit')" @click="openEdit(item)" />
              <v-list-item :title="t('common.delete')" class="text-error" @click="handleDelete(item)" />
            </v-list>
          </v-menu>
        </div>
      </template>
    </AppDataTable>

    <PepyDialog
      v-if="showModal"
      :title="editingId ? t('maintenance.edit_title') : t('maintenance.create_title')"
      icon="mdi-tools"
      :loading="submitting"
      :confirm-text="editingId ? t('common.save') : t('maintenance.report_issue')"
      @update:model-value="(v) => !v && (showModal = false)"
      @confirm="handleSubmit"
    >
      <v-row dense>
        <v-col cols="12">
          <v-select
            v-model="form.asset_id"
            :label="t('maintenance.asset_required')"
            :items="assets.map((a) => ({ title: `${a.name} (${a.asset_code})`, value: a.id }))"
            required
          />
        </v-col>
        <v-col cols="12">
          <v-textarea v-model="form.issue" :label="t('maintenance.issue')" rows="3" required />
        </v-col>
        <v-col cols="12" sm="6">
          <v-text-field v-model="form.reported_date" type="date" :label="t('maintenance.reported_date')" required />
        </v-col>
        <v-col cols="12" sm="6">
          <v-text-field v-model="form.cost" type="number" step="0.01" :label="t('maintenance.cost')" />
        </v-col>
        <v-col cols="12">
          <v-select
            v-model="form.staff_id"
            :label="t('maintenance.assigned_staff')"
            :items="staffList.map((s) => ({ title: s.full_name, value: s.id }))"
            clearable
          />
        </v-col>
      </v-row>
    </PepyDialog>
  </v-container>
</template>
