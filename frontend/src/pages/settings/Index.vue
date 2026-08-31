<script setup>
import { ref, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import { useToastStore } from '../../stores/toast'
import { useLocale } from '../../composables/useLocale'
import { useThemeColor } from '../../composables/useThemeColor'
import { useBranding } from '../../composables/useBranding'

const { t } = useI18n()
const { setLocale } = useLocale()
const { applyThemeColor } = useThemeColor()
const { systemName, organizationName } = useBranding()
const toast = useToastStore()
const loading = ref(true)
const form = reactive({
  organization_name: '', system_name: '', theme_color: '#128a43', email: '', phone: '',
  address: '', qr_size: 300, locale: 'en', report_interval_months: 6, report_recipient_email: '',
  include_staff_in_reports: false,
  mail_mailer: 'smtp', mail_host: '', mail_port: 587, mail_encryption: 'tls',
  mail_username: '', mail_password: '', mail_from_address: '', mail_from_name: '',
})

// The saved SMTP password is never sent to the browser — we only learn whether
// one exists, so the field can say "leave blank to keep the current one".
const mailPasswordSet = ref(false)

async function loadSettings() {
  const { data } = await http.get('/settings')
  Object.keys(form).forEach((key) => {
    if (data[key] !== undefined) form[key] = data[key]
  })
  mailPasswordSet.value = data.mail_password_set === true
  form.mail_password = ''
  // Stored as the string '1'/'0' like every other setting — coerce to a
  // real boolean or the checkbox would render "checked" for both values
  // (a non-empty string is always truthy in JS, '0' included).
  form.include_staff_in_reports = data.include_staff_in_reports === '1'
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
    systemName.value = form.system_name
    organizationName.value = form.organization_name
    document.title = form.system_name || t('app_name')
    // Saved passwords are write-only: clear the field and flip the hint so a
    // second save doesn't resubmit it (and a blank field doesn't read as "no
    // password on file").
    if (form.mail_password) mailPasswordSet.value = true
    form.mail_password = ''
    toast.success(t('settings.updated'))
  } catch (e) {
    toast.error(e.response?.data?.message || t('settings.update_failed'))
  }
}

// Mail test
const testEmail = ref('')
const sendingTest = ref(false)

async function sendTestEmail() {
  sendingTest.value = true
  try {
    const { data } = await http.post('/settings/test-mail', { email: testEmail.value })
    toast.success(data.message || t('settings.mail_test_sent'))
  } catch (e) {
    toast.error(e.response?.data?.message || t('settings.mail_test_failed'))
  } finally {
    sendingTest.value = false
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
        <h1 class="font-display text-xl font-bold text-fg tracking-tight">{{ t('settings.title') }}</h1>
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
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.report_interval') }}</label>
            <input v-model.number="form.report_interval_months" type="number" min="1" max="24" class="input" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.report_recipient_email') }}</label>
            <input v-model="form.report_recipient_email" type="email" placeholder="reports@example.com" class="input" />
          </div>
        </div>
        <p class="text-xs text-faint -mt-2">{{ t('settings.report_recipient_email_hint') }}</p>

        <div class="border-t border-line pt-4 space-y-3">
          <h3 class="text-xs font-semibold text-muted tracking-wide uppercase">{{ t('settings.staff_role') }}</h3>
          <label class="flex items-start gap-2.5 text-sm text-muted select-none cursor-pointer">
            <input type="checkbox" v-model="form.include_staff_in_reports" class="mt-0.5 rounded border-line text-brand focus:ring-brand/30" />
            <span>
              {{ t('settings.include_staff_in_reports') }}
              <span class="block text-xs text-faint mt-0.5">{{ t('settings.include_staff_in_reports_hint') }}</span>
            </span>
          </label>
        </div>

        <div class="border-t border-line pt-4 space-y-3">
          <div>
            <h3 class="text-xs font-semibold text-muted tracking-wide uppercase">{{ t('settings.mail_title') }}</h3>
            <p class="text-xs text-faint mt-1">{{ t('settings.mail_subtitle') }}</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_mailer') }}</label>
              <select v-model="form.mail_mailer" class="input">
                <option value="smtp">SMTP</option>
                <option value="log">{{ t('settings.mail_mailer_log') }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_encryption') }}</label>
              <select v-model="form.mail_encryption" class="input">
                <option value="tls">TLS</option>
                <option value="ssl">SSL</option>
                <option value="none">{{ t('settings.mail_encryption_none') }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2 space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_host') }}</label>
              <input v-model="form.mail_host" placeholder="smtp.gmail.com" class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_port') }}</label>
              <input v-model.number="form.mail_port" type="number" min="1" max="65535" class="input" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_username') }}</label>
              <input v-model="form.mail_username" autocomplete="off" class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_password') }}</label>
              <input
                v-model="form.mail_password"
                type="password"
                autocomplete="new-password"
                :placeholder="mailPasswordSet ? t('settings.mail_password_keep') : ''"
                class="input"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_from_address') }}</label>
              <input v-model="form.mail_from_address" type="email" class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_from_name') }}</label>
              <input v-model="form.mail_from_name" class="input" />
            </div>
          </div>

          <div class="rounded-xl bg-bg border border-line p-4 space-y-2">
            <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_test') }}</label>
            <div class="flex gap-2">
              <input v-model="testEmail" type="email" :placeholder="t('settings.mail_test_placeholder')" class="input flex-1" />
              <button
                type="button"
                @click="sendTestEmail"
                :disabled="sendingTest || !testEmail"
                class="btn-ghost flex-shrink-0 disabled:opacity-50"
              >
                {{ sendingTest ? t('settings.mail_test_sending') : t('settings.mail_test_send') }}
              </button>
            </div>
            <p class="text-xs text-faint">{{ t('settings.mail_test_hint') }}</p>
          </div>
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
