<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const toast = useToastStore()

const file = ref(null)
const generateQr = ref(true)
const importing = ref(false)
const dragging = ref(false)
const result = ref(null)

function pick(e) {
  file.value = e.target.files[0] || null
  result.value = null
}
function onDrop(e) {
  dragging.value = false
  file.value = e.dataTransfer.files[0] || null
  result.value = null
}

async function submit() {
  if (!file.value) return
  importing.value = true
  result.value = null
  try {
    const fd = new FormData()
    fd.append('file', file.value)
    fd.append('generate_qr', generateQr.value ? '1' : '0')
    const { data } = await http.post('/assets/import', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    result.value = data
    toast.success(t('import.success_message', { created: data.created, updated: data.updated }))
  } catch (e) {
    toast.error(e.response?.data?.message || t('import.failed'))
  } finally {
    importing.value = false
  }
}

async function downloadTemplate() {
  const { data } = await http.get('/assets/import/template', { responseType: 'blob' })
  const url = URL.createObjectURL(data)
  const a = document.createElement('a')
  a.href = url
  a.download = 'asset_import_template.csv'
  a.click()
  URL.revokeObjectURL(url)
}

function reset() {
  file.value = null
  result.value = null
}
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 max-w-3xl mx-auto space-y-6">
      <PageHeader :title="t('import.title')" :subtitle="t('import.subtitle')" />

      <RouterLink to="/assets" class="inline-flex items-center gap-1.5 text-sm font-semibold text-muted hover:text-fg">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
        {{ t('import.back') }}
      </RouterLink>

      <!-- How it works -->
      <div class="card p-5 space-y-3">
        <h3 class="font-bold text-fg">{{ t('import.how_it_works') }}</h3>
        <ul class="text-sm text-muted space-y-1.5">
          <li class="flex gap-2"><span class="text-brand-600 dark:text-brand-300">•</span> {{ t('import.step1') }}</li>
          <li class="flex gap-2"><span class="text-brand-600 dark:text-brand-300">•</span> {{ t('import.step2') }}</li>
          <li class="flex gap-2"><span class="text-brand-600 dark:text-brand-300">•</span> {{ t('import.step_location') }}</li>
          <li class="flex gap-2"><span class="text-brand-600 dark:text-brand-300">•</span> {{ t('import.step3') }}</li>
          <li class="flex gap-2"><span class="text-brand-600 dark:text-brand-300">•</span> {{ t('import.step4') }}</li>
        </ul>
        <button @click="downloadTemplate" class="btn-ghost btn-sm">{{ t('import.download_template') }}</button>
      </div>

      <!-- Uploader -->
      <div class="card p-5 space-y-4">
        <label
          class="block border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-colors"
          :class="dragging ? 'border-brand bg-surface-2' : 'border-line hover:border-brand/50'"
          @dragover.prevent="dragging = true"
          @dragleave.prevent="dragging = false"
          @drop.prevent="onDrop"
        >
          <input type="file" accept=".xlsx,.xls,.csv,.txt" class="hidden" @change="pick" />
          <svg class="w-10 h-10 mx-auto text-faint" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
          <p class="mt-2 text-sm font-semibold text-fg">{{ file ? file.name : t('import.choose_file') }}</p>
          <p class="text-xs text-faint mt-0.5">{{ file ? (file.size / 1024).toFixed(0) + ' KB' : t('import.file_hint') }}</p>
        </label>

        <label class="flex items-center gap-2.5 text-sm text-muted">
          <input type="checkbox" v-model="generateQr" class="rounded border-line text-brand focus:ring-brand" />
          {{ t('import.generate_qr') }}
          <span class="text-xs text-faint">{{ t('import.generate_qr_hint') }}</span>
        </label>

        <div class="flex items-center gap-3">
          <button @click="submit" :disabled="!file || importing" class="btn-primary">
            <svg v-if="importing" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
            {{ importing ? t('import.importing') : t('import.import_assets') }}
          </button>
          <button v-if="file && !importing" @click="reset" class="btn-ghost">{{ t('import.clear') }}</button>
        </div>
        <p v-if="importing && generateQr" class="text-xs text-faint">{{ t('import.qr_wait_hint') }}</p>
      </div>

      <!-- Result -->
      <div v-if="result" class="card p-5 space-y-4">
        <h3 class="font-bold text-fg">{{ t('import.complete') }}</h3>
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

        <div v-if="result.errors.length" class="space-y-1.5">
          <p class="text-sm font-semibold text-fg">{{ t('import.failed_rows') }}</p>
          <div class="max-h-48 overflow-y-auto rounded-xl border border-line divide-y divide-line">
            <p v-for="(err, i) in result.errors" :key="i" class="px-3 py-2 text-xs text-muted">{{ err }}</p>
          </div>
        </div>

        <div class="flex gap-3">
          <RouterLink to="/assets" class="btn-primary btn-sm">{{ t('import.view_register') }}</RouterLink>
          <button @click="reset" class="btn-ghost btn-sm">{{ t('import.import_another') }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
