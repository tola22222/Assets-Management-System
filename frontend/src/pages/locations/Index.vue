<script setup>
import { ref, onMounted, reactive } from 'vue'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useToastStore } from '../../stores/toast'

const { items: locations, loading, fetchAll, create, update, destroy } = useApiCrud('/locations', { entityName: 'Location' })
const toast = useToastStore()

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const form = reactive({ name: '', type: 'office', description: '' })

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', type: 'office', description: '' })
  showModal.value = true
}

function openEdit(location) {
  editingId.value = location.id
  Object.assign(form, { name: location.name, type: location.type, description: location.description || '' })
  showModal.value = true
}

async function handleSubmit() {
  if (editingId.value) {
    await update(editingId.value, form)
  } else {
    await create(form)
  }
  showModal.value = false
}

async function confirmDelete() {
  try {
    await destroy(deletingId.value)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not delete location.')
  } finally {
    deletingId.value = null
  }
}

onMounted(fetchAll)
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-5xl mx-auto space-y-6">
      <PageHeader title="Locations" subtitle="Offices, labs and program sites" buttonText="New Location" @action="openCreate" />

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-gray-400 font-semibold bg-gray-50/70 border-b border-gray-100">
              <th class="p-4 pl-5">Name</th>
              <th class="p-4">Type</th>
              <th class="p-4">Stock Records</th>
              <th class="p-4 pr-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="loc in locations" :key="loc.id" class="hover:bg-gray-50/50">
              <td class="p-4 pl-5 font-medium text-ink">{{ loc.name }}</td>
              <td class="p-4 text-gray-500 capitalize">{{ loc.type }}</td>
              <td class="p-4 text-gray-500">{{ loc.asset_stocks_count ?? 0 }}</td>
              <td class="p-4 pr-5 text-right">
                <button @click="openEdit(loc)" class="text-brand hover:underline mr-3 text-sm font-semibold">Edit</button>
                <button @click="deletingId = loc.id" class="text-red-500 hover:underline text-sm font-semibold">Delete</button>
              </td>
            </tr>
            <tr v-if="!loading && !locations.length">
              <td colspan="4" class="p-8 text-center text-gray-400">No locations yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showModal" :title="editingId ? 'Edit Location' : 'New Location'" @close="showModal = false">
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Name *</label>
          <input v-model="form.name" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Type *</label>
          <select v-model="form.type" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
            <option value="office">Office</option>
            <option value="lab">Lab</option>
            <option value="program">Program</option>
          </select>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Description</label>
          <textarea v-model="form.description" rows="2" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand"></textarea>
        </div>
        <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl w-full transition">
          {{ editingId ? 'Save Changes' : 'Create Location' }}
        </button>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
