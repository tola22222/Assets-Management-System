<script setup>
import { ref, onMounted, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useServerTable } from '../../composables/useServerTable'

const { t } = useI18n()
const {
  items: receipts, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
} = useServerTable('/asset-stocks')
const { create, destroy } = useApiCrud('/asset-stocks', { entityName: t('asset_stocks.entity'), refetch: fetchPage })

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
  const [categoriesRes, locationsRes] = await Promise.all([
    http.get('/categories', { params: { per_page: 100 } }),
    http.get('/locations', { params: { per_page: 100 } }),
  ])
  categories.value = categoriesRes.data.data ?? categoriesRes.data
  locations.value = locationsRes.data.data ?? locationsRes.data
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

const headers = computed(() => [
  { title: t('assets.code'), key: 'code', sortable: false },
  { title: t('common.asset'), key: 'asset', sortable: false },
  { title: t('common.location'), key: 'location', sortable: false },
  { title: t('asset_stocks.reference'), key: 'reference_no', sortable: true },
  { title: t('asset_stocks.received_at'), key: 'created_at', sortable: true },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end', width: 70 },
])

onMounted(() => {
  fetchPage()
  loadOptions()
})
</script>

<template>
    <div class="p-8 max-w-6xl mx-auto space-y-6">
      <PageHeader :title="t('asset_stocks.title')" :subtitle="t('asset_stocks.subtitle')" :buttonText="t('asset_stocks.new')" @action="openCreate" />

      <AppDataTable
        :headers="headers"
        :items="receipts"
        :items-length="total"
        :loading="loading"
        :page="page"
        :items-per-page="perPage"
        :items-per-page-options="[10, 25, 50, 100]"
        :sort-by="sortByVuetify"
        :search="search"
        :empty-text="t('asset_stocks.empty')"
        :show-view="false"
        :show-edit="false"
        @update:search="setSearch"
        @update:options="handleOptions"
        @delete="(row) => (deletingId = row.id)"
      >
        <template #item.code="{ item }"><span class="font-mono text-xs text-fg">{{ item.asset?.asset_code || t('common.n_a') }}</span></template>
        <template #item.asset="{ item }"><span class="font-medium text-fg">{{ item.asset?.name || t('common.n_a') }}</span></template>
        <template #item.location="{ item }">{{ item.to_location?.name || t('common.n_a') }}</template>
        <template #item.reference_no="{ item }"><span class="font-mono text-xs">{{ item.reference_no }}</span></template>
        <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>
      </AppDataTable>
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
</template>
