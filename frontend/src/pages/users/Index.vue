<script setup>
import { ref, onMounted, reactive } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useToastStore } from '../../stores/toast'

const { items: users, loading, fetchAll, create, update, destroy } = useApiCrud('/users', { entityName: 'User' })
const toast = useToastStore()

const staffList = ref([])
const showModal = ref(null)
const editingId = ref(null)
const deletingId = ref(null)
const resettingId = ref(null)
const newPassword = ref('')
const newPasswordConfirm = ref('')

const roleLabels = { admin: 'Admin', staff: 'Staff', executive_director: 'Executive Director', finance_manager: 'Finance Manager' }
const roleColors = {
  admin: 'bg-purple-100 text-purple-700',
  executive_director: 'bg-amber-100 text-amber-700',
  finance_manager: 'bg-teal-100 text-teal-700',
  staff: 'bg-blue-100 text-blue-700',
}

const emptyForm = () => ({ name: '', email: '', password: '', role: 'staff', staff_id: '' })
const form = reactive(emptyForm())

async function loadStaff() {
  const { data } = await http.get('/staff')
  staffList.value = data
}

function openCreate() {
  editingId.value = null
  Object.assign(form, emptyForm())
  showModal.value = true
}

function openEdit(user) {
  editingId.value = user.id
  Object.assign(form, { name: user.name, email: user.email, password: '', role: user.role, staff_id: user.staff_id || '' })
  showModal.value = true
}

async function handleSubmit() {
  try {
    if (editingId.value) {
      const { password, ...rest } = form
      await update(editingId.value, rest)
    } else {
      await create(form)
    }
    showModal.value = false
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not save user.')
  }
}

async function toggleLock(user) {
  await http.post(`/users/${user.id}/lock`)
  toast.success(user.is_locked ? 'User unlocked.' : 'User locked.')
  await fetchAll()
}

async function submitPasswordReset() {
  try {
    await http.post(`/users/${resettingId.value}/reset-password`, {
      password: newPassword.value,
      password_confirmation: newPasswordConfirm.value,
    })
    toast.success('Password reset successfully.')
    resettingId.value = null
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not reset password.')
  }
}

async function confirmDelete() {
  try {
    await destroy(deletingId.value)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not delete user.')
  } finally {
    deletingId.value = null
  }
}

onMounted(() => {
  fetchAll()
  loadStaff()
})
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-5xl mx-auto space-y-6">
      <PageHeader title="User Management" subtitle="Accounts that can sign in to the system" buttonText="New User" @action="openCreate" />

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-gray-400 font-semibold bg-gray-50/70 border-b border-gray-100">
              <th class="p-4 pl-5">Name</th>
              <th class="p-4">Email</th>
              <th class="p-4">Role</th>
              <th class="p-4">Status</th>
              <th class="p-4 pr-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="u in users" :key="u.id" class="hover:bg-gray-50/50">
              <td class="p-4 pl-5 font-medium text-ink">{{ u.name }}</td>
              <td class="p-4 text-gray-500">{{ u.email }}</td>
              <td class="p-4"><span class="px-2.5 py-1 rounded-lg text-xs font-semibold" :class="roleColors[u.role]">{{ roleLabels[u.role] || u.role }}</span></td>
              <td class="p-4">
                <span class="px-2 py-0.5 rounded-lg text-xs font-bold" :class="u.is_locked ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'">
                  {{ u.is_locked ? 'LOCKED' : 'ACTIVE' }}
                </span>
              </td>
              <td class="p-4 pr-5 text-right whitespace-nowrap">
                <button @click="openEdit(u)" class="text-brand hover:underline mr-3 text-sm font-semibold">Edit</button>
                <button @click="toggleLock(u)" class="text-gray-500 hover:underline mr-3 text-sm font-semibold">{{ u.is_locked ? 'Unlock' : 'Lock' }}</button>
                <button @click="resettingId = u.id; newPassword = ''; newPasswordConfirm = ''" class="text-gray-500 hover:underline mr-3 text-sm font-semibold">Reset Password</button>
                <button @click="deletingId = u.id" class="text-red-500 hover:underline text-sm font-semibold">Delete</button>
              </td>
            </tr>
            <tr v-if="!loading && !users.length">
              <td colspan="5" class="p-8 text-center text-gray-400">No users yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showModal" :title="editingId ? 'Edit User' : 'New User'" @close="showModal = false">
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Name *</label>
          <input v-model="form.name" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Email *</label>
          <input v-model="form.email" type="email" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <div v-if="!editingId" class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Password *</label>
          <input v-model="form.password" type="password" minlength="8" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Role *</label>
            <select v-model="form.role" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
              <option value="staff">Staff</option>
              <option value="admin">Admin</option>
              <option value="executive_director">Executive Director</option>
              <option value="finance_manager">Finance Manager</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Link to Staff</label>
            <select v-model="form.staff_id" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
              <option value="">None</option>
              <option v-for="s in staffList" :key="s.id" :value="s.id">{{ s.full_name }}</option>
            </select>
          </div>
        </div>
        <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl w-full transition">
          {{ editingId ? 'Save Changes' : 'Create User' }}
        </button>
      </form>
    </Modal>

    <Modal v-if="resettingId" title="Reset Password" @close="resettingId = null">
      <form @submit.prevent="submitPasswordReset" class="p-6 space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">New Password *</label>
          <input v-model="newPassword" type="password" minlength="8" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Confirm Password *</label>
          <input v-model="newPasswordConfirm" type="password" minlength="8" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl w-full transition">Reset Password</button>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
