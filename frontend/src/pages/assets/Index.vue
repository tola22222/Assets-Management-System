<script setup>
import { ref, onMounted, reactive } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import { useApiCrud } from '../../composables/useApiCrud'
import { useToastStore } from '../../stores/toast'

const { items: assetsList, loading, fetchAll, destroy } = useApiCrud('/assets', { entityName: 'Asset' })
const toast = useToastStore()

const categories = ref([])
const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const imageFile = ref(null)
const submitting = ref(false)

const emptyForm = () => ({
  name: '', category_id: '', description: '', model: '', brand: '',
  serial_number: '', purchase_date: '', purchase_price: '', condition: 'good', status: 'active',
})
const form = reactive(emptyForm())

async function loadCategories() {
  const { data } = await http.get('/categories')
  categories.value = data
}

function openCreate() {
  editingId.value = null
  Object.assign(form, emptyForm())
  imageFile.value = null
  showModal.value = true
}

function openEdit(asset) {
  editingId.value = asset.id
  Object.assign(form, {
    name: asset.name, category_id: asset.category_id, description: asset.description || '',
    model: asset.model || '', brand: asset.brand || '', serial_number: asset.serial_number || '',
    purchase_date: asset.purchase_date || '', purchase_price: asset.purchase_price || '',
    condition: asset.condition, status: asset.status,
  })
  imageFile.value = null
  showModal.value = true
}

function handleFileChange(e) {
  imageFile.value = e.target.files[0] || null
}

function buildFormData() {
  const fd = new FormData()
  Object.entries(form).forEach(([key, value]) => {
    if (value !== null && value !== '') fd.append(key, value)
  })
  if (imageFile.value) fd.append('image', imageFile.value)
  return fd
}

async function handleSubmit() {
  submitting.value = true
  try {
    const fd = buildFormData()
    const config = { headers: { 'Content-Type': 'multipart/form-data' } }
    if (editingId.value) {
      fd.append('_method', 'PUT')
      await http.post(`/assets/${editingId.value}`, fd, config)
      toast.success('Asset updated successfully.')
    } else {
      await http.post('/assets', fd, config)
      toast.success('Asset registered successfully.')
    }
    showModal.value = false
    await fetchAll()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not save asset.')
  } finally {
    submitting.value = false
  }
}

async function confirmDelete() {
  await destroy(deletingId.value)
  deletingId.value = null
}

async function regenerateQr(asset) {
  await http.post(`/assets/${asset.id}/regenerate-qr`)
  toast.success('QR code regenerated.')
  await fetchAll()
}

onMounted(() => {
  fetchAll()
  loadCategories()
})
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-6xl mx-auto space-y-6">
      <PageHeader title="Asset Register" subtitle="Manage and track your organization's assets" buttonText="Register Asset" @action="openCreate" />

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-gray-400 font-semibold bg-gray-50/70 border-b border-gray-100">
              <th class="p-4 pl-5">Asset</th>
              <th class="p-4">Code</th>
              <th class="p-4">Category</th>
              <th class="p-4">Condition</th>
              <th class="p-4">Status</th>
              <th class="p-4">QR</th>
              <th class="p-4 pr-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="asset in assetsList" :key="asset.id" class="hover:bg-gray-50/50">
              <td class="p-4 pl-5 font-medium text-ink flex items-center gap-3">
                <img v-if="asset.image_url" :src="asset.image_url" class="w-8 h-8 rounded-lg object-cover border border-gray-200" alt="" />
                <span class="w-8 h-8 rounded-lg bg-gray-100 flex-shrink-0" v-else></span>
                {{ asset.name }}
              </td>
              <td class="p-4 text-gray-500">{{ asset.asset_code }}</td>
              <td class="p-4 text-gray-500">{{ asset.category?.name || '—' }}</td>
              <td class="p-4 text-gray-500 capitalize">{{ asset.condition }}</td>
              <td class="p-4">
                <span class="px-2 py-0.5 rounded-lg text-xs font-bold" :class="asset.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600'">
                  {{ asset.status }}
                </span>
              </td>
              <td class="p-4">
                <a v-if="asset.qr_code_url" :href="asset.qr_code_url" target="_blank" class="text-brand hover:underline text-xs font-semibold">View</a>
              </td>
              <td class="p-4 pr-5 text-right whitespace-nowrap">
                <button @click="openEdit(asset)" class="text-brand hover:underline mr-3 text-sm font-semibold">Edit</button>
                <button @click="regenerateQr(asset)" class="text-gray-500 hover:underline mr-3 text-sm font-semibold">Regen QR</button>
                <button @click="deletingId = asset.id" class="text-red-500 hover:underline text-sm font-semibold">Delete</button>
              </td>
            </tr>
            <tr v-if="!loading && !assetsList.length">
              <td colspan="7" class="p-8 text-center text-gray-400">No assets registered yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showModal" :title="editingId ? 'Edit Asset' : 'Register Asset'" wide @close="showModal = false">
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Name *</label>
            <input v-model="form.name" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Category *</label>
            <select v-model="form.category_id" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
              <option value="">Select Category</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Model</label>
            <input v-model="form.model" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Brand</label>
            <input v-model="form.brand" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Serial Number</label>
            <input v-model="form.serial_number" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Purchase Date</label>
            <input v-model="form.purchase_date" type="date" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Purchase Price</label>
            <input v-model="form.purchase_price" type="number" step="0.01" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Condition</label>
            <select v-model="form.condition" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
              <option value="good">Good</option>
              <option value="fair">Fair</option>
              <option value="broken">Broken</option>
              <option value="lost">Lost</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-700 tracking-wide">Status</label>
            <select v-model="form.status" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
              <option value="active">Active</option>
              <option value="disposed">Disposed</option>
            </select>
          </div>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Description</label>
          <textarea v-model="form.description" rows="2" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand"></textarea>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-gray-700 tracking-wide">Photo</label>
          <input type="file" accept="image/jpeg,image/png" @change="handleFileChange" class="w-full text-sm" />
        </div>
        <button type="submit" :disabled="submitting" class="bg-brand hover:bg-brand-dark disabled:opacity-60 text-white font-semibold text-sm px-6 py-2.5 rounded-xl w-full transition">
          {{ submitting ? 'Saving…' : (editingId ? 'Save Changes' : 'Register Asset') }}
        </button>
      </form>
    </Modal>

    <ConfirmDialog v-if="deletingId" @confirm="confirmDelete" @cancel="deletingId = null" />
  </AppLayout>
</template>
