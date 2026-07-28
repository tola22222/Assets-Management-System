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
        <h1 class="text-xl font-bold text-fg tracking-tight">QR Scanner</h1>
        <p class="text-muted text-sm mt-0.5">Enter or scan an asset code to look it up</p>
      </div>

      <form v-if="!asset" @submit.prevent="handleScan" class="bg-surface rounded-2xl border border-line p-6 flex gap-3">
        <input v-model="assetCode" placeholder="Enter asset code, e.g. IT-2026-000001" autofocus
          class="flex-1 border border-line rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand" />
        <button type="submit" :disabled="loading" class="btn-primary">
          {{ loading ? 'Searching…' : 'Look Up' }}
        </button>
      </form>

      <div v-else class="space-y-6">
        <div class="bg-surface rounded-2xl border border-line p-6">
          <div class="flex items-start justify-between">
            <div>
              <h2 class="font-bold text-fg text-lg">{{ asset.name }}</h2>
              <p class="text-muted text-sm">{{ asset.asset_code }} — {{ asset.category?.name || 'Uncategorized' }}</p>
            </div>
            <button @click="reset" class="text-sm text-faint hover:text-muted">Scan Another</button>
          </div>
          <div class="grid grid-cols-2 gap-4 mt-4 text-sm">
            <div><span class="text-faint">Condition:</span> <span class="capitalize font-semibold text-fg">{{ asset.condition }}</span></div>
            <div><span class="text-faint">Status:</span> <span class="capitalize font-semibold text-fg">{{ asset.status }}</span></div>
          </div>
        </div>

        <div class="bg-surface rounded-2xl border border-line p-6">
          <h3 class="font-bold text-fg mb-4">Record Verification</h3>
          <form @submit.prevent="submitVerification" class="space-y-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">Location *</label>
              <select v-model="verifyForm.location_id" required class="input">
                <option value="">Select Location</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">Condition *</label>
              <select v-model="verifyForm.condition" class="input">
                <option value="good">Good</option>
                <option value="fair">Fair</option>
                <option value="broken">Broken</option>
                <option value="lost">Lost</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">Remark</label>
              <textarea v-model="verifyForm.remark" rows="2" class="input"></textarea>
            </div>
            <button type="submit" class="btn-primary w-full">Confirm Verification</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
