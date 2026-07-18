<script setup>
import { ref, onMounted, reactive } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useToastStore } from '../../stores/toast'

const { items: verifications, loading, fetchAll } = useApiCrud('/asset-verifications', { entityName: 'Verification' })
const toast = useToastStore()

const assets = ref([])
const locations = ref([])
const showModal = ref(false)
const form = reactive({ asset_id: '', location_id: '', quantity_verified: 1, condition: 'good', remark: '' })

async function loadOptions() {
  const [a, l] = await Promise.all([http.get('/assets'), http.get('/locations')])
  assets.value = a.data
  locations.value = l.data
}

function openCreate() {
  Object.assign(form, { asset_id: '', location_id: '', quantity_verified: 1, condition: 'good', remark: '' })
  showModal.value = true
}

async function handleSubmit() {
  try {
    await http.post('/asset-verifications', form)
    toast.success('Verification recorded successfully.')
    showModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not record verification.')
  }
}

async function complete(id) {
  await http.post(`/asset-verifications/${id}/complete`)
  toast.success('Verification marked complete.')
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
      <PageHeader title="Asset Verifications" subtitle="Physical count and condition checks" buttonText="New Verification" @action="openCreate" />

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-gray-400 font-semibold bg-gray-50/70 border-b border-gray-100">
              <th class="p-4 pl-5">Asset</th>
              <th class="p-4">Location</th>
              <th class="p-4">Condition</th>
              <th class="p-4">Verified By</th>
              <th class="p-4">Status</th>
              <th class="p-4 pr-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="v in verifications" :key="v.id" class="hover:bg-gray-50/50">
              <td class="p-4 pl-5 font-medium text-ink">{{ v.asset?.name || 'N/A' }}</td>
              <td class="p-4 text-gray-500">{{ v.location?.name || 'N/A' }}</td>
              <td class="p-4 text-gray-500 capitalize">{{ v.condition }}</td>
              <td class="p-4 text-gray-500">{{ v.verified_by?.name || 'N/A' }}</td>
              <td class="p-4">
                <span class="px-2.5 py-1 rounded-lg text-xs font-bold" :class="v.verified_at ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                  {{ v.verified_at ? 'COMPLETE' : 'PENDING' }}
                </span>
              </td>
              <td class="p-4 pr-5 text-right">
                <button v-if="!v.verified_at" @click="complete(v.id)" class="text-brand hover:underline text-sm font-semibold">Mark Complete</button>
              </td>
            </tr>
            <tr v-if="!loading && !verifications.length">
              <td colspan="6" class="p-8 text-center text-gray-400">No verifications recorded yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showModal" title="New Verification" @close="showModal = false">
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
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Location *</label>
            <select v-model="form.location_id" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
              <option value="">Select Location</option>
              <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Quantity Verified *</label>
            <input v-model.number="form.quantity_verified" type="number" min="1" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Condition *</label>
          <select v-model="form.condition" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
            <option value="good">Good</option>
            <option value="fair">Fair</option>
            <option value="broken">Broken</option>
            <option value="lost">Lost</option>
          </select>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Remark</label>
          <textarea v-model="form.remark" rows="2" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand"></textarea>
        </div>
        <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl w-full transition">Save Verification</button>
      </form>
    </Modal>
  </AppLayout>
</template>
