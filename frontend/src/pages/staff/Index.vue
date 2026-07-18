<script setup>
import { ref, onMounted, reactive } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useToastStore } from '../../stores/toast'

const { items: staffList, loading, fetchAll, destroy } = useApiCrud('/staff', { entityName: 'Staff member' })
const toast = useToastStore()

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const photoFile = ref(null)
const emptyForm = () => ({ full_name: '', email: '', phone: '', position: '', hire_date: '', status: 'active' })
const form = reactive(emptyForm())

function openCreate() {
  editingId.value = null
  Object.assign(form, emptyForm())
  photoFile.value = null
  showModal.value = true
}

function openEdit(staff) {
  editingId.value = staff.id
  Object.assign(form, {
    full_name: staff.full_name, email: staff.email || '', phone: staff.phone || '',
    position: staff.position || '', hire_date: staff.hire_date || '', status: staff.status || 'active',
  })
  photoFile.value = null
  showModal.value = true
}

function handleFileChange(e) {
  photoFile.value = e.target.files[0] || null
}

async function handleSubmit() {
  const fd = new FormData()
  Object.entries(form).forEach(([k, v]) => { if (v !== '') fd.append(k, v) })
  if (photoFile.value) fd.append('photo', photoFile.value)

  try {
    if (editingId.value) {
      fd.append('_method', 'PUT')
      await http.post(`/staff/${editingId.value}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      toast.success('Staff updated successfully.')
    } else {
      await http.post('/staff', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      toast.success('Staff member created.')
    }
    showModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not save staff member.')
  }
}

async function confirmDelete() {
  await destroy(deletingId.value)
  deletingId.value = null
}

onMounted(fetchAll)
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-5xl mx-auto space-y-6">
      <PageHeader title="Staff Directory" subtitle="Personnel who can be assigned assets" buttonText="New Staff" @action="openCreate" />

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-gray-400 font-semibold bg-gray-50/70 border-b border-gray-100">
              <th class="p-4 pl-5">Name</th>
              <th class="p-4">Position</th>
              <th class="p-4">Phone</th>
              <th class="p-4">Status</th>
              <th class="p-4 pr-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="s in staffList" :key="s.id" class="hover:bg-gray-50/50">
              <td class="p-4 pl-5 font-medium text-ink flex items-center gap-3">
                <img v-if="s.photo_path_url" :src="s.photo_path_url" class="w-8 h-8 rounded-full object-cover" alt="" />
                <span v-else class="w-8 h-8 rounded-full bg-gray-100 flex-shrink-0"></span>
                {{ s.full_name }}
              </td>
              <td class="p-4 text-gray-500">{{ s.position || '—' }}</td>
              <td class="p-4 text-gray-500">{{ s.phone || '—' }}</td>
              <td class="p-4">
                <span class="px-2 py-0.5 rounded-lg text-xs font-bold" :class="s.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600'">{{ s.status }}</span>
              </td>
              <td class="p-4 pr-5 text-right">
                <button @click="openEdit(s)" class="text-brand hover:underline mr-3 text-sm font-semibold">Edit</button>
                <button @click="deletingId = s.id" class="text-red-500 hover:underline text-sm font-semibold">Delete</button>
              </td>
            </tr>
            <tr v-if="!loading && !staffList.length">
              <td colspan="5" class="p-8 text-center text-gray-400">No staff members yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showModal" :title="editingId ? 'Edit Staff' : 'New Staff'" @close="showModal = false">
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Full Name *</label>
          <input v-model="form.full_name" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Email</label>
            <input v-model="form.email" type="email" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Phone</label>
            <input v-model="form.phone" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Position</label>
            <input v-model="form.position" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Hire Date</label>
            <input v-model="form.hire_date" type="date" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
        </div>
        <div v-if="editingId" class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Status *</label>
          <select v-model="form.status" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Photo</label>
          <input type="file" accept="image/jpeg,image/png" @change="handleFileChange" class="w-full text-sm" />
        </div>
        <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl w-full transition">
          {{ editingId ? 'Save Changes' : 'Add Staff' }}
        </button>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
