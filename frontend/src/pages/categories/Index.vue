<script setup>
import { ref, onMounted, reactive } from 'vue'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import { useApiCrud } from '../../composables/useApiCrud'

const { items: categories, loading, fetchAll, create, update, destroy } = useApiCrud('/categories', { entityName: 'Category' })

const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const form = reactive({ name: '', short_name: '', description: '' })

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', short_name: '', description: '' })
  showModal.value = true
}

function openEdit(category) {
  editingId.value = category.id
  Object.assign(form, { name: category.name, short_name: category.short_name || '', description: category.description || '' })
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
  await destroy(deletingId.value)
  deletingId.value = null
}

onMounted(fetchAll)
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-5xl mx-auto space-y-6">
      <PageHeader title="Asset Categories" subtitle="Organize assets by category" buttonText="New Category" @action="openCreate" />

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-gray-400 font-semibold bg-gray-50/70 border-b border-gray-100">
              <th class="p-4 pl-5">Name</th>
              <th class="p-4">Short Name</th>
              <th class="p-4">Assets</th>
              <th class="p-4 pr-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="cat in categories" :key="cat.id" class="hover:bg-gray-50/50">
              <td class="p-4 pl-5 font-medium text-ink">{{ cat.name }}</td>
              <td class="p-4 text-gray-500">{{ cat.short_name || '—' }}</td>
              <td class="p-4 text-gray-500">{{ cat.assets_count ?? 0 }}</td>
              <td class="p-4 pr-5 text-right">
                <button @click="openEdit(cat)" class="text-brand hover:underline mr-3 text-sm font-semibold">Edit</button>
                <button @click="deletingId = cat.id" class="text-red-500 hover:underline text-sm font-semibold">Delete</button>
              </td>
            </tr>
            <tr v-if="!loading && !categories.length">
              <td colspan="4" class="p-8 text-center text-gray-400">No categories yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showModal" :title="editingId ? 'Edit Category' : 'New Category'" @close="showModal = false">
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Name *</label>
          <input v-model="form.name" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Short Name</label>
          <input v-model="form.short_name" maxlength="10" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Description</label>
          <textarea v-model="form.description" rows="2" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand"></textarea>
        </div>
        <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl w-full transition">
          {{ editingId ? 'Save Changes' : 'Create Category' }}
        </button>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
