<script setup>
import { ref, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import { useToastStore } from '../../stores/toast'
import { useLocale } from '../../composables/useLocale'
import { useThemeColor } from '../../composables/useThemeColor'
import { useConfirm } from '../../composables/useConfirm'

const { t } = useI18n()
const { setLocale } = useLocale()
const { applyThemeColor } = useThemeColor()
const toast = useToastStore()
const { confirm } = useConfirm()
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

async function handleRestore(name) {
  const ok = await confirm({
    title: t('settings.restore_title'),
    message: t('settings.restore_message'),
    color: 'error',
    confirmText: t('settings.restore'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return
  try {
    await http.post(`/settings/backups/${encodeURIComponent(name)}/restore`)
    toast.success(t('settings.restored'))
  } catch (e) {
    toast.error(e.response?.data?.message || t('settings.restore_failed'))
  }
}

async function handleDeleteBackup(name) {
  const ok = await confirm({
    title: t('settings.delete_title'),
    message: t('settings.delete_message'),
    color: 'error',
    confirmText: t('settings.delete'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return
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
  <v-container fluid class="pa-0 d-flex flex-column ga-6" style="max-width: 640px">
      <div>
        <h1 class="text-h5 font-weight-bold">{{ t('settings.title') }}</h1>
        <p class="text-body-2 text-medium-emphasis mt-1">{{ t('settings.subtitle') }}</p>
      </div>

      <v-card v-if="!loading" rounded="lg" variant="flat" border class="pa-6">
        <v-form @submit.prevent="handleSubmit">
          <div class="d-flex flex-column ga-1">
            <v-text-field v-model="form.organization_name" :label="t('settings.organization_name')" />
            <v-text-field v-model="form.system_name" :label="t('settings.system_name')" />
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-text-field v-model="form.email" type="email" :label="t('settings.email')" />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field v-model="form.phone" :label="t('settings.phone')" />
              </v-col>
            </v-row>
            <v-textarea v-model="form.address" :label="t('settings.address')" rows="2" />
            <v-row dense>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="form.locale"
                  :label="t('settings.language')"
                  :items="[
                    { title: 'English', value: 'en' },
                    { title: 'ខ្មែរ', value: 'km' },
                  ]"
                  @update:model-value="onLocaleChange"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field v-model.number="form.qr_size" type="number" min="100" max="1000" :label="t('settings.qr_size')" />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field v-model="form.theme_color" type="color" :label="t('settings.theme_color')" @update:model-value="onThemeColorChange" />
              </v-col>
            </v-row>
            <v-text-field v-model.number="form.report_interval_months" type="number" min="1" max="24" :label="t('settings.report_interval')" />
            <v-btn type="submit" color="primary" variant="flat" class="align-self-start mt-2">{{ t('settings.save') }}</v-btn>
          </div>
        </v-form>
      </v-card>

      <v-card rounded="lg" variant="flat" border class="pa-6">
        <div class="d-flex align-center justify-space-between ga-3 mb-4">
          <div>
            <h2 class="text-subtitle-1 font-weight-bold">{{ t('settings.backup_title') }}</h2>
            <p class="text-body-2 text-medium-emphasis mt-1">{{ t('settings.backup_subtitle') }}</p>
          </div>
          <v-btn color="primary" variant="flat" size="small" :loading="backingUp" class="flex-shrink-0" @click="createBackup">
            {{ t('settings.create_backup') }}
          </v-btn>
        </div>

        <v-table v-if="backups.length">
          <thead>
            <tr><th>{{ t('settings.file') }}</th><th>{{ t('settings.size') }}</th><th>{{ t('settings.created') }}</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="b in backups" :key="b.name">
              <td class="font-mono text-caption">{{ b.name }}</td>
              <td>{{ formatSize(b.size) }}</td>
              <td>{{ b.date }}</td>
              <td class="text-right text-no-wrap">
                <v-btn variant="text" size="small" @click="downloadBackup(b.name)">{{ t('settings.download') }}</v-btn>
                <v-btn variant="text" size="small" @click="handleRestore(b.name)">{{ t('settings.restore') }}</v-btn>
                <v-btn variant="text" size="small" color="error" @click="handleDeleteBackup(b.name)">{{ t('settings.delete') }}</v-btn>
              </td>
            </tr>
          </tbody>
        </v-table>
        <p v-else class="text-body-2 text-medium-emphasis mb-0">{{ t('settings.no_backups') }}</p>
      </v-card>
  </v-container>
</template>
