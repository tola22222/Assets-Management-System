<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const toast = useToastStore()
const route = useRoute()
const router = useRouter()
const assetCode = ref('')
const asset = ref(null)
const loading = ref(false)
const locations = ref([])
const verifyForm = ref({ location_id: '', condition: 'good', remark: '' })

// Starts on the location the register already has: leaving it alone verifies
// that location, picking another one updates it.
function resetForm() {
  verifyForm.value = { location_id: asset.value?.location_id ?? '', condition: 'good', remark: '' }
}

async function lookUp(code) {
  loading.value = true
  try {
    // This POST is what records the scan against the signed-in account.
    const { data } = await http.post('/qr-scan', { asset_code: code })
    asset.value = data.asset
    const { data: locs } = await http.get('/locations')
    locations.value = locs
    resetForm()
  } catch (e) {
    toast.error(errorMessage(e, t('qr_scan.not_found')))
    asset.value = null
  } finally {
    loading.value = false
  }
}

// The code lives in the URL (/qr-scan/PEY-SR-FAF-0928) so a printed tag's link,
// a post-login redirect and a page refresh all land on the same asset.
function handleScan() {
  const code = assetCode.value.trim()
  if (!code) return
  if (route.params.code === code) lookUp(code)
  else router.push({ name: 'qr-scan', params: { code } })
}

watch(
  () => route.params.code,
  (code) => {
    if (code) {
      assetCode.value = code
      lookUp(code)
    } else {
      asset.value = null
      assetCode.value = ''
    }
  },
  { immediate: true },
)

async function submitVerification() {
  try {
    await http.post(`/qr-scan/${asset.value.asset_code}/verify`, verifyForm.value)
    toast.success(t('qr_scan.verified'))
    const { data } = await http.get(`/qr-scan/${asset.value.asset_code}`)
    asset.value = data
    resetForm()
  } catch (e) {
    toast.error(errorMessage(e, t('qr_scan.record_failed')))
  }
}

function reset() {
  router.push({ name: 'qr-scan' })
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
            <div class="form-group">
              <label class="label">{{ t('qr_scan.location_required') }}</label>
              <select v-model="verifyForm.location_id" required class="input">
                <option value="">{{ t('common.select_location') }}</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="label">{{ t('qr_scan.condition_required') }}</label>
              <select v-model="verifyForm.condition" class="input">
                <option value="good">{{ t('qr_scan.condition_good') }}</option>
                <option value="fair">{{ t('qr_scan.condition_fair') }}</option>
                <option value="broken">{{ t('qr_scan.condition_broken') }}</option>
                <option value="lost">{{ t('qr_scan.condition_lost') }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="label">{{ t('qr_scan.remark') }}</label>
              <textarea v-model="verifyForm.remark" rows="2" class="textarea"></textarea>
            </div>
            <button type="submit" class="btn-primary w-full">{{ t('qr_scan.confirm_verification') }}</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
