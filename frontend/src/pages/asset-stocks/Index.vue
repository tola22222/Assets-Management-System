<script setup>
import { ref, onMounted, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import AppDataTable from '../../components/common/AppDataTable.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useServerTable } from '../../composables/useServerTable'
import { useConfirm } from '../../composables/useConfirm'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const {
  items: receipts, loading, page, perPage, total, sortByVuetify,
  search, setSearch, handleOptions, fetchPage,
} = useServerTable('/asset-stocks')
const { create, destroy } = useApiCrud('/asset-stocks', { entityName: t('asset_stocks.entity'), refetch: fetchPage })
const { confirm } = useConfirm()
const toast = useToastStore()

const categories = ref([])
const locations = ref([])
const showModal = ref(false)
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

async function handleDelete(row) {
  const ok = await confirm({
    title: t('confirm.delete_title'),
    message: t('confirm.delete_message'),
    color: 'error',
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return
  try {
    await destroy(row.id)
  } catch (e) {
    toast.error(e.response?.data?.message || t('asset_stocks.delete_failed'))
  }
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
  <v-container fluid class="pa-0">
      <AppPageHeader
        :title="t('asset_stocks.title')"
        :subtitle="t('asset_stocks.subtitle')"
        :actions="[{ label: t('asset_stocks.new'), icon: 'mdi-plus', onClick: openCreate }]"
      />

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
        @delete="handleDelete"
      >
        <template #item.code="{ item }"><span class="font-mono text-caption">{{ item.asset?.asset_code || t('common.n_a') }}</span></template>
        <template #item.asset="{ item }"><span class="font-weight-medium">{{ item.asset?.name || t('common.n_a') }}</span></template>
        <template #item.location="{ item }">{{ item.to_location?.name || t('common.n_a') }}</template>
        <template #item.reference_no="{ item }"><span class="font-mono text-caption">{{ item.reference_no }}</span></template>
        <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>
      </AppDataTable>
  </v-container>

  <Modal v-if="showModal" :title="t('asset_stocks.new')" wide @close="showModal = false">
    <v-form @submit.prevent="handleSubmit">
      <v-card-text class="d-flex flex-column ga-1">
        <p class="text-caption text-medium-emphasis mb-2">{{ t('asset_stocks.hint') }}</p>
        <v-text-field v-model="form.name" :label="t('assets.name_required')" required />
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select
              v-model="form.category_id"
              :label="t('assets.category_required')"
              :items="categories.map((c) => ({ title: c.name, value: c.id }))"
              required
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="form.location_id"
              :label="t('asset_stocks.location_required')"
              :items="locations.map((l) => ({ title: l.name, value: l.id }))"
              required
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model.number="form.quantity" type="number" min="1" max="200" :label="t('asset_stocks.quantity_required')" required />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.brand" :label="t('assets.brand')" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.model" :label="t('assets.model')" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.purchase_date" type="date" :label="t('assets.purchase_date')" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.purchase_price" type="number" step="0.01" :label="t('assets.purchase_price')" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="form.condition"
              :label="t('assets.condition')"
              :items="[
                { title: t('assets.condition_good'), value: 'good' },
                { title: t('assets.condition_fair'), value: 'fair' },
                { title: t('assets.condition_broken'), value: 'broken' },
                { title: t('assets.condition_lost'), value: 'lost' },
              ]"
            />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn type="submit" color="primary" variant="flat" prepend-icon="mdi-plus" :loading="submitting">
          {{ t('asset_stocks.save') }}
        </v-btn>
        <v-btn variant="text" @click="showModal = false">{{ t('common.cancel') }}</v-btn>
      </v-card-actions>
    </v-form>
  </Modal>
</template>
