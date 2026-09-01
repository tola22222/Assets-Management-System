<script setup>
import { ref, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import AppLayout from '../../layouts/AppLayout.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import { useToastStore } from '../../stores/toast'
import { useLocale } from '../../composables/useLocale'
import { useTheme } from '../../composables/useTheme'
import { useThemeColor } from '../../composables/useThemeColor'
import { useBranding } from '../../composables/useBranding'

const { t } = useI18n()
const { setLocale } = useLocale()
// Light/dark is a per-browser preference held in localStorage, not a row in
// `settings` — the same as the old Blade screen's toggle. It applies on click
// rather than on Save, so it deliberately sits outside the settings form.
const { isDark, setDark, setLight } = useTheme()
const { applyThemeColor } = useThemeColor()
const { systemName, organizationName, logoUrl, refreshBranding } = useBranding()

// Logo upload. The backend has always accepted this (SettingController::update
// validates and stores a `logo`), but no control existed to send one.
const logoFile = ref(null)
const logoPreview = ref('')

function onLogoChange(event) {
  const file = event.target.files?.[0]
  if (!file) return
  logoFile.value = file
  logoPreview.value = URL.createObjectURL(file)
}

function clearLogoSelection() {
  logoFile.value = null
  if (logoPreview.value) URL.revokeObjectURL(logoPreview.value)
  logoPreview.value = ''
}
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

// Server-derived (last_scheduled_report_at + the interval). Kept as a plain
// ref rather than recomputed in JS so this can never drift from the date the
// scheduler itself will act on; it refreshes from the save response.
const nextReportDue = ref(null)

// Which engine is live decides the backup file format (.sql vs .sqlite), so the
// backup card names it rather than leaving the admin to guess from extensions.
const databaseDriver = ref('')

function applySettingsPayload(data) {
  Object.keys(form).forEach((key) => {
    if (data[key] !== undefined) form[key] = data[key]
  })
  nextReportDue.value = data.next_report_due ?? null
  databaseDriver.value = data.database_driver || ''
}

// A failed load used to leave `loading` stuck at true forever, and the form is
// rendered behind v-if="!loading" — so one failed request silently erased every
// control on the page with no error shown, which looks exactly like the
// features having been deleted. Fail loudly and offer a retry instead.
const loadError = ref('')

async function loadSettings() {
  loadError.value = ''
  try {
    const { data } = await http.get('/settings')
    applySettingsPayload(data)
    mailPasswordSet.value = data.mail_password_set === true
    form.mail_password = ''
    // Stored as the string '1'/'0' like every other setting — coerce to a
    // real boolean or the checkbox would render "checked" for both values
    // (a non-empty string is always truthy in JS, '0' included).
    form.include_staff_in_reports = data.include_staff_in_reports === '1'
    if (data.locale) setLocale(data.locale)
    if (data.theme_color) applyThemeColor(data.theme_color)
  } catch (e) {
    loadError.value = e.response?.data?.message || t('settings.load_failed')
    toast.error(loadError.value)
  } finally {
    loading.value = false
  }
}

function onLocaleChange() {
  setLocale(form.locale)
}

function onThemeColorChange() {
  applyThemeColor(form.theme_color)
}

async function handleSubmit() {
  try {
    // A file can't ride along in a JSON body, so switch to multipart only when
    // one is actually selected — keeps the common no-logo save unchanged.
    let saved
    if (logoFile.value) {
      const payload = new FormData()
      Object.entries(form).forEach(([key, value]) => {
        if (value === null || value === undefined) return
        payload.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value)
      })
      payload.append('logo', logoFile.value)
      saved = (await http.post('/settings', payload)).data
      clearLogoSelection()
      await refreshBranding()
    } else {
      saved = (await http.post('/settings', form)).data
    }
    // update() returns the fresh index() payload, so a changed report interval
    // shows its new due date immediately instead of after a reload.
    nextReportDue.value = saved?.next_report_due ?? null
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
    toast.error(errorMessage(e, t('settings.update_failed')))
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
    toast.error(errorMessage(e, t('settings.mail_test_failed')))
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
  try {
    const { data } = await http.get('/settings/backups')
    backups.value = data
  } catch (e) {
    // The backup card renders independently of the settings form, so a failure
    // here must not take the rest of the page down with it.
    backups.value = []
    toast.error(errorMessage(e, t('settings.backups_load_failed')))
  }
}

async function createBackup() {
  backingUp.value = true
  try {
    await http.post('/settings/backup')
    toast.success(t('settings.backup_created'))
    await loadBackups()
  } catch (e) {
    toast.error(errorMessage(e, t('settings.backup_failed')))
  } finally {
    backingUp.value = false
  }
}

// Upload an existing dump — the only way to restore a backup this server did
// not itself produce (a download from another install, or from before a rebuild).
const uploadFile = ref(null)
const uploading = ref(false)
const uploadInput = ref(null)

function onUploadChange(event) {
  uploadFile.value = event.target.files?.[0] || null
}

function clearUploadSelection() {
  uploadFile.value = null
  if (uploadInput.value) uploadInput.value.value = ''
}

async function submitUpload() {
  if (!uploadFile.value) return
  uploading.value = true
  try {
    const payload = new FormData()
    payload.append('file', uploadFile.value)
    await http.post('/settings/backups/upload', payload)
    toast.success(t('settings.backup_uploaded'))
    clearUploadSelection()
    await loadBackups()
  } catch (e) {
    toast.error(errorMessage(e, t('settings.backup_upload_failed')))
  } finally {
    uploading.value = false
  }
}

async function downloadBackup(name) {
  try {
    const { data } = await http.get(`/settings/backups/${encodeURIComponent(name)}/download`, { responseType: 'blob' })
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = name
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    // A missing or unreadable backup file used to do nothing at all on click.
    toast.error(errorMessage(e, t('settings.download_failed')))
  }
}

async function confirmRestore() {
  const name = pendingRestore.value
  pendingRestore.value = null
  try {
    await http.post(`/settings/backups/${encodeURIComponent(name)}/restore`)
    toast.success(t('settings.restored'))
  } catch (e) {
    toast.error(errorMessage(e, t('settings.restore_failed')))
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
    toast.error(errorMessage(e, t('settings.delete_failed')))
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
    <!-- Two-column card grid. The settings that save together stay inside one
         <form> with a single Save bar; Appearance and Backup act immediately and
         so sit outside it, in their own row. -->
    <div class="p-6 sm:p-8 max-w-6xl mx-auto space-y-6">
      <div>
        <h1 class="font-display text-3xl font-bold text-fg tracking-tight">{{ t('settings.title') }}</h1>
        <p class="text-muted text-sm mt-1">{{ t('settings.subtitle') }}</p>
      </div>

      <!-- Never leave the page looking empty: say what failed and offer a retry. -->
      <div v-if="loadError" class="card border-red-300 dark:border-red-800 p-6 space-y-3">
        <h2 class="font-bold text-red-600 dark:text-red-400">{{ t('settings.load_failed_title') }}</h2>
        <p class="text-sm text-muted">{{ loadError }}</p>
        <button type="button" @click="loading = true; loadSettings()" class="btn-primary btn-sm">{{ t('settings.retry') }}</button>
      </div>

      <form v-if="!loading && !loadError" @submit.prevent="handleSubmit" class="space-y-6">
        <!-- items-start keeps the shorter column from stretching to match the taller one. -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">

          <!-- Left column -->
          <div class="space-y-6">
          <div class="card p-6 space-y-4">
            <div>
              <h2 class="font-bold text-fg">{{ t('settings.organization_info') }}</h2>
              <p class="text-muted text-sm mt-0.5">{{ t('settings.organization_info_subtitle') }}</p>
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.organization_name') }}</label>
              <input v-model="form.organization_name" class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.system_name') }}</label>
              <input v-model="form.system_name" class="input" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.logo') }}</label>
              <!-- flex-wrap with a min-width on the picker: at 375px the
                   preview, the file input and the Cancel button together leave
                   the input about 170px, which clips the chosen filename. It
                   now drops onto its own line instead. -->
              <div class="flex flex-wrap items-center gap-4">
                <div class="w-14 h-14 rounded-xl border border-line bg-surface-2 flex items-center justify-center overflow-hidden flex-shrink-0">
                  <img v-if="logoPreview || logoUrl" :src="logoPreview || logoUrl" alt="" class="w-full h-full object-contain" />
                  <span v-else class="text-[10px] text-faint text-center px-1">{{ t('settings.logo_default') }}</span>
                </div>
                <div class="flex-1 min-w-[200px]">
                  <input type="file" accept="image/png,image/jpeg" @change="onLogoChange" class="block w-full text-sm text-muted file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand file:text-white hover:file:bg-brand-dark file:cursor-pointer" />
                  <p class="text-xs text-faint mt-1">{{ t('settings.logo_hint') }}</p>
                </div>
                <button v-if="logoFile" type="button" @click="clearLogoSelection" class="btn-ghost btn-sm flex-shrink-0">
                  {{ t('common.cancel') }}
                </button>
              </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
              <textarea v-model="form.address" rows="2" class="textarea"></textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.language') }}</label>
                <select v-model="form.locale" @change="onLocaleChange" class="select">
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
                <!-- 2.625rem is exactly what .input resolves to (py-2.5 +
                     text-sm line-height + 1px borders). h-11 made this swatch
                     2px taller than the Language and QR size fields beside it,
                     so the row sat visibly out of line. -->
                <input v-model="form.theme_color" @input="onThemeColorChange" type="color" class="w-full h-[2.625rem] bg-surface-2 border border-line rounded-xl cursor-pointer p-1 transition focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15" />
              </div>
            </div>
          </div>

          <!-- Appearance is grouped here only so the two columns come out a
               similar height. It holds no form fields — light/dark is a
               per-browser localStorage preference that applies on click — so
               nothing in it is submitted with the settings. -->
          <div class="card p-6 space-y-4">
            <div>
              <h2 class="font-bold text-fg">{{ t('settings.appearance') }}</h2>
              <p class="text-muted text-sm mt-0.5">{{ t('settings.appearance_subtitle') }}</p>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-4">
              <div>
                <p class="text-sm font-semibold text-fg">{{ t('settings.dark_mode') }}</p>
                <p class="text-xs text-faint mt-0.5">{{ t('settings.dark_mode_hint') }}</p>
              </div>
              <div class="flex items-center gap-1 bg-surface-2 rounded-xl p-1 w-fit flex-shrink-0">
                <button
                  type="button"
                  @click="setLight()"
                  class="px-4 py-1.5 rounded-lg text-xs font-semibold transition"
                  :class="!isDark ? 'bg-brand text-white' : 'text-muted hover:text-fg'"
                >{{ t('settings.light') }}</button>
                <button
                  type="button"
                  @click="setDark()"
                  class="px-4 py-1.5 rounded-lg text-xs font-semibold transition"
                  :class="isDark ? 'bg-brand text-white' : 'text-muted hover:text-fg'"
                >{{ t('settings.dark') }}</button>
              </div>
            </div>
          </div>
          </div>

          <!-- Right column: two stacked cards, so the two columns end up a
               similar height instead of one very tall card beside a stub. -->
          <div class="space-y-6">

            <div class="card p-6 space-y-4">
              <div>
                <h2 class="font-bold text-fg">{{ t('settings.report_schedule') }}</h2>
                <p class="text-muted text-sm mt-0.5">{{ t('settings.report_schedule_subtitle') }}</p>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                  <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.report_interval') }}</label>
                  <input v-model.number="form.report_interval_months" type="number" min="1" max="24" class="input" />
                </div>
                <div class="space-y-1.5">
                  <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.report_recipient_email') }}</label>
                  <input v-model="form.report_recipient_email" type="email" placeholder="reports@example.com" class="input" />
                </div>
                <!-- Spans the grid rather than sitting in the email field's own
                     cell: the sentence is long enough to wrap to four ragged
                     lines in a half-width column, which leaves the field beside
                     it stranded above a block of empty space. As a grid child it
                     picks up the grid's own gap-4, so it no longer needs the
                     -mt-2 that used to cancel out a double gap. -->
                <p class="sm:col-span-2 text-xs text-faint">{{ t('settings.report_recipient_email_hint') }}</p>
              </div>

              <!-- Derived from the last send + the interval above, so an admin can
                   see when the next scheduled report actually goes out. -->
              <div class="rounded-xl bg-surface-2 border border-line px-3.5 py-2.5 text-sm">
                <span class="font-semibold text-fg">{{ t('settings.next_report_due') }}</span>
                <span class="text-muted ml-1.5">{{ nextReportDue || t('settings.next_report_due_none') }}</span>
              </div>

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
            </div>

            <div class="card p-6 space-y-4">
              <div>
                <h2 class="font-bold text-fg">{{ t('settings.mail_title') }}</h2>
                <p class="text-muted text-sm mt-0.5">{{ t('settings.mail_subtitle') }}</p>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                  <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_mailer') }}</label>
                  <select v-model="form.mail_mailer" class="select">
                    <option value="smtp">SMTP</option>
                    <option value="log">{{ t('settings.mail_mailer_log') }}</option>
                  </select>
                </div>
                <div class="space-y-1.5">
                  <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_encryption') }}</label>
                  <select v-model="form.mail_encryption" class="select">
                    <option value="tls">TLS</option>
                    <option value="ssl">SSL</option>
                    <option value="none">{{ t('settings.mail_encryption_none') }}</option>
                  </select>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2 space-y-1.5">
                  <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_host') }}</label>
                  <input v-model="form.mail_host" placeholder="smtp.gmail.com" class="input" />
                </div>
                <div class="space-y-1.5">
                  <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_port') }}</label>
                  <input v-model.number="form.mail_port" type="number" min="1" max="65535" class="input" />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                  <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_from_address') }}</label>
                  <input v-model="form.mail_from_address" type="email" class="input" />
                </div>
                <div class="space-y-1.5">
                  <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_from_name') }}</label>
                  <input v-model="form.mail_from_name" class="input" />
                </div>
              </div>

              <div class="rounded-xl bg-surface-2 border border-line p-4 space-y-2">
                <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_test') }}</label>
                <div class="flex flex-wrap gap-2">
                  <input v-model="testEmail" type="email" :placeholder="t('settings.mail_test_placeholder')" class="input flex-1 min-w-[180px]" />
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
          </div>
        </div>

        <!-- One Save for every card above — they post as a single request. -->
        <div class="card p-4 flex flex-wrap items-center gap-3">
          <button type="submit" class="btn-primary">{{ t('settings.save') }}</button>
          <p class="text-xs text-faint">{{ t('settings.save_hint') }}</p>
        </div>
      </form>

      <!-- Full width, not half: the backups table carries a filename, size,
           date and three actions, and at half width the actions get pushed out
           of view behind a horizontal scroll. -->
      <div class="card p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="font-bold text-fg">{{ t('settings.backup_title') }}</h2>
            <p class="text-muted text-sm mt-0.5">{{ t('settings.backup_subtitle') }}</p>
          </div>
          <button @click="createBackup" :disabled="backingUp" class="btn-primary btn-sm flex-shrink-0">
            {{ backingUp ? t('settings.backing_up') : t('settings.create_backup') }}
          </button>
        </div>

        <p v-if="databaseDriver" class="text-xs text-faint">
          {{ t('settings.backup_engine', { engine: databaseDriver === 'sqlite' ? 'SQLite (.sqlite)' : 'MySQL (.sql)' }) }}
        </p>

        <!-- Restoring a dump this server did not make is only possible if it can
             be uploaded first. -->
        <div class="rounded-xl bg-surface-2 border border-line p-4 space-y-2">
          <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.upload_backup') }}</label>
          <div class="flex flex-wrap items-center gap-2">
            <input
              ref="uploadInput"
              type="file"
              accept=".sql,.sqlite"
              @change="onUploadChange"
              class="flex-1 min-w-[240px] sm:max-w-sm block text-sm text-muted file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand file:text-white hover:file:bg-brand-dark file:cursor-pointer"
            />
            <button type="button" @click="submitUpload" :disabled="!uploadFile || uploading" class="btn-primary btn-sm flex-shrink-0 disabled:opacity-50">
              {{ uploading ? t('settings.uploading') : t('settings.upload') }}
            </button>
            <button v-if="uploadFile" type="button" @click="clearUploadSelection" class="btn-ghost btn-sm flex-shrink-0">
              {{ t('common.cancel') }}
            </button>
          </div>
          <p class="text-xs text-faint">{{ t('settings.upload_backup_hint') }}</p>
        </div>

        <!-- overflow-x-auto is load-bearing: this row cannot shrink (mono
             filename plus three whitespace-nowrap cells), so without it the
             Restore and Delete buttons are clipped off-screen and
             unreachable on a phone rather than scrolled to.
             Plain border/rounded-xl rather than .table-wrap: that class adds
             its own shadow and a rounded-2xl radius, which reads as a second
             card floating inside this one and doesn't match the radius of
             the upload panel directly above it. -->
        <div v-if="backups.length" class="border border-line rounded-xl overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr><th>{{ t('settings.file') }}</th><th>{{ t('settings.size') }}</th><th>{{ t('settings.created') }}</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="b in backups" :key="b.name">
                <!-- nowrap on the filename and size: both are short, unbreakable
                     values, and letting them wrap split "69 KB" over two lines
                     and broke filenames mid-token once the column got tight. -->
                <td class="font-mono text-xs whitespace-nowrap">
                  {{ b.name }}
                  <!-- Say up front that this file cannot load into the running
                       engine, instead of only failing after Restore is clicked. -->
                  <span v-if="b.restorable === false" class="badge badge-warning ml-1.5">{{ t('settings.wrong_format') }}</span>
                </td>
                <td class="whitespace-nowrap">{{ formatSize(b.size) }}</td>
                <td class="whitespace-nowrap">{{ b.date }}</td>
                <td class="text-right pr-5 space-x-1.5 whitespace-nowrap">
                  <button @click="downloadBackup(b.name)" class="btn-ghost btn-sm">{{ t('settings.download') }}</button>
                  <button
                    @click="pendingRestore = b.name"
                    :disabled="b.restorable === false"
                    :title="b.restorable === false ? t('settings.wrong_format_hint') : ''"
                    class="btn-ghost btn-sm disabled:opacity-40 disabled:cursor-not-allowed"
                  >{{ t('settings.restore') }}</button>
                  <!-- .btn-danger is the app-wide destructive button (every other
                       page's Delete uses it); btn-ghost + text-red also lost the
                       red on hover, since .btn-ghost sets hover:text-fg. -->
                  <button @click="pendingDelete = b.name" class="btn-danger btn-sm">{{ t('settings.delete') }}</button>
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
