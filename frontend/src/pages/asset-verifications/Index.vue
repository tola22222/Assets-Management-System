<script setup>
import { ref, h, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { NDataTable } from 'naive-ui'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableFilter } from '../../composables/useTableFilter'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const { items: verifications, loading, fetchAll } = useApiCrud('/asset-verifications', { entityName: t('asset_verifications.entity') })
const toast = useToastStore()

const { search, filtered: searched } = useTableSearch(verifications, [(v) => v.asset?.name, (v) => v.asset?.asset_code, (v) => v.location?.name])
const { filters, filtered: matched, hasActiveFilters, clearFilters } = useTableFilter(searched, {
  condition: (row, v) => row.condition === v,
  completed: (row, v) => (v === 'yes' ? !!row.verified_at : !row.verified_at),
})

const assets = ref([])
const locations = ref([])
const showModal = ref(false)
const form = reactive({ asset_id: '', location_id: '', quantity_verified: 1, condition: 'good', remark: '' })

async function loadOptions() {
  const [a, l] = await Promise.all([http.get('/assets/export'), http.get('/locations')])
  assets.value = a.data.data
  locations.value = l.data
}

function openCreate() {
  Object.assign(form, { asset_id: '', location_id: '', quantity_verified: 1, condition: 'good', remark: '' })
  showModal.value = true
}

async function handleSubmit() {
  try {
    await http.post('/asset-verifications', form)
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

const columns = computed(() => [
  {
    title: t('common.asset'), key: 'asset', sorter: 'default',
    render: (v) => h('span', { class: 'font-medium text-fg' }, v.asset?.name || t('common.n_a')),
  },
  {
    title: t('common.location'), key: 'location', sorter: 'default',
    render: (v) => v.location?.name || t('common.n_a'),
  },
  {
    title: t('asset_returns.condition'), key: 'condition', sorter: 'default',
    render: (v) => h('span', { class: 'capitalize' }, v.condition),
  },
  {
    title: t('asset_verifications.verified_by'), key: 'verified_by', sorter: 'default',
    render: (v) => v.verified_by?.name || t('common.n_a'),
  },
  {
    title: t('common.status'), key: 'verified_at', sorter: 'default',
    render: (v) => h('span', {
      class: ['px-2.5 py-1 rounded-lg text-xs font-bold', v.verified_at ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'],
    }, v.verified_at ? t('asset_verifications.complete') : t('asset_verifications.pending')),
  },
  {
    title: t('common.actions'), key: 'actions', width: 70,
    render: (v) => {
      if (v.verified_at) return null
      return h('div', { class: 'flex justify-end' }, [
        h('button', {
          onClick: () => complete(v.id),
          title: t('common.mark_complete'),
          class: 'w-7 h-7 rounded-lg bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition',
        }, [h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', 'stroke-width': '2.2', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' }),
        ])]),
      ])
    },
  },
])

onMounted(() => {
  fetchAll()
  loadOptions()
})
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-6xl mx-auto space-y-6">
      <PageHeader :title="t('asset_verifications.title')" :subtitle="t('asset_verifications.subtitle')" :buttonText="t('asset_verifications.new')" @action="openCreate" />

      <div class="table-wrap">
        <div class="table-toolbar">
          <div class="w-full sm:max-w-xs">
            <SearchInput v-model="search" :placeholder="t('common.search')" />
          </div>
          <select v-model="filters.condition" class="filter-select">
            <option value="">{{ t('asset_returns.condition') }}: {{ t('common.all') }}</option>
            <option value="good">{{ t('asset_verifications.condition_good') }}</option>
            <option value="fair">{{ t('asset_verifications.condition_fair') }}</option>
            <option value="broken">{{ t('asset_verifications.condition_broken') }}</option>
            <option value="lost">{{ t('asset_verifications.condition_lost') }}</option>
          </select>
          <select v-model="filters.completed" class="filter-select">
            <option value="">{{ t('common.status') }}: {{ t('common.all') }}</option>
            <option value="yes">{{ t('asset_verifications.complete') }}</option>
            <option value="no">{{ t('asset_verifications.pending') }}</option>
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
            <p class="py-10 text-center text-faint text-sm">{{ t('asset_verifications.empty') }}</p>
          </template>
        </n-data-table>
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
