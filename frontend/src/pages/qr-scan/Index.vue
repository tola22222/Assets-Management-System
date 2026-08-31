<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
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
    toast.error(e.response?.data?.message || t('qr_scan.not_found'))
    asset.value = null
  } finally {
    loading.value = false
  }
}

async function submitVerification() {
  try {
    await http.post(`/qr-scan/${asset.value.asset_code}/verify`, verifyForm.value)
    toast.success(t('qr_scan.verified'))
    const { data } = await http.get(`/qr-scan/${asset.value.asset_code}`)
    asset.value = data
  } catch (e) {
    toast.error(e.response?.data?.message || t('qr_scan.record_failed'))
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
        <h1 class="font-display text-xl font-bold text-fg tracking-tight">{{ t('qr_scan.title') }}</h1>
        <p class="text-muted text-sm mt-0.5">{{ t('qr_scan.subtitle') }}</p>
      </div>

      <form v-if="!asset" @submit.prevent="handleScan" class="card p-6 flex gap-3">
        <input v-model="assetCode" :placeholder="t('qr_scan.code_placeholder')" autofocus
          class="input flex-1" />
        <button type="submit" :disabled="loading" class="btn-primary">
          {{ loading ? t('qr_scan.searching') : t('qr_scan.look_up') }}
        </button>
      </form>

      <div v-else class="space-y-6">
        <div class="card p-6">
          <div class="flex items-start justify-between">
            <div>
              <h2 class="font-display font-bold text-fg text-lg">{{ asset.name }}</h2>
              <p class="text-muted text-sm">{{ asset.asset_code }} — {{ asset.category?.name || t('qr_scan.uncategorized') }}</p>
            </div>
            <button @click="reset" class="text-sm text-faint hover:text-muted">{{ t('qr_scan.scan_another') }}</button>
          </div>
          <div class="grid grid-cols-2 gap-4 mt-4 text-sm">
            <div><span class="text-faint">{{ t('qr_scan.condition_label') }}</span> <span class="capitalize font-semibold text-fg">{{ asset.condition }}</span></div>
            <div><span class="text-faint">{{ t('qr_scan.status_label') }}</span> <span class="capitalize font-semibold text-fg">{{ asset.status }}</span></div>
          </div>
        </div>

        <div class="card p-6">
          <h3 class="font-display font-bold text-fg mb-4">{{ t('qr_scan.record_verification') }}</h3>
          <form @submit.prevent="submitVerification" class="space-y-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('qr_scan.location_required') }}</label>
              <select v-model="verifyForm.location_id" required class="input">
                <option value="">{{ t('common.select_location') }}</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('qr_scan.condition_required') }}</label>
              <select v-model="verifyForm.condition" class="input">
                <option value="good">{{ t('qr_scan.condition_good') }}</option>
                <option value="fair">{{ t('qr_scan.condition_fair') }}</option>
                <option value="broken">{{ t('qr_scan.condition_broken') }}</option>
                <option value="lost">{{ t('qr_scan.condition_lost') }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('qr_scan.remark') }}</label>
              <textarea v-model="verifyForm.remark" rows="2" class="input"></textarea>
            </div>
            <button type="submit" class="btn-primary w-full">{{ t('qr_scan.confirm_verification') }}</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
