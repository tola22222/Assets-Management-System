<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const toast = useToastStore()

const file = ref(null)
const fileInput = ref(null)
const generateQr = ref(true)
const importing = ref(false)
const dragging = ref(false)
const result = ref(null)

// Optional bulk photo attach: each image is matched server-side to a row by
// filename (asset code or serial number), so no extra spreadsheet column is needed.
const images = ref([])
const imagesInput = ref(null)
const imagesDragging = ref(false)

// The page is one wizard: pick a file, optionally add photos, read the result.
// The stepper is not a gate — every step behind the furthest reached one stays
// clickable, so nothing is harder to get to than it was on the single screen.
const steps = [
  { id: 'file', label: 'import.step_file' },
  { id: 'photos', label: 'import.step_photos' },
  { id: 'complete', label: 'import.step_complete' },
]
const current = ref(0)

// Complete only opens once there is something to show there.
const maxStep = computed(() => (result.value ? 2 : 1))
function goTo(i) {
  if (i <= maxStep.value) current.value = i
}

function pick(e) {
  file.value = e.target.files[0] || null
  result.value = null
}
function onDrop(e) {
  dragging.value = false
  file.value = e.dataTransfer.files[0] || null
  result.value = null
}
function openFileDialog() {
  fileInput.value?.click()
}

function pickImages(e) {
  images.value = [...images.value, ...Array.from(e.target.files || [])]
  e.target.value = ''
  result.value = null
}
function onDropImages(e) {
  imagesDragging.value = false
  images.value = [...images.value, ...Array.from(e.dataTransfer.files || []).filter((f) => f.type.startsWith('image/'))]
  result.value = null
}
function openImagesDialog() {
  imagesInput.value?.click()
}
function removeImage(index) {
  images.value = images.value.filter((_, i) => i !== index)
}
function clearImages() {
  images.value = []
}

async function submit() {
  if (!file.value) return
  importing.value = true
  result.value = null
  try {
    const fd = new FormData()
    fd.append('file', file.value)
    fd.append('generate_qr', generateQr.value ? '1' : '0')
    images.value.forEach((img) => fd.append('images[]', img))
    const { data } = await http.post('/assets/import', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    result.value = data
    current.value = 2
    toast.success(t('import.success_message', { created: data.created, updated: data.updated }))
  } catch (e) {
    toast.error(errorMessage(e, t('import.failed')))
  } finally {
    importing.value = false
  }
}

async function downloadTemplate() {
  try {
    const { data } = await http.get('/assets/import/template', { responseType: 'blob' })
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = 'asset_import_template.csv'
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    toast.error(errorMessage(e, t('import.template_failed')))
  }
}

function reset() {
  file.value = null
  images.value = []
  result.value = null
  current.value = 0
}
</script>

<template>
  <AppLayout>
    <!-- Two-tier width from the reference: the stepper spans the wider
         container, the form sits in a narrow centred column inside it. -->
    <div class="p-6 sm:p-8 max-w-4xl mx-auto">
      <!-- Stepper. Scrolls sideways rather than wrapping — a wrapped second row
           reads as a separate bar instead of one track. -->
      <nav class="card rounded-full px-4 py-2 mb-8 max-w-[480px] mx-auto flex items-center justify-between gap-3 overflow-x-auto">
        <button
          v-for="(s, i) in steps"
          :key="s.id"
          type="button"
          @click="goTo(i)"
          :disabled="i > maxStep"
          class="flex items-center gap-1.5 text-[13px] font-semibold whitespace-nowrap transition disabled:cursor-not-allowed"
          :class="i < current ? 'text-emerald-600 dark:text-emerald-400'
            : i === current ? 'text-brand-700 dark:text-brand-300'
            : 'text-faint'"
        >
          <span
            class="w-4 h-4 rounded-full inline-flex items-center justify-center flex-shrink-0"
            :class="i < current ? 'bg-emerald-500 text-white'
              : i === current ? 'bg-brand text-white'
              : 'border-[1.5px] border-line'"
          >
            <svg v-if="i <= current" class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
          </span>
          {{ t(s.label) }}
        </button>
      </nav>

      <!-- 480px centred column, as in the reference. -->
      <div class="max-w-[480px] mx-auto">

        <!-- ── Step 1 · File ──────────────────────────────────────── -->
        <template v-if="current === 0">
          <div
            class="border-[1.5px] border-dashed rounded-xl bg-surface-2 p-6 text-center transition-colors"
            :class="dragging ? 'border-brand' : 'border-line hover:border-brand/50'"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
          >
            <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv,.txt" class="hidden" @change="pick" />
            <p class="text-xs text-faint italic mb-3">{{ t('import.drop_hint') }}<br />{{ t('import.file_hint') }}</p>
            <button type="button" @click="openFileDialog" class="btn-primary btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
              {{ file ? t('import.choose_different_file') : t('import.choose_file') }}
            </button>
          </div>

          <!-- Chosen file, shown as a field with a clear affordance on the right. -->
          <div v-if="file" class="mt-6">
            <label class="block text-xs font-medium text-muted mb-1.5">{{ t('import.selected_file') }}</label>
            <div class="relative">
              <div class="input pr-10 truncate">{{ file.name }}</div>
              <button
                type="button"
                @click="reset"
                :title="t('import.clear')"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-faint hover:text-red-500 transition"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
            <p class="text-xs text-faint mt-1.5">{{ t('import.file_size_kb', { size: (file.size / 1024).toFixed(0) }) }}</p>
          </div>

          <label class="flex flex-wrap items-center gap-2 mt-5 cursor-pointer">
            <input type="checkbox" v-model="generateQr" class="w-4 h-4 rounded border-line text-brand focus:ring-brand" />
            <span class="text-[13px] font-semibold text-brand-700 dark:text-brand-300">{{ t('import.generate_qr') }}</span>
            <span class="text-xs text-faint">{{ t('import.generate_qr_hint') }}</span>
          </label>

          <div class="mt-6 rounded-xl border border-line p-4">
            <h3 class="text-xs font-semibold text-muted tracking-wide uppercase mb-2">{{ t('import.how_it_works') }}</h3>
            <ul class="text-sm text-muted space-y-1">
              <li class="flex gap-2"><span class="text-brand-600 dark:text-brand-300">•</span> {{ t('import.step1') }}</li>
              <li class="flex gap-2"><span class="text-brand-600 dark:text-brand-300">•</span> {{ t('import.step2') }}</li>
              <li class="flex gap-2"><span class="text-brand-600 dark:text-brand-300">•</span> {{ t('import.step_location') }}</li>
              <li class="flex gap-2"><span class="text-brand-600 dark:text-brand-300">•</span> {{ t('import.step3') }}</li>
              <li class="flex gap-2"><span class="text-brand-600 dark:text-brand-300">•</span> {{ t('import.step4') }}</li>
            </ul>
          </div>
        </template>

        <!-- ── Step 2 · Photos ────────────────────────────────────── -->
        <template v-if="current === 1">
          <p class="text-sm text-muted mb-4">{{ t('import.images_hint') }}</p>

          <div
            class="border-[1.5px] border-dashed rounded-xl bg-surface-2 p-6 text-center transition-colors"
            :class="imagesDragging ? 'border-brand' : 'border-line hover:border-brand/50'"
            @dragover.prevent="imagesDragging = true"
            @dragleave.prevent="imagesDragging = false"
            @drop.prevent="onDropImages"
          >
            <input ref="imagesInput" type="file" accept="image/jpeg,image/png" multiple class="hidden" @change="pickImages" />
            <p class="text-xs text-faint italic mb-3">{{ t('import.images_drop_hint') }}<br />{{ t('import.images_file_hint') }}</p>
            <button type="button" @click="openImagesDialog" class="btn-primary btn-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 4.5h18M3.75 4.5v15A2.25 2.25 0 006 21.75h12a2.25 2.25 0 002.25-2.25v-15" /></svg>
              {{ t('import.choose_images') }}
            </button>
          </div>

          <div v-if="images.length" class="mt-5">
            <label class="block text-xs font-medium text-muted mb-1.5">{{ t('import.images_selected', { count: images.length }) }}</label>
            <div class="max-h-48 overflow-y-auto rounded-xl border border-line divide-y divide-line">
              <div v-for="(img, i) in images" :key="i" class="flex items-center justify-between gap-2 px-3 py-1.5 text-xs text-muted">
                <span class="truncate">{{ img.name }}</span>
                <button type="button" @click="removeImage(i)" class="text-faint hover:text-red-500 flex-shrink-0" :title="t('common.delete')">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>
            </div>
            <button type="button" @click="clearImages" class="btn-ghost btn-sm mt-2">{{ t('import.clear_images') }}</button>
          </div>

          <p v-if="importing && generateQr" class="text-xs text-faint mt-4">{{ t('import.qr_wait_hint') }}</p>
        </template>

        <!-- ── Step 3 · Complete ──────────────────────────────────── -->
        <template v-if="current === 2 && result">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-500/10 p-3 text-center">
              <p class="font-display text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ result.created }}</p>
              <p class="text-xs text-muted mt-0.5">{{ t('import.added') }}</p>
            </div>
            <div class="rounded-xl bg-blue-50 dark:bg-blue-500/10 p-3 text-center">
              <p class="font-display text-2xl font-bold text-blue-700 dark:text-blue-300">{{ result.updated }}</p>
              <p class="text-xs text-muted mt-0.5">{{ t('import.updated') }}</p>
            </div>
            <div class="rounded-xl bg-surface-2 p-3 text-center">
              <p class="font-display text-2xl font-bold text-fg">{{ result.skipped }}</p>
              <p class="text-xs text-muted mt-0.5">{{ t('import.skipped') }}</p>
            </div>
            <div class="rounded-xl p-3 text-center" :class="result.errors.length ? 'bg-red-50 dark:bg-red-500/10' : 'bg-surface-2'">
              <p class="font-display text-2xl font-bold" :class="result.errors.length ? 'text-red-700 dark:text-red-300' : 'text-fg'">{{ result.errors.length }}</p>
              <p class="text-xs text-muted mt-0.5">{{ t('import.errors') }}</p>
            </div>
          </div>

          <p v-if="result.images_attached" class="text-sm text-muted mt-4">
            {{ t('import.images_attached_message', { count: result.images_attached }) }}
          </p>

          <div v-if="result.images_unmatched?.length" class="mt-4">
            <label class="block text-xs font-medium text-muted mb-1.5">{{ t('import.images_unmatched') }}</label>
            <div class="max-h-48 overflow-y-auto rounded-xl border border-line divide-y divide-line">
              <p v-for="(name, i) in result.images_unmatched" :key="i" class="px-3 py-2 text-xs text-muted truncate">{{ name }}</p>
            </div>
          </div>

          <div v-if="result.errors.length" class="mt-4">
            <label class="block text-xs font-medium text-muted mb-1.5">{{ t('import.failed_rows') }}</label>
            <div class="max-h-48 overflow-y-auto rounded-xl border border-line divide-y divide-line">
              <p v-for="(err, i) in result.errors" :key="i" class="px-3 py-2 text-xs text-muted">{{ err }}</p>
            </div>
          </div>
        </template>

        <!-- Action bar: a text action on the left, step navigation on the right.
             Below sm the two groups stack and every button goes full width, so
             they stay comfortably tappable instead of shrinking to fit a row. -->
        <div class="mt-9 pt-5 border-t border-line flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
          <button v-if="current !== 2" type="button" @click="downloadTemplate" class="btn-subtle btn-sm w-full sm:w-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            {{ t('import.download_template') }}
          </button>
          <span v-else class="hidden sm:block"></span>

          <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full sm:w-auto">
            <button v-if="current === 1" type="button" @click="goTo(0)" class="btn-ghost w-full sm:w-auto">{{ t('common.back') }}</button>

            <button v-if="current === 0" type="button" @click="goTo(1)" :disabled="!file" class="btn-primary w-full sm:w-auto disabled:opacity-50">
              {{ t('import.next') }}
            </button>

            <button v-if="current === 1" type="button" @click="submit" :disabled="!file || importing" class="btn-primary w-full sm:w-auto disabled:opacity-50">
              <svg v-if="importing" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
              {{ importing ? t('import.importing') : t('import.import_assets') }}
            </button>

            <template v-if="current === 2">
              <button type="button" @click="reset" class="btn-ghost w-full sm:w-auto">{{ t('import.import_another') }}</button>
              <RouterLink to="/assets" class="btn-primary w-full sm:w-auto">{{ t('import.view_register') }}</RouterLink>
            </template>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
