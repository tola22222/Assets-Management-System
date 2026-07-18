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

const { items: returnsList, loading, fetchAll } = useApiCrud('/asset-returns', { entityName: 'Return' })
const toast = useToastStore()
const auth = useAuthStore()

const assignments = ref([])
const showModal = ref(false)
const form = reactive({ assignment_id: '', asset_id: '', condition: 'good', damage_notes: '', return_date: '' })

async function loadOptions() {
  const { data } = await http.get('/asset-assignments')
  assignments.value = data.filter((a) => a.status !== 'returned')
}

function openCreate() {
  Object.assign(form, { assignment_id: '', asset_id: '', condition: 'good', damage_notes: '', return_date: new Date().toISOString().slice(0, 10) })
  showModal.value = true
}

function onAssignmentChange() {
  const assignment = assignments.value.find((a) => a.id == form.assignment_id)
  form.asset_id = assignment?.asset_id || ''
}

async function handleSubmit() {
  try {
    await http.post('/asset-returns', form)
    toast.success('Return request submitted.')
    showModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not submit return.')
  }
}

async function approve(id) {
  await http.post(`/asset-returns/${id}/approve`)
  toast.success('Return approved.')
  await fetchAll()
}

async function reject(id) {
  await http.post(`/asset-returns/${id}/reject`)
  toast.success('Return rejected.')
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
      <PageHeader title="Asset Returns" subtitle="Return requests for assigned assets" buttonText="New Return" @action="openCreate" />

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-gray-400 font-semibold bg-gray-50/70 border-b border-gray-100">
              <th class="p-4 pl-5">Asset</th>
              <th class="p-4">Condition</th>
              <th class="p-4">Returned By</th>
              <th class="p-4">Status</th>
              <th class="p-4 pr-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="r in returnsList" :key="r.id" class="hover:bg-gray-50/50">
              <td class="p-4 pl-5 font-medium text-ink">{{ r.asset?.name || 'N/A' }}</td>
              <td class="p-4 text-gray-500 capitalize">{{ r.condition }}</td>
              <td class="p-4 text-gray-500">{{ r.returned_by?.name || 'N/A' }}</td>
              <td class="p-4"><StatusBadge :status="r.status" /></td>
              <td class="p-4 pr-5 text-right whitespace-nowrap">
                <template v-if="r.status === 'pending' && auth.user?.role === 'admin'">
                  <button @click="approve(r.id)" class="text-brand hover:underline mr-3 text-sm font-semibold">Approve</button>
                  <button @click="reject(r.id)" class="text-red-500 hover:underline text-sm font-semibold">Reject</button>
                </template>
              </td>
            </tr>
            <tr v-if="!loading && !returnsList.length">
              <td colspan="5" class="p-8 text-center text-gray-400">No return requests yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showModal" title="New Return Request" @close="showModal = false">
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Assignment *</label>
          <select v-model="form.assignment_id" required @change="onAssignmentChange" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
            <option value="">Select Assignment</option>
            <option v-for="a in assignments" :key="a.id" :value="a.id">{{ a.asset?.name }} — {{ a.recipient_name }}</option>
          </select>
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
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Return Date *</label>
          <input v-model="form.return_date" type="date" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Damage Notes</label>
          <textarea v-model="form.damage_notes" rows="2" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand"></textarea>
        </div>
        <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl w-full transition">Submit Return</button>
      </form>
    </Modal>
  </AppLayout>
</template>
