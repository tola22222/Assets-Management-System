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

const { items: disposals, loading, fetchAll } = useApiCrud('/asset-disposals', { entityName: 'Disposal request' })
const toast = useToastStore()
const auth = useAuthStore()

const assets = ref([])
const showModal = ref(false)
const imageFile = ref(null)
const form = reactive({ asset_id: '', recommended_action: 'disposal', reason: '' })

const canApprove = () => ['admin', 'executive_director'].includes(auth.user?.role)

async function loadAssets() {
  const { data } = await http.get('/assets')
  assets.value = data.filter((a) => a.status === 'active')
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
    toast.success('Disposal request submitted for Executive Director approval.')
    showModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not submit disposal request.')
  }
}

async function approve(id) {
  await http.post(`/asset-disposals/${id}/approve`)
  toast.success('Disposal request approved.')
  await fetchAll()
}

async function reject(id) {
  await http.post(`/asset-disposals/${id}/reject`)
  toast.success('Disposal request rejected.')
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
      <PageHeader title="Asset Disposals" subtitle="Repair, disposal & replacement requests requiring Executive Director approval" buttonText="New Request" @action="openCreate" />

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-gray-400 font-semibold bg-gray-50/70 border-b border-gray-100">
              <th class="p-4 pl-5">Asset</th>
              <th class="p-4">Action</th>
              <th class="p-4">Reason</th>
              <th class="p-4">Photo</th>
              <th class="p-4">Requested By</th>
              <th class="p-4">Status</th>
              <th class="p-4 pr-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="d in disposals" :key="d.id" class="hover:bg-gray-50/50">
              <td class="p-4 pl-5 font-medium text-ink">{{ d.asset?.name || 'N/A' }}</td>
              <td class="p-4 text-gray-500 capitalize">{{ d.recommended_action }}</td>
              <td class="p-4 max-w-xs truncate text-gray-500" :title="d.reason">{{ d.reason }}</td>
              <td class="p-4">
                <a v-if="d.image_url" :href="d.image_url" target="_blank"><img :src="d.image_url" class="w-9 h-9 rounded-lg object-cover border border-gray-200" alt="" /></a>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="p-4 text-gray-500">{{ d.requester?.name || 'N/A' }}</td>
              <td class="p-4"><StatusBadge :status="d.status" /></td>
              <td class="p-4 pr-5 text-right whitespace-nowrap">
                <template v-if="d.status === 'pending' && canApprove()">
                  <button @click="approve(d.id)" class="text-brand hover:underline mr-3 text-sm font-semibold">Approve</button>
                  <button @click="reject(d.id)" class="text-red-500 hover:underline text-sm font-semibold">Reject</button>
                </template>
              </td>
            </tr>
            <tr v-if="!loading && !disposals.length">
              <td colspan="7" class="p-8 text-center text-gray-400">No disposal requests recorded.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showModal" title="New Disposal Request" @close="showModal = false">
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Asset *</label>
          <select v-model="form.asset_id" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
            <option value="">Select Asset</option>
            <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }} ({{ a.asset_code }})</option>
          </select>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Recommended Action *</label>
          <select v-model="form.recommended_action" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
            <option value="repair">Repair</option>
            <option value="disposal">Disposal</option>
            <option value="replacement">Replacement</option>
          </select>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Reason *</label>
          <textarea v-model="form.reason" rows="3" required placeholder="Describe the condition and why this action is recommended" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand"></textarea>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Photo Reference</label>
          <input type="file" accept="image/jpeg,image/png" @change="handleFileChange" class="w-full text-sm" />
        </div>
        <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl w-full transition">Submit Request</button>
      </form>
    </Modal>
  </AppLayout>
</template>
