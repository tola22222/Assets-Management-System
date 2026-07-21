<script setup>
import { ref, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import SearchInput from '../../components/ui/SearchInput.vue'
import TableSortIcon from '../../components/ui/TableSortIcon.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useTableSearch } from '../../composables/useTableSearch'
import { useTableSort } from '../../composables/useTableSort'

const { t } = useI18n()
const { items: receipts, loading, fetchAll, create, destroy } = useApiCrud('/asset-stocks', { entityName: t('asset_stocks.entity') })
const { search, filtered: searched } = useTableSearch(receipts, [(r) => r.asset?.name, (r) => r.asset?.asset_code, (r) => r.to_location?.name, 'reference_no'])
const { sortKey, sortDir, toggleSort, sorted: sortedReceipts } = useTableSort(searched, {
  defaultKey: 'created_at', defaultDir: 'desc',
  paths: { code: 'asset.asset_code', asset: 'asset.name', location: 'to_location.name' },
})

const categories = ref([])
const locations = ref([])
const showModal = ref(false)
const deletingId = ref(null)
const submitting = ref(false)

const emptyForm = () => ({
  name: '', category_id: '', location_id: '', quantity: 1,
  brand: '', model: '', purchase_date: '', purchase_price: '', condition: 'good',
})
const form = reactive(emptyForm())

async function loadOptions() {
  const [categoriesRes, locationsRes] = await Promise.all([http.get('/categories'), http.get('/locations')])
  categories.value = categoriesRes.data
  locations.value = locationsRes.data
}

function openCreate() {
  Object.assign(form, emptyForm())
  showModal.value = true
}

async function handleSubmit() {
  submitting.value = true
  try {
    await create(form)
    showModal.value = false
  } finally {
    submitting.value = false
  }
}

async function confirmDelete() {
  await destroy(deletingId.value)
  deletingId.value = null
}

function formatDate(v) {
  return v ? new Date(v).toLocaleDateString() : '—'
}

onMounted(() => {
  fetchAll()
  loadOptions()
})
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-6xl mx-auto space-y-6">
      <PageHeader :title="t('asset_stocks.title')" :subtitle="t('asset_stocks.subtitle')" :buttonText="t('asset_stocks.new')" @action="openCreate" />

      <div class="w-full sm:max-w-xs">
        <SearchInput v-model="search" :placeholder="t('common.search')" />
      </div>

      <div class="bg-surface rounded-2xl border border-line overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-faint font-semibold bg-surface-2/70 border-b border-line">
              <th class="p-4 pl-5 th-sort" @click="toggleSort('code')">{{ t('assets.code') }}<TableSortIcon :active="sortKey === 'code'" :direction="sortDir" /></th>
              <th class="p-4 th-sort" @click="toggleSort('asset')">{{ t('common.asset') }}<TableSortIcon :active="sortKey === 'asset'" :direction="sortDir" /></th>
              <th class="p-4 th-sort" @click="toggleSort('location')">{{ t('common.location') }}<TableSortIcon :active="sortKey === 'location'" :direction="sortDir" /></th>
              <th class="p-4">{{ t('asset_stocks.reference') }}</th>
              <th class="p-4 th-sort" @click="toggleSort('created_at')">{{ t('asset_stocks.received_at') }}<TableSortIcon :active="sortKey === 'created_at'" :direction="sortDir" /></th>
              <th class="p-4 pr-5 text-right">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            <tr v-for="r in sortedReceipts" :key="r.id" class="hover:bg-surface-2/50">
              <td class="p-4 pl-5 font-mono text-xs text-fg">{{ r.asset?.asset_code || t('common.n_a') }}</td>
              <td class="p-4 font-medium text-fg">{{ r.asset?.name || t('common.n_a') }}</td>
              <td class="p-4 text-muted">{{ r.to_location?.name || t('common.n_a') }}</td>
              <td class="p-4 text-muted font-mono text-xs">{{ r.reference_no }}</td>
              <td class="p-4 text-muted">{{ formatDate(r.created_at) }}</td>
              <td class="p-4 pr-5 text-right">
                <button @click="deletingId = r.id" :title="t('common.delete')" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                </button>
              </td>
            </tr>
            <tr v-if="!loading && !sortedReceipts.length">
              <td colspan="6" class="p-8 text-center text-faint">{{ t('asset_stocks.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showModal" :title="t('asset_stocks.new')" wide @close="showModal = false">
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <p class="text-xs text-muted">{{ t('asset_stocks.hint') }}</p>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('assets.name_required') }} <span class="text-red-500">*</span></label>
            <input v-model="form.name" required class="input" />
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('assets.category_required') }} <span class="text-red-500">*</span></label>
              <select v-model="form.category_id" required class="input">
                <option value="">{{ t('assets.select_category') }}</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_stocks.location_required') }}</label>
              <select v-model="form.location_id" required class="input">
                <option value="">{{ t('common.select_location') }}</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_stocks.quantity_required') }}</label>
              <input v-model.number="form.quantity" type="number" min="1" max="200" required class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('assets.brand') }}</label>
              <input v-model="form.brand" class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('assets.model') }}</label>
              <input v-model="form.model" class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('assets.purchase_date') }}</label>
              <input v-model="form.purchase_date" type="date" class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('assets.purchase_price') }}</label>
              <input v-model="form.purchase_price" type="number" step="0.01" class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('assets.condition') }}</label>
              <select v-model="form.condition" class="input">
                <option value="good">{{ t('assets.condition_good') }}</option>
                <option value="fair">{{ t('assets.condition_fair') }}</option>
                <option value="broken">{{ t('assets.condition_broken') }}</option>
                <option value="lost">{{ t('assets.condition_lost') }}</option>
              </select>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-line px-6 py-4">
          <button type="submit" :disabled="submitting" class="btn-primary">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ t('asset_stocks.save') }}
          </button>
          <button type="button" class="btn-ghost" @click="showModal = false">{{ t('common.cancel') }}</button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
