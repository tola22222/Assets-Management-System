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

const { items: assignments, loading, fetchAll } = useApiCrud('/asset-assignments', { entityName: 'Assignment' })
const toast = useToastStore()
const auth = useAuthStore()

const assets = ref([])
const locations = ref([])
const staffList = ref([])
const programs = ref([])
const showModal = ref(false)
const returningId = ref(null)
const returnCondition = ref('good')
const returnRemark = ref('')

const form = reactive({ asset_id: '', assigned_to_type: 'staff', assigned_to_id: '', location_id: '', quantity: 1, assigned_date: '', due_date: '' })

async function loadOptions() {
  const [a, l, s, p] = await Promise.all([
    http.get('/assets'), http.get('/locations'), http.get('/staff').catch(() => ({ data: [] })), http.get('/programs').catch(() => ({ data: [] })),
  ])
  assets.value = a.data
  locations.value = l.data
  staffList.value = s.data
  programs.value = p.data
}

function openCreate() {
  Object.assign(form, { asset_id: '', assigned_to_type: 'staff', assigned_to_id: '', location_id: '', quantity: 1, assigned_date: new Date().toISOString().slice(0, 10), due_date: '' })
  showModal.value = true
}

async function handleSubmit() {
  try {
    await http.post('/asset-assignments', form)
    toast.success('Asset assigned successfully.')
    showModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not create assignment.')
  }
}

async function cancelAssignment(id) {
  await http.post(`/asset-assignments/${id}/cancel`)
  toast.success('Assignment cancelled.')
  await fetchAll()
}

async function submitReturn() {
  const fd = new FormData()
  fd.append('condition', returnCondition.value)
  fd.append('remark', returnRemark.value)
  await http.post(`/asset-assignments/${returningId.value}/return`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
  toast.success('Asset returned successfully.')
  returningId.value = null
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
      <PageHeader title="Asset Assignments" subtitle="Assets checked out to staff or programs" buttonText="New Assignment" @action="openCreate" />

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-gray-400 font-semibold bg-gray-50/70 border-b border-gray-100">
              <th class="p-4 pl-5">Asset</th>
              <th class="p-4">Recipient</th>
              <th class="p-4">Location</th>
              <th class="p-4">Qty</th>
              <th class="p-4">Status</th>
              <th class="p-4 pr-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="a in assignments" :key="a.id" class="hover:bg-gray-50/50">
              <td class="p-4 pl-5 font-medium text-ink">{{ a.asset?.name || 'N/A' }}</td>
              <td class="p-4 text-gray-500">{{ a.recipient_name }}</td>
              <td class="p-4 text-gray-500">{{ a.location?.name || 'N/A' }}</td>
              <td class="p-4 text-gray-500">{{ a.quantity }}</td>
              <td class="p-4"><StatusBadge :status="a.status" /></td>
              <td class="p-4 pr-5 text-right whitespace-nowrap">
                <template v-if="a.status !== 'returned'">
                  <button @click="returningId = a.id; returnCondition = 'good'; returnRemark = ''" class="text-brand hover:underline mr-3 text-sm font-semibold">Return</button>
                  <button @click="cancelAssignment(a.id)" class="text-red-500 hover:underline text-sm font-semibold">Cancel</button>
                </template>
              </td>
            </tr>
            <tr v-if="!loading && !assignments.length">
              <td colspan="6" class="p-8 text-center text-gray-400">No assignments yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showModal" title="New Assignment" @close="showModal = false">
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
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Assign To *</label>
            <select v-model="form.assigned_to_type" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
              <option value="staff">Staff</option>
              <option value="program">Program</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Recipient *</label>
            <select v-model="form.assigned_to_id" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
              <option value="">Select</option>
              <option v-for="r in (form.assigned_to_type === 'staff' ? staffList : programs)" :key="r.id" :value="r.id">{{ r.full_name || r.name }}</option>
            </select>
          </div>
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
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Quantity *</label>
            <input v-model.number="form.quantity" type="number" min="1" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Assigned Date *</label>
            <input v-model="form.assigned_date" type="date" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Due Date</label>
            <input v-model="form.due_date" type="date" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
        </div>
        <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl w-full transition">Assign Asset</button>
      </form>
    </Modal>

    <Modal v-if="returningId" title="Return Asset" @close="returningId = null">
      <form @submit.prevent="submitReturn" class="p-6 space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Condition *</label>
          <select v-model="returnCondition" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
            <option value="good">Good</option>
            <option value="fair">Fair</option>
            <option value="broken">Broken</option>
            <option value="lost">Lost</option>
          </select>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Remark</label>
          <textarea v-model="returnRemark" rows="2" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand"></textarea>
        </div>
        <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl w-full transition">Confirm Return</button>
      </form>
    </Modal>
  </AppLayout>
</template>
