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
const { items: disposals, loading, fetchAll } = useApiCrud('/asset-disposals', { entityName: t('asset_disposals.entity') })
const toast = useToastStore()
const auth = useAuthStore()

const { search, filtered: searched } = useTableSearch(disposals, [(d) => d.asset?.name, (d) => d.asset?.asset_code, (d) => d.requester?.name, 'reason'])
const { filters, filtered: matched, hasActiveFilters, clearFilters } = useTableFilter(searched, {
  status: (row, v) => row.status === v,
  recommended_action: (row, v) => row.recommended_action === v,
})

const assets = ref([])
const showModal = ref(false)
const imageFile = ref(null)
const form = reactive({ asset_id: '', recommended_action: 'disposal', reason: '' })

const canApprove = () => ['operations_hr_manager', 'executive_director'].includes(auth.user?.role)

function approveRejectButtons(d) {
  if (!(d.status === 'pending' && canApprove())) return null
  return h('div', { class: 'flex items-center justify-end gap-1.5' }, [
    h('button', {
      onClick: () => approve(d.id),
      title: t('common.approve'),
      class: 'w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition',
    }, [h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', 'stroke-width': '2.2', viewBox: '0 0 24 24' }, [
      h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' }),
    ])]),
    h('button', {
      onClick: () => reject(d.id),
      title: t('common.reject'),
      class: 'w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition',
    }, [h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', 'stroke-width': '2.2', viewBox: '0 0 24 24' }, [
      h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z' }),
    ])]),
  ])
}

const columns = computed(() => [
  {
    title: t('common.asset'), key: 'asset', sorter: 'default',
    render: (d) => h('span', { class: 'font-medium text-fg' }, d.asset?.name || t('common.n_a')),
  },
  {
    title: t('asset_disposals.action_col'), key: 'recommended_action', sorter: 'default',
    render: (d) => h('span', { class: 'capitalize' }, d.recommended_action),
  },
  {
    title: t('asset_disposals.reason_col'), key: 'reason',
    render: (d) => h('span', { class: 'max-w-xs truncate block', title: d.reason }, d.reason),
  },
  {
    title: t('asset_disposals.photo'), key: 'image_url',
    render: (d) => d.image_url
      ? h('a', { href: d.image_url, target: '_blank' }, [h('img', { src: d.image_url, class: 'w-9 h-9 rounded-lg object-cover border border-line', alt: '' })])
      : h('span', { class: 'text-faint' }, '—'),
  },
  {
    title: t('asset_disposals.requested_by'), key: 'requester', sorter: 'default',
    render: (d) => d.requester?.name || t('common.n_a'),
  },
  {
    title: t('common.status'), key: 'status', sorter: 'default',
    render: (d) => h(StatusBadge, { status: d.status }),
  },
  {
    title: t('common.actions'), key: 'actions', width: 90,
    render: (d) => approveRejectButtons(d),
  },
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
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || t('asset_disposals.submit_failed'))
  }
}

async function approve(id) {
  await http.post(`/asset-disposals/${id}/approve`)
  toast.success(t('asset_disposals.approved'))
  await fetchAll()
}

async function reject(id) {
  await http.post(`/asset-disposals/${id}/reject`)
  toast.success(t('asset_disposals.rejected'))
  await fetchAll()
}

onMounted(() => {
  fetchAll()
  loadAssets()
})
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-6xl mx-auto space-y-6">
      <PageHeader :title="t('asset_disposals.title')" :subtitle="t('asset_disposals.subtitle')" :buttonText="t('asset_disposals.new')" @action="openCreate" />

      <div class="table-wrap">
        <div class="table-toolbar">
          <div class="w-full sm:max-w-xs">
            <SearchInput v-model="search" :placeholder="t('common.search')" />
          </div>
          <select v-model="filters.recommended_action" class="filter-select">
            <option value="">{{ t('asset_disposals.action_col') }}: {{ t('common.all') }}</option>
            <option value="repair">{{ t('asset_disposals.action_repair') }}</option>
            <option value="disposal">{{ t('asset_disposals.action_disposal') }}</option>
            <option value="replacement">{{ t('asset_disposals.action_replacement') }}</option>
          </select>
          <select v-model="filters.status" class="filter-select">
            <option value="">{{ t('common.status') }}: {{ t('common.all') }}</option>
            <option value="pending">{{ t('status.pending') }}</option>
            <option value="approved">{{ t('status.approved') }}</option>
            <option value="rejected">{{ t('status.rejected') }}</option>
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
            <p class="py-10 text-center text-faint text-sm">{{ t('asset_disposals.empty') }}</p>
          </template>
        </n-data-table>
      </div>
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
  </AppLayout>
</template>
