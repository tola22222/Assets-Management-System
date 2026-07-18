<script setup>
import { ref, onMounted, reactive } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import { useApiCrud } from '../../composables/useApiCrud'

const { items: stocks, loading, fetchAll, create, destroy } = useApiCrud('/asset-stocks', { entityName: 'Stock record' })

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
      <PageHeader title="Stock Movements" subtitle="Quantity of each asset held at each location" buttonText="New Stock Record" @action="openCreate" />

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-gray-400 font-semibold bg-gray-50/70 border-b border-gray-100">
              <th class="p-4 pl-5">Asset</th>
              <th class="p-4">Location</th>
              <th class="p-4">Quantity</th>
              <th class="p-4 pr-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="stock in stocks" :key="stock.id" class="hover:bg-gray-50/50">
              <td class="p-4 pl-5 font-medium text-ink">{{ stock.asset?.name || 'N/A' }}</td>
              <td class="p-4 text-gray-500">{{ stock.location?.name || 'N/A' }}</td>
              <td class="p-4 text-gray-500">{{ stock.quantity }}</td>
              <td class="p-4 pr-5 text-right">
                <button @click="deletingId = stock.id" class="text-red-500 hover:underline text-sm font-semibold">Delete</button>
              </td>
            </tr>
            <tr v-if="!loading && !stocks.length">
              <td colspan="4" class="p-8 text-center text-gray-400">No stock records yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showModal" title="New Stock Record" @close="showModal = false">
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Asset *</label>
          <select v-model="form.asset_id" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
            <option value="">Select Asset</option>
            <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }} ({{ a.asset_code }})</option>
          </select>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Location *</label>
          <select v-model="form.location_id" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
            <option value="">Select Location</option>
            <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
          </select>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Quantity *</label>
          <input v-model.number="form.quantity" type="number" min="0" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl w-full transition">Save</button>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
