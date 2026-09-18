<script setup>
import { ref, computed, watch } from 'vue'
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
const scan = ref(null)
const canChangeLocation = ref(true)
const loading = ref(false)
const saving = ref(false)
const locations = ref([])
const verifyForm = ref({ location_id: '', condition: 'good', remark: '' })

const CONDITIONS = ['good', 'fair', 'broken', 'lost']

function resetForm() {
  verifyForm.value = {
    // Start from where the register says the asset is: leaving it alone
    // verifies that location, picking another one updates it.
    location_id: asset.value?.location_id ?? '',
    condition: CONDITIONS.includes(asset.value?.condition) ? asset.value.condition : 'good',
    remark: '',
  }
}

async function loadDetails() {
  const { data } = await http.get(`/qr-scan/${encodeURIComponent(asset.value.asset_code)}`)
  asset.value = data
}

async function lookUp(code) {
  if (!code) return
  loading.value = true
  try {
    // This POST is what records the scan against the signed-in account.
    const { data } = await http.post('/qr-scan', { asset_code: code })
    asset.value = data.asset
    scan.value = data.scan
    canChangeLocation.value = data.can_change_location
    const [{ data: locs }] = await Promise.all([http.get('/locations'), loadDetails()])
    locations.value = locs
    resetForm()
  } catch (e) {
    toast.error(errorMessage(e, t('qr_scan.not_found')))
    asset.value = null
    scan.value = null
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
      scan.value = null
      assetCode.value = ''
    }
  },
  { immediate: true },
)

const locationChanged = computed(() =>
  !!asset.value && verifyForm.value.location_id !== '' && Number(verifyForm.value.location_id) !== Number(asset.value.location_id),
)
const selectedLocationName = computed(() => locations.value.find((l) => l.id === Number(verifyForm.value.location_id))?.name || '')

async function submitVerification() {
  saving.value = true
  try {
    const { data } = await http.post(`/qr-scan/${encodeURIComponent(asset.value.asset_code)}/verify`, verifyForm.value)
    toast.success(data.location_changed ? t('qr_scan.location_updated') : t('qr_scan.verified'))
    await loadDetails()
    resetForm()
  } catch (e) {
    toast.error(errorMessage(e, t('qr_scan.record_failed')))
  } finally {
    saving.value = false
  }
}

function reset() {
  router.push({ name: 'qr-scan' })
}

function formatDateTime(raw) {
  const d = new Date(raw)
  return isNaN(d) ? '' : d.toLocaleString()
}

function scanSummary(s) {
  if (s.action === 'location_updated') {
    return t('qr_scan.activity_location_updated', {
      from: s.previous_location?.name || t('qr_scan.no_location'),
      to: s.location?.name || t('qr_scan.no_location'),
    })
  }
  if (s.action === 'verified') return t('qr_scan.activity_verified', { condition: t(`qr_scan.condition_${s.condition}`) })
  return t('qr_scan.activity_scanned')
}
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 max-w-2xl mx-auto space-y-6">
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
        <!-- Says out loud that the scan was logged, and under whose name. -->
        <div v-if="scan" class="flex items-start gap-2 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-sm px-3.5 py-3 rounded-xl">
          <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <span>{{ t('qr_scan.scan_recorded', { name: scan.user_name, time: formatDateTime(scan.created_at) }) }}</span>
        </div>

        <div class="card p-6">
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
              <h2 class="font-display font-bold text-fg text-lg">{{ asset.name }}</h2>
              <p class="text-muted text-sm"><span class="id-chip">{{ asset.asset_code }}</span> — {{ asset.category?.name || t('qr_scan.uncategorized') }}</p>
            </div>
            <button @click="reset" class="text-sm text-faint hover:text-muted flex-shrink-0">{{ t('qr_scan.scan_another') }}</button>
          </div>
          <div class="grid grid-cols-2 gap-4 mt-4 text-sm">
            <div><span class="text-faint">{{ t('qr_scan.condition_label') }}</span> <span class="capitalize font-semibold text-fg">{{ asset.condition }}</span></div>
            <div><span class="text-faint">{{ t('qr_scan.status_label') }}</span> <span class="capitalize font-semibold text-fg">{{ asset.status }}</span></div>
            <div class="col-span-2"><span class="text-faint">{{ t('qr_scan.location_label') }}</span> <span class="font-semibold text-fg">{{ asset.location?.name || t('qr_scan.no_location') }}</span></div>
            <div v-if="asset.brand || asset.model"><span class="text-faint">{{ t('qr_scan.model_label') }}</span> <span class="font-semibold text-fg">{{ [asset.brand, asset.model].filter(Boolean).join(' ') }}</span></div>
            <div v-if="asset.serial_number"><span class="text-faint">{{ t('qr_scan.serial_label') }}</span> <span class="font-semibold text-fg">{{ asset.serial_number }}</span></div>
          </div>
        </div>

        <div class="card p-6">
          <h3 class="font-display font-bold text-fg">{{ t('qr_scan.record_verification') }}</h3>
          <p class="text-sm text-muted mt-0.5 mb-4">{{ t('qr_scan.record_verification_hint') }}</p>
          <form @submit.prevent="submitVerification" class="space-y-4">
            <div class="form-group">
              <label class="label">{{ t('qr_scan.location_required') }}</label>
              <select v-model="verifyForm.location_id" required :disabled="!canChangeLocation" class="input">
                <option value="">{{ t('common.select_location') }}</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
              <p v-if="!canChangeLocation" class="text-xs text-faint mt-1.5">{{ t('qr_scan.location_locked_hint') }}</p>
              <p v-else-if="locationChanged" class="text-xs text-amber-600 dark:text-amber-400 mt-1.5">
                {{ t('qr_scan.location_change_warning', { from: asset.location?.name || t('qr_scan.no_location'), to: selectedLocationName }) }}
              </p>
            </div>
            <div class="form-group">
              <label class="label">{{ t('qr_scan.condition_required') }}</label>
              <select v-model="verifyForm.condition" class="input">
                <option v-for="c in CONDITIONS" :key="c" :value="c">{{ t(`qr_scan.condition_${c}`) }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="label">{{ t('qr_scan.remark') }}</label>
              <textarea v-model="verifyForm.remark" rows="2" class="textarea"></textarea>
            </div>
            <button type="submit" :disabled="saving" class="btn-primary w-full">
              {{ locationChanged ? t('qr_scan.confirm_with_location') : t('qr_scan.confirm_verification') }}
            </button>
          </form>
        </div>

        <div v-if="asset.scans?.length" class="card p-6">
          <h3 class="font-display font-bold text-fg mb-3">{{ t('qr_scan.recent_activity') }}</h3>
          <ul class="divide-y divide-line">
            <li v-for="s in asset.scans" :key="s.id" class="py-2.5 text-sm">
              <p class="text-fg"><span class="font-semibold">{{ s.user?.name || s.user_name || t('qr_scan.unknown_user') }}</span> — {{ scanSummary(s) }}</p>
              <p class="text-xs text-faint mt-0.5">{{ formatDateTime(s.created_at) }}</p>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
