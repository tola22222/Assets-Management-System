<script setup>
import { ref, onMounted, reactive } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useToastStore } from '../../stores/toast'
import { useAuthStore } from '../../stores/auth'

const { items: transfers, loading, fetchAll } = useApiCrud('/asset-transfers', { entityName: 'Transfer' })
const toast = useToastStore()
const auth = useAuthStore()

const assets = ref([])
const locations = ref([])
const showModal = ref(false)
const form = reactive({ asset_id: '', from_location_id: '', to_location_id: '', reason: '', transfer_date: '' })

async function loadOptions() {
  const [a, l] = await Promise.all([http.get('/assets'), http.get('/locations')])
  assets.value = a.data
  locations.value = l.data
}

function openCreate() {
  Object.assign(form, { asset_id: '', from_location_id: '', to_location_id: '', reason: '', transfer_date: new Date().toISOString().slice(0, 10) })
  showModal.value = true
}

async function handleSubmit() {
  try {
    await http.post('/asset-transfers', form)
    toast.success('Transfer submitted successfully.')
    showModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not submit transfer.')
  }
}

async function approve(id) {
  await http.post(`/asset-transfers/${id}/approve`)
  toast.success('Transfer approved.')
  await fetchAll()
}

async function reject(id) {
  await http.post(`/asset-transfers/${id}/reject`)
  toast.success('Transfer rejected.')
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
      <PageHeader title="Asset Transfers" subtitle="Move assets between locations" buttonText="New Transfer" @action="openCreate" />

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-gray-400 font-semibold bg-gray-50/70 border-b border-gray-100">
              <th class="p-4 pl-5">Asset</th>
              <th class="p-4">From</th>
              <th class="p-4">To</th>
              <th class="p-4">Requester</th>
              <th class="p-4">Status</th>
              <th class="p-4 pr-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="t in transfers" :key="t.id" class="hover:bg-gray-50/50">
              <td class="p-4 pl-5 font-medium text-ink">{{ t.asset?.name || 'N/A' }}</td>
              <td class="p-4 text-gray-500">{{ t.from_location?.name || 'N/A' }}</td>
              <td class="p-4 text-gray-500">{{ t.to_location?.name || 'N/A' }}</td>
              <td class="p-4 text-gray-500">{{ t.requester?.name || 'N/A' }}</td>
              <td class="p-4"><StatusBadge :status="t.status" /></td>
              <td class="p-4 pr-5 text-right whitespace-nowrap">
                <template v-if="t.status === 'pending' && auth.user?.role === 'admin'">
                  <button @click="approve(t.id)" class="text-brand hover:underline mr-3 text-sm font-semibold">Approve</button>
                  <button @click="reject(t.id)" class="text-red-500 hover:underline text-sm font-semibold">Reject</button>
                </template>
              </td>
            </tr>
            <tr v-if="!loading && !transfers.length">
              <td colspan="6" class="p-8 text-center text-gray-400">No transfers yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showModal" title="New Transfer Request" @close="showModal = false">
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Asset *</label>
          <select v-model="form.asset_id" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
            <option value="">Select Asset</option>
            <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }} ({{ a.asset_code }})</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">From Location *</label>
            <select v-model="form.from_location_id" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
              <option value="">Select Location</option>
              <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">To Location *</label>
            <select v-model="form.to_location_id" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
              <option value="">Select Location</option>
              <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
          </div>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Transfer Date *</label>
          <input v-model="form.transfer_date" type="date" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Reason</label>
          <textarea v-model="form.reason" rows="2" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand"></textarea>
        </div>
        <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl w-full transition">Submit Transfer</button>
      </form>
    </Modal>
  </AppLayout>
</template>
