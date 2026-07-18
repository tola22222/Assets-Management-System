<script setup>
import { ref, onMounted, reactive } from 'vue'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import { useApiCrud } from '../../composables/useApiCrud'

const { items: suppliers, loading, fetchAll, create, update, destroy } = useApiCrud('/suppliers', { entityName: 'Supplier' })

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const form = reactive({ name: '', phone: '', address: '' })

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', phone: '', address: '' })
  showModal.value = true
}

function openEdit(supplier) {
  editingId.value = supplier.id
  Object.assign(form, { name: supplier.name, phone: supplier.phone || '', address: supplier.address || '' })
  showModal.value = true
}

async function handleSubmit() {
  if (editingId.value) await update(editingId.value, form)
  else await create(form)
  showModal.value = false
}

async function confirmDelete() {
  await destroy(deletingId.value)
  deletingId.value = null
}

onMounted(fetchAll)
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-4xl mx-auto space-y-6">
      <PageHeader title="Suppliers" subtitle="Vendors assets are purchased from" buttonText="New Supplier" @action="openCreate" />

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-gray-400 font-semibold bg-gray-50/70 border-b border-gray-100">
              <th class="p-4 pl-5">Name</th>
              <th class="p-4">Phone</th>
              <th class="p-4">Address</th>
              <th class="p-4 pr-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="s in suppliers" :key="s.id" class="hover:bg-gray-50/50">
              <td class="p-4 pl-5 font-medium text-ink">{{ s.name }}</td>
              <td class="p-4 text-gray-500">{{ s.phone || '—' }}</td>
              <td class="p-4 text-gray-500">{{ s.address || '—' }}</td>
              <td class="p-4 pr-5 text-right">
                <button @click="openEdit(s)" class="text-brand hover:underline mr-3 text-sm font-semibold">Edit</button>
                <button @click="deletingId = s.id" class="text-red-500 hover:underline text-sm font-semibold">Delete</button>
              </td>
            </tr>
            <tr v-if="!loading && !suppliers.length">
              <td colspan="4" class="p-8 text-center text-gray-400">No suppliers yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showModal" :title="editingId ? 'Edit Supplier' : 'New Supplier'" @close="showModal = false">
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Name *</label>
          <input v-model="form.name" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Phone</label>
          <input v-model="form.phone" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Address</label>
          <textarea v-model="form.address" rows="2" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand"></textarea>
        </div>
        <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl w-full transition">
          {{ editingId ? 'Save Changes' : 'Create Supplier' }}
        </button>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
