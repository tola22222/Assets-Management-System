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

const { t } = useI18n()
const {
  items: assignments, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
  filters, hasActiveFilters, applyFilters, clearFilters,
} = useServerTable('/asset-assignments', { filterKeys: ['status'] })
const toast = useToastStore()

const assets = ref([])
const locations = ref([])
const staffList = ref([])
const programs = ref([])
const showModal = ref(false)
const returningId = ref(null)
const returnCondition = ref('good')
const returnRemark = ref('')

const form = reactive({ asset_id: '', assigned_to_type: 'staff', assigned_to_id: '', location_id: '', quantity: 1, assigned_date: '', due_date: '' })

const headers = computed(() => [
  { title: t('common.asset'), key: 'asset', sortable: false },
  { title: t('asset_assignments.recipient'), key: 'recipient_name', sortable: false },
  { title: t('common.location'), key: 'location', sortable: false },
  { title: t('asset_assignments.qty'), key: 'quantity', sortable: true },
  { title: t('common.status'), key: 'status', sortable: true },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 90 },
])

async function loadOptions() {
  const perPage = { params: { per_page: 100 } }
  const [a, l, s, p] = await Promise.all([
    http.get('/assets/export'), http.get('/locations', perPage),
    http.get('/staff', perPage).catch(() => ({ data: [] })), http.get('/programs', perPage).catch(() => ({ data: [] })),
  ])
  assets.value = a.data.data
  locations.value = l.data.data ?? l.data
  staffList.value = s.data.data ?? s.data
  programs.value = p.data.data ?? p.data
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
    await fetchPage()
  } catch (e) {
    toast.error(e.response?.data?.message || t('asset_assignments.assign_failed'))
  }
}

async function cancelAssignment(id) {
  await http.post(`/asset-assignments/${id}/cancel`)
  toast.success(t('asset_assignments.cancelled'))
  await fetchPage()
}

async function submitReturn() {
  const fd = new FormData()
  fd.append('condition', returnCondition.value)
  fd.append('remark', returnRemark.value)
  await http.post(`/asset-assignments/${returningId.value}/return`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
  toast.success(t('asset_assignments.returned_successfully'))
  returningId.value = null
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
        :title="t('asset_assignments.title')"
        :subtitle="t('asset_assignments.subtitle')"
        :actions="[{ label: t('asset_assignments.new'), icon: 'mdi-plus', onClick: openCreate }]"
      />

      <AppDataTable
        :headers="headers"
        :items="assignments"
        :items-length="total"
        :loading="loading"
        :page="page"
        :items-per-page="perPage"
        :items-per-page-options="[10, 25, 50, 100]"
        :sort-by="sortByVuetify"
        :search="search"
        :empty-text="t('asset_assignments.empty')"
        @update:search="setSearch"
        @update:options="handleOptions"
      >
        <template #filters>
          <v-select
            v-model="filters.status"
            :label="t('common.status')"
            :items="[
              { title: t('common.all'), value: '' },
              { title: t('status.assigned'), value: 'assigned' },
              { title: t('status.active'), value: 'active' },
              { title: t('status.returned'), value: 'returned' },
              { title: t('status.overdue'), value: 'overdue' },
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
        <template #item.location="{ item }">{{ item.location?.name || t('common.n_a') }}</template>
        <template #item.status="{ item }"><StatusBadge :status="item.status" /></template>

        <template #item.actions="{ item }">
          <div v-if="item.status !== 'returned'" class="d-flex align-center justify-end ga-1">
            <v-btn
              icon="mdi-keyboard-return"
              size="small"
              variant="text"
              color="primary"
              :title="t('common.return')"
              @click="returningId = item.id; returnCondition = 'good'; returnRemark = ''"
            />
            <v-btn icon="mdi-close" size="small" variant="text" color="error" :title="t('common.cancel')" @click="cancelAssignment(item.id)" />
          </div>
        </template>
      </AppDataTable>
  </v-container>

  <Modal v-if="showModal" :title="t('asset_assignments.modal_title')" @close="showModal = false">
    <v-form @submit.prevent="handleSubmit">
      <v-card-text class="d-flex flex-column ga-1">
        <v-select
          v-model="form.asset_id"
          :label="t('asset_assignments.asset_required')"
          :items="assets.map((a) => ({ title: `${a.name} (${a.asset_code})`, value: a.id }))"
          required
        />
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select
              v-model="form.assigned_to_type"
              :label="t('asset_assignments.assign_to')"
              :items="[
                { title: t('asset_assignments.staff'), value: 'staff' },
                { title: t('asset_assignments.program'), value: 'program' },
              ]"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="form.assigned_to_id"
              :label="t('asset_assignments.recipient_required')"
              :items="(form.assigned_to_type === 'staff' ? staffList : programs).map((r) => ({ title: r.full_name || r.name, value: r.id }))"
              required
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="form.location_id"
              :label="t('asset_assignments.location_required')"
              :items="locations.map((l) => ({ title: l.name, value: l.id }))"
              required
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model.number="form.quantity" type="number" min="1" :label="t('asset_assignments.quantity_required')" required />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.assigned_date" type="date" :label="t('asset_assignments.assigned_date')" required />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.due_date" type="date" :label="t('asset_assignments.due_date')" />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn type="submit" color="primary" variant="flat" prepend-icon="mdi-plus">{{ t('asset_assignments.assign_button') }}</v-btn>
        <v-btn variant="text" @click="showModal = false">{{ t('common.cancel') }}</v-btn>
      </v-card-actions>
    </v-form>
  </Modal>

  <Modal v-if="returningId" :title="t('asset_assignments.return_title')" @close="returningId = null">
    <v-form @submit.prevent="submitReturn">
      <v-card-text class="d-flex flex-column ga-1">
        <v-select
          v-model="returnCondition"
          :label="t('asset_assignments.condition_required')"
          :items="[
            { title: t('asset_assignments.condition_good'), value: 'good' },
            { title: t('asset_assignments.condition_fair'), value: 'fair' },
            { title: t('asset_assignments.condition_broken'), value: 'broken' },
            { title: t('asset_assignments.condition_lost'), value: 'lost' },
          ]"
        />
        <v-textarea v-model="returnRemark" :label="t('asset_assignments.remark')" rows="2" />
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn type="submit" color="primary" variant="flat">{{ t('asset_assignments.confirm_return') }}</v-btn>
        <v-btn variant="text" @click="returningId = null">{{ t('common.cancel') }}</v-btn>
      </v-card-actions>
    </v-form>
  </Modal>
</template>
