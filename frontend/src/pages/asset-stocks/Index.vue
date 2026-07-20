<script setup>
import { ref, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import { useApiCrud } from '../../composables/useApiCrud'

const { t } = useI18n()
const { items: stocks, loading, fetchAll, create, destroy } = useApiCrud('/asset-stocks', { entityName: t('asset_stocks.entity') })

const assets = ref([])
const locations = ref([])
const showModal = ref(false)
const deletingId = ref(null)
const form = reactive({ asset_id: '', location_id: '', quantity: 1 })

async function loadOptions() {
  const [assetsRes, locationsRes] = await Promise.all([http.get('/assets'), http.get('/locations')])
  assets.value = assetsRes.data
  locations.value = locationsRes.data
}

function openCreate() {
  Object.assign(form, { asset_id: '', location_id: '', quantity: 1 })
  showModal.value = true
}

async function handleSubmit() {
  await create(form)
  showModal.value = false
}

async function confirmDelete() {
  await destroy(deletingId.value)
  deletingId.value = null
}

onMounted(() => {
  fetchAll()
  loadOptions()
})
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-5xl mx-auto space-y-6">
      <PageHeader :title="t('asset_stocks.title')" :subtitle="t('asset_stocks.subtitle')" :buttonText="t('asset_stocks.new')" @action="openCreate" />

      <div class="bg-surface rounded-2xl border border-line overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-faint font-semibold bg-surface-2/70 border-b border-line">
              <th class="p-4 pl-5">{{ t('common.asset') }}</th>
              <th class="p-4">{{ t('common.location') }}</th>
              <th class="p-4">{{ t('common.quantity') }}</th>
              <th class="p-4 pr-5 text-right">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            <tr v-for="stock in stocks" :key="stock.id" class="hover:bg-surface-2/50">
              <td class="p-4 pl-5 font-medium text-fg">{{ stock.asset?.name || t('common.n_a') }}</td>
              <td class="p-4 text-muted">{{ stock.location?.name || t('common.n_a') }}</td>
              <td class="p-4 text-muted">{{ stock.quantity }}</td>
              <td class="p-4 pr-5 text-right">
                <button @click="deletingId = stock.id" :title="t('common.delete')" class="w-7 h-7 rounded-lg bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                </button>
              </td>
            </tr>
            <tr v-if="!loading && !stocks.length">
              <td colspan="4" class="p-8 text-center text-faint">{{ t('asset_stocks.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showModal" :title="t('asset_stocks.new')" @close="showModal = false">
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-muted tracking-wide">{{ t('asset_stocks.asset_required') }}</label>
          <select v-model="form.asset_id" required class="input">
            <option value="">{{ t('common.select_asset') }}</option>
            <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }} ({{ a.asset_code }})</option>
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
          <input v-model.number="form.quantity" type="number" min="0" required class="input" />
        </div>
        <button type="submit" class="btn-primary w-full">{{ t('asset_stocks.save') }}</button>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
