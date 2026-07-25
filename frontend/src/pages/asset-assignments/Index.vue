<script setup>
import { ref, h, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { NDataTable } from 'naive-ui'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableFilter } from '../../composables/useTableFilter'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'

const { t } = useI18n()
const { items: assignments, loading, fetchAll } = useApiCrud('/asset-assignments', { entityName: t('asset_assignments.entity') })
const toast = useToastStore()
const auth = useAuthStore()

const { search, filtered: searched } = useTableSearch(assignments, [(a) => a.asset?.name, (a) => a.asset?.asset_code, 'recipient_name'])
const { filters, filtered: matched, hasActiveFilters, clearFilters } = useTableFilter(searched, {
  status: (row, v) => row.status === v,
})

const assets = ref([])
const locations = ref([])
const staffList = ref([])
const programs = ref([])
const showModal = ref(false)
const returningId = ref(null)
const returnCondition = ref('good')
const returnRemark = ref('')

const form = reactive({ asset_id: '', assigned_to_type: 'staff', assigned_to_id: '', location_id: '', quantity: 1, assigned_date: '', due_date: '' })

const columns = computed(() => [
  { title: t('common.asset'), key: 'asset', sorter: 'default', render: (a) => a.asset?.name || t('common.n_a') },
  { title: t('asset_assignments.recipient'), key: 'recipient_name', sorter: 'default', render: (a) => a.recipient_name },
  { title: t('common.location'), key: 'location', sorter: 'default', render: (a) => a.location?.name || t('common.n_a') },
  { title: t('asset_assignments.qty'), key: 'quantity', sorter: 'default', render: (a) => a.quantity },
  { title: t('common.status'), key: 'status', sorter: 'default', render: (a) => h(StatusBadge, { status: a.status }) },
  {
    title: t('common.actions'),
    key: 'actions',
    render(a) {
      if (a.status === 'returned') return null
      return h('div', { class: 'flex items-center justify-end gap-1.5' }, [
        h('button', {
          onClick: () => { returningId.value = a.id; returnCondition.value = 'good'; returnRemark.value = '' },
          title: t('common.return'),
          class: 'w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition',
        }, [
          h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', 'stroke-width': '2.2', viewBox: '0 0 24 24' }, [
            h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3' }),
          ]),
        ]),
        h('button', {
          onClick: () => cancelAssignment(a.id),
          title: t('common.cancel'),
          class: 'w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition',
        }, [
          h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', 'stroke-width': '2.2', viewBox: '0 0 24 24' }, [
            h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M6 18L18 6M6 6l12 12' }),
          ]),
        ]),
      ])
    },
  },
])

async function loadOptions() {
  const [a, l, s, p] = await Promise.all([
    http.get('/assets/export'), http.get('/locations'), http.get('/staff').catch(() => ({ data: [] })), http.get('/programs').catch(() => ({ data: [] })),
  ])
  assets.value = a.data.data
  locations.value = l.data
  staffList.value = s.data
  programs.value = p.data
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
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || t('asset_assignments.assign_failed'))
  }
}

async function cancelAssignment(id) {
  await http.post(`/asset-assignments/${id}/cancel`)
  toast.success(t('asset_assignments.cancelled'))
  await fetchAll()
}

async function submitReturn() {
  const fd = new FormData()
  fd.append('condition', returnCondition.value)
  fd.append('remark', returnRemark.value)
  await http.post(`/asset-assignments/${returningId.value}/return`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
  toast.success(t('asset_assignments.returned_successfully'))
  returningId.value = null
  await fetchAll()
}

onMounted(() => {
  fetchAll()
  loadOptions()
})
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-6xl mx-auto space-y-6">
      <PageHeader :title="t('asset_assignments.title')" :subtitle="t('asset_assignments.subtitle')" :buttonText="t('asset_assignments.new')" @action="openCreate" />

      <div class="table-wrap">
        <div class="table-toolbar">
          <div class="w-full sm:max-w-xs">
            <SearchInput v-model="search" :placeholder="t('common.search')" />
          </div>
          <select v-model="filters.status" class="filter-select">
            <option value="">{{ t('common.status') }}: {{ t('common.all') }}</option>
            <option value="assigned">{{ t('status.assigned') }}</option>
            <option value="active">{{ t('status.active') }}</option>
            <option value="returned">{{ t('status.returned') }}</option>
            <option value="overdue">{{ t('status.overdue') }}</option>
          </select>
          <button v-if="hasActiveFilters" @click="clearFilters" class="btn-subtle btn-sm">{{ t('common.clear_filters') }}</button>
        </div>
        <n-data-table
          :columns="columns"
          :data="matched"
          :loading="loading"
          :row-key="(row) => row.id"
          :pagination="{ pageSize: 10, showSizePicker: true, pageSizes: [10, 25, 50, 100] }"
          :bordered="false"
        >
          <template #empty>
            <p class="py-10 text-center text-faint text-sm">{{ t('asset_assignments.empty') }}</p>
          </template>
        </n-data-table>
      </div>
    </div>

    <Modal v-if="showModal" :title="t('asset_assignments.modal_title')" @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.asset_required') }}</label>
            <select v-model="form.asset_id" required class="input">
              <option value="">{{ t('common.select_asset') }}</option>
              <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }} ({{ a.asset_code }})</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.assign_to') }}</label>
              <select v-model="form.assigned_to_type" class="input">
                <option value="staff">{{ t('asset_assignments.staff') }}</option>
                <option value="program">{{ t('asset_assignments.program') }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.recipient_required') }}</label>
              <select v-model="form.assigned_to_id" required class="input">
                <option value="">{{ t('asset_assignments.select_recipient') }}</option>
                <option v-for="r in (form.assigned_to_type === 'staff' ? staffList : programs)" :key="r.id" :value="r.id">{{ r.full_name || r.name }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.location_required') }}</label>
              <select v-model="form.location_id" required class="input">
                <option value="">{{ t('common.select_location') }}</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.quantity_required') }}</label>
              <input v-model.number="form.quantity" type="number" min="1" required class="input" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.assigned_date') }}</label>
              <input v-model="form.assigned_date" type="date" required class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.due_date') }}</label>
              <input v-model="form.due_date" type="date" class="input" />
            </div>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ t('asset_assignments.assign_button') }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <Modal v-if="returningId" :title="t('asset_assignments.return_title')" @close="returningId = null">
      <form @submit.prevent="submitReturn">
        <div class="p-6 space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.condition_required') }}</label>
            <select v-model="returnCondition" class="input">
              <option value="good">{{ t('asset_assignments.condition_good') }}</option>
              <option value="fair">{{ t('asset_assignments.condition_fair') }}</option>
              <option value="broken">{{ t('asset_assignments.condition_broken') }}</option>
              <option value="lost">{{ t('asset_assignments.condition_lost') }}</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_assignments.remark') }}</label>
            <textarea v-model="returnRemark" rows="2" class="input"></textarea>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" class="btn-primary">{{ t('asset_assignments.confirm_return') }}</button>
          <button type="button" class="btn-ghost" @click="returningId = null">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>
