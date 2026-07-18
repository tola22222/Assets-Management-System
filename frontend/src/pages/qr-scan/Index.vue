<script setup>
import { ref } from 'vue'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import { useToastStore } from '../../stores/toast'

const toast = useToastStore()
const assetCode = ref('')
const asset = ref(null)
const loading = ref(false)
const locations = ref([])
const verifyForm = ref({ location_id: '', condition: 'good', remark: '' })

async function handleScan() {
  if (!assetCode.value) return
  loading.value = true
  try {
    const { data } = await http.post('/qr-scan', { asset_code: assetCode.value })
    asset.value = data
    const { data: locs } = await http.get('/locations')
    locations.value = locs
    verifyForm.value = { location_id: '', condition: 'good', remark: '' }
  } catch (e) {
    toast.error(e.response?.data?.message || 'Asset not found.')
    asset.value = null
  } finally {
    loading.value = false
  }
}

async function submitVerification() {
  try {
    await http.post(`/qr-scan/${asset.value.asset_code}/verify`, verifyForm.value)
    toast.success('Asset verified successfully via QR scan.')
    const { data } = await http.get(`/qr-scan/${asset.value.asset_code}`)
    asset.value = data
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not record verification.')
  }
}

function reset() {
  asset.value = null
  assetCode.value = ''
}
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-2xl mx-auto space-y-6">
      <div>
        <h1 class="text-xl font-bold text-ink tracking-tight">QR Scanner</h1>
        <p class="text-gray-500 text-sm mt-0.5">Enter or scan an asset code to look it up</p>
      </div>

      <form v-if="!asset" @submit.prevent="handleScan" class="bg-white rounded-2xl border border-gray-200 p-6 flex gap-3">
        <input v-model="assetCode" placeholder="Enter asset code, e.g. IT-2026-000001" autofocus
          class="flex-1 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        <button type="submit" :disabled="loading" class="bg-brand hover:bg-brand-dark disabled:opacity-60 text-white font-semibold text-sm px-6 py-2.5 rounded-xl transition">
          {{ loading ? 'Searching…' : 'Look Up' }}
        </button>
      </form>

      <div v-else class="space-y-6">
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
          <div class="flex items-start justify-between">
            <div>
              <h2 class="font-bold text-ink text-lg">{{ asset.name }}</h2>
              <p class="text-gray-500 text-sm">{{ asset.asset_code }} — {{ asset.category?.name || 'Uncategorized' }}</p>
            </div>
            <button @click="reset" class="text-sm text-gray-400 hover:text-gray-600">Scan Another</button>
          </div>
          <div class="grid grid-cols-2 gap-4 mt-4 text-sm">
            <div><span class="text-gray-400">Condition:</span> <span class="capitalize font-semibold text-ink">{{ asset.condition }}</span></div>
            <div><span class="text-gray-400">Status:</span> <span class="capitalize font-semibold text-ink">{{ asset.status }}</span></div>
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6">
          <h3 class="font-bold text-ink mb-4">Record Verification</h3>
          <form @submit.prevent="submitVerification" class="space-y-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-700 tracking-wide">Location *</label>
              <select v-model="verifyForm.location_id" required class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
                <option value="">Select Location</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-700 tracking-wide">Condition *</label>
              <select v-model="verifyForm.condition" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand">
                <option value="good">Good</option>
                <option value="fair">Fair</option>
                <option value="broken">Broken</option>
                <option value="lost">Lost</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-700 tracking-wide">Remark</label>
              <textarea v-model="verifyForm.remark" rows="2" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand"></textarea>
            </div>
            <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-2.5 rounded-xl w-full transition">Confirm Verification</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
