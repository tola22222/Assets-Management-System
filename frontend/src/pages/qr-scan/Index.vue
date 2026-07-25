<script setup>
import { ref } from 'vue'
import http from '../../api/http'
import AppPageHeader from '../../components/common/AppPageHeader.vue'
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
    const { data: locs } = await http.get('/locations', { params: { per_page: 100 } })
    locations.value = locs.data ?? locs
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
  <v-container fluid class="pa-0 d-flex flex-column ga-6" style="max-width: 640px">
      <AppPageHeader title="QR Scanner" subtitle="Enter or scan an asset code to look it up" />

      <v-form v-if="!asset" @submit.prevent="handleScan">
        <v-card rounded="lg" variant="flat" border class="pa-6 d-flex ga-3">
          <v-text-field v-model="assetCode" placeholder="Enter asset code, e.g. IT-2026-000001" autofocus hide-details />
          <v-btn type="submit" color="primary" variant="flat" :loading="loading">Look Up</v-btn>
        </v-card>
      </v-form>

      <div v-else class="d-flex flex-column ga-6">
        <v-card rounded="lg" variant="flat" border class="pa-6">
          <div class="d-flex align-start justify-space-between">
            <div>
              <h2 class="text-h6 font-weight-bold">{{ asset.name }}</h2>
              <p class="text-body-2 text-medium-emphasis">{{ asset.asset_code }} — {{ asset.category?.name || 'Uncategorized' }}</p>
            </div>
            <v-btn variant="text" size="small" @click="reset">Scan Another</v-btn>
          </div>
          <v-row dense class="mt-2 text-body-2">
            <v-col cols="6"><span class="text-medium-emphasis">Condition:</span> <span class="text-capitalize font-weight-semibold">{{ asset.condition }}</span></v-col>
            <v-col cols="6"><span class="text-medium-emphasis">Status:</span> <span class="text-capitalize font-weight-semibold">{{ asset.status }}</span></v-col>
          </v-row>
        </v-card>

        <v-card rounded="lg" variant="flat" border class="pa-6">
          <h3 class="text-subtitle-1 font-weight-bold mb-4">Record Verification</h3>
          <v-form @submit.prevent="submitVerification">
            <div class="d-flex flex-column ga-1">
              <v-select
                v-model="verifyForm.location_id"
                label="Location *"
                :items="locations.map((l) => ({ title: l.name, value: l.id }))"
                required
              />
              <v-select
                v-model="verifyForm.condition"
                label="Condition *"
                :items="[
                  { title: 'Good', value: 'good' },
                  { title: 'Fair', value: 'fair' },
                  { title: 'Broken', value: 'broken' },
                  { title: 'Lost', value: 'lost' },
                ]"
              />
              <v-textarea v-model="verifyForm.remark" label="Remark" rows="2" />
              <v-btn type="submit" color="primary" variant="flat" block>Confirm Verification</v-btn>
            </div>
          </v-form>
        </v-card>
      </div>
  </v-container>
</template>
