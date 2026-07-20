<script setup>
import { ref, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import { useToastStore } from '../../stores/toast'
import { useLocale } from '../../composables/useLocale'
import { useThemeColor } from '../../composables/useThemeColor'

const { t } = useI18n()
const { setLocale } = useLocale()
const { applyThemeColor } = useThemeColor()
const toast = useToastStore()
const loading = ref(true)
const form = reactive({
  organization_name: '', system_name: '', theme_color: '#128a43', email: '', phone: '',
  address: '', qr_size: 300, locale: 'en', report_interval_months: 6,
})

async function loadSettings() {
  const { data } = await http.get('/settings')
  Object.keys(form).forEach((key) => {
    if (data[key] !== undefined) form[key] = data[key]
  })
  if (data.locale) setLocale(data.locale)
  if (data.theme_color) applyThemeColor(data.theme_color)
  loading.value = false
}

function onLocaleChange() {
  setLocale(form.locale)
}

function onThemeColorChange() {
  applyThemeColor(form.theme_color)
}

async function handleSubmit() {
  try {
    await http.post('/settings', form)
    setLocale(form.locale)
    applyThemeColor(form.theme_color)
    toast.success(t('settings.updated'))
  } catch (e) {
    toast.error(e.response?.data?.message || t('settings.update_failed'))
  }
}

// Backup & Restore
const backups = ref([])
const backingUp = ref(false)
const pendingRestore = ref(null)
const pendingDelete = ref(null)

async function loadBackups() {
  const { data } = await http.get('/settings/backups')
  backups.value = data
}

async function createBackup() {
  backingUp.value = true
  try {
    await http.post('/settings/backup')
    toast.success(t('settings.backup_created'))
    await loadBackups()
  } catch (e) {
    toast.error(e.response?.data?.message || t('settings.backup_failed'))
  } finally {
    backingUp.value = false
  }
}

async function downloadBackup(name) {
  const { data } = await http.get(`/settings/backups/${encodeURIComponent(name)}/download`, { responseType: 'blob' })
  const url = URL.createObjectURL(data)
  const a = document.createElement('a')
  a.href = url
  a.download = name
  a.click()
  URL.revokeObjectURL(url)
}

async function confirmRestore() {
  const name = pendingRestore.value
  pendingRestore.value = null
  try {
    await http.post(`/settings/backups/${encodeURIComponent(name)}/restore`)
    toast.success(t('settings.restored'))
  } catch (e) {
    toast.error(e.response?.data?.message || t('settings.restore_failed'))
  }
}

async function confirmDelete() {
  const name = pendingDelete.value
  pendingDelete.value = null
  try {
    await http.delete(`/settings/backups/${encodeURIComponent(name)}`)
    toast.success(t('settings.deleted'))
    await loadBackups()
  } catch (e) {
    toast.error(e.response?.data?.message || t('settings.delete_failed'))
  }
}

function formatSize(bytes) {
  if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
  if (bytes >= 1024) return (bytes / 1024).toFixed(0) + ' KB'
  return bytes + ' B'
}

onMounted(() => {
  loadSettings()
  loadBackups()
})
</script>

<template>
  <AppLayout>
    <div class="p-8 max-w-3xl mx-auto space-y-6">
      <div>
        <h1 class="text-xl font-bold text-fg tracking-tight">{{ t('settings.title') }}</h1>
        <p class="text-muted text-sm mt-0.5">{{ t('settings.subtitle') }}</p>
      </div>

      <form v-if="!loading" @submit.prevent="handleSubmit" class="bg-surface rounded-2xl border border-line p-8 space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.organization_name') }}</label>
          <input v-model="form.organization_name" class="input" />
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.system_name') }}</label>
          <input v-model="form.system_name" class="input" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.email') }}</label>
            <input v-model="form.email" type="email" class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.phone') }}</label>
            <input v-model="form.phone" class="input" />
          </div>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.address') }}</label>
          <textarea v-model="form.address" rows="2" class="input"></textarea>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.language') }}</label>
            <select v-model="form.locale" @change="onLocaleChange" class="input">
              <option value="en">English</option>
              <option value="km">ខ្មែរ</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.qr_size') }}</label>
            <input v-model.number="form.qr_size" type="number" min="100" max="1000" class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.theme_color') }}</label>
            <input v-model="form.theme_color" @input="onThemeColorChange" type="color" class="w-full h-11 border border-line rounded-xl cursor-pointer p-1" />
          </div>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.report_interval') }}</label>
          <input v-model.number="form.report_interval_months" type="number" min="1" max="24" class="input" />
        </div>
        <div class="pt-2">
          <button type="submit" class="btn-primary">{{ t('settings.save') }}</button>
        </div>
      </form>

      <div class="bg-surface rounded-2xl border border-line p-8 space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="font-bold text-fg">{{ t('settings.backup_title') }}</h2>
            <p class="text-muted text-sm mt-0.5">{{ t('settings.backup_subtitle') }}</p>
          </div>
          <button @click="createBackup" :disabled="backingUp" class="btn-primary btn-sm flex-shrink-0">
            {{ backingUp ? t('settings.backing_up') : t('settings.create_backup') }}
          </button>
        </div>

        <div v-if="backups.length" class="table-wrap">
          <table class="data-table">
            <thead>
              <tr><th>{{ t('settings.file') }}</th><th>{{ t('settings.size') }}</th><th>{{ t('settings.created') }}</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="b in backups" :key="b.name">
                <td class="font-mono text-xs">{{ b.name }}</td>
                <td>{{ formatSize(b.size) }}</td>
                <td>{{ b.date }}</td>
                <td class="text-right pr-5 space-x-1.5 whitespace-nowrap">
                  <button @click="downloadBackup(b.name)" class="btn-ghost btn-sm">{{ t('settings.download') }}</button>
                  <button @click="pendingRestore = b.name" class="btn-ghost btn-sm">{{ t('settings.restore') }}</button>
                  <button @click="pendingDelete = b.name" class="btn-ghost btn-sm text-red-600 dark:text-red-400">{{ t('settings.delete') }}</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="text-sm text-faint">{{ t('settings.no_backups') }}</p>
      </div>
    </div>

    <ConfirmDialog
      v-if="pendingRestore"
      :title="t('settings.restore_title')"
      :message="t('settings.restore_message')"
      @confirm="confirmRestore"
      @cancel="pendingRestore = null"
    />
    <ConfirmDialog
      v-if="pendingDelete"
      :title="t('settings.delete_title')"
      :message="t('settings.delete_message')"
      @confirm="confirmDelete"
      @cancel="pendingDelete = null"
    />
  </AppLayout>
</template>
