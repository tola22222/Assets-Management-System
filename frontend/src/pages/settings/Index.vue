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

// One tab per section. Backup is the odd one out: it acts immediately and owns
// its own buttons, so its panel renders outside the <form> (a bare <button>
// inside a form defaults to type=submit and would save the settings instead).
const tabs = [
  { id: 'general', label: 'settings.tab_general' },
  { id: 'appearance', label: 'settings.tab_appearance' },
  { id: 'reports', label: 'settings.tab_reports' },
  { id: 'mail', label: 'settings.tab_mail' },
  { id: 'backup', label: 'settings.tab_backup' },
]
const activeTab = ref('general')

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

// The hex field lets the value be typed as well as picked. Half-typed input
// ("#12", "#12ab") would otherwise be handed to applyThemeColor on every
// keystroke, so only a complete 6-digit hex is previewed — the raw text stays
// bound either way so the field never fights what is being typed.
function onThemeColorText() {
  if (/^#[0-9a-fA-F]{6}$/.test(form.theme_color)) applyThemeColor(form.theme_color)
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

// Cancel in the footer discards unsaved edits by re-reading the saved values —
// the same request the page makes on mount, so nothing is written.
function discardChanges() {
  clearLogoSelection()
  loadSettings()
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
    // The backup panel renders independently of the settings form, so a failure
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
    <!-- Single-column, row-based settings layout: a tab bar selects a section,
         and each setting is one full-width row of "label + description" beside
         its control, divided by rules rather than boxed in separate cards. -->
    <div class="p-6 sm:p-8 max-w-5xl mx-auto">
      <div class="mb-5">
        <h1 class="font-display text-2xl font-bold text-fg tracking-tight">{{ t('settings.title') }}</h1>
        <p class="text-muted text-sm mt-1">{{ t('settings.subtitle') }}</p>
      </div>

      <!-- Scrolls sideways rather than wrapping: five tabs plus Khmer labels
           overflow a phone, and a wrapped second row reads as two nav bars. -->
      <nav class="flex gap-1 border-b border-line pb-3 mb-6 overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          @click="activeTab = tab.id"
          class="px-3 py-2 rounded-lg text-sm whitespace-nowrap transition"
          :class="activeTab === tab.id ? 'bg-surface-2 text-fg font-bold' : 'text-muted hover:text-fg font-medium'"
        >{{ t(tab.label) }}</button>
      </nav>

      <!-- Never leave the page looking empty: say what failed and offer a retry. -->
      <div v-if="loadError && activeTab !== 'backup'" class="card border-red-300 dark:border-red-800 p-6 space-y-3">
        <h2 class="font-bold text-red-600 dark:text-red-400">{{ t('settings.load_failed_title') }}</h2>
        <p class="text-sm text-muted">{{ loadError }}</p>
        <button type="button" @click="loading = true; loadSettings()" class="btn-primary btn-sm">{{ t('settings.retry') }}</button>
      </div>

      <!-- One <form> for every savable tab: the fields of the tabs that are not
           on screen stay bound to the same reactive object, so Save still posts
           the whole settings payload from whichever tab is open. -->
      <form v-if="!loading && !loadError && activeTab !== 'backup'" @submit.prevent="handleSubmit">

        <!-- ── General ─────────────────────────────────────────────── -->
        <template v-if="activeTab === 'general'">
          <div class="mb-6">
            <h2 class="text-base font-semibold text-fg">{{ t('settings.organization_info') }}</h2>
            <p class="text-sm text-muted mt-0.5">{{ t('settings.organization_info_subtitle') }}</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.organization_name') }}</label>
            </div>
            <div><input v-model="form.organization_name" class="input max-w-md" /></div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.system_name') }}</label>
              <p class="text-sm text-muted mt-0.5">{{ t('settings.system_name_desc') }}</p>
            </div>
            <div><input v-model="form.system_name" class="input max-w-md" /></div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.logo') }}</label>
              <p class="text-sm text-muted mt-0.5">{{ t('settings.logo_hint') }}</p>
            </div>
            <!-- flex-wrap with a min-width on the picker: at 375px the preview,
                 the file input and the Cancel button together leave the input
                 about 170px, which clips the chosen filename. It now drops onto
                 its own line instead. -->
            <div class="flex flex-wrap items-center gap-4">
              <div class="w-14 h-14 rounded-xl border border-line bg-surface-2 flex items-center justify-center overflow-hidden flex-shrink-0">
                <img v-if="logoPreview || logoUrl" :src="logoPreview || logoUrl" alt="" class="w-full h-full object-contain" />
                <span v-else class="text-[10px] text-faint text-center px-1">{{ t('settings.logo_default') }}</span>
              </div>
              <div class="flex-1 min-w-[200px]">
                <input type="file" accept="image/png,image/jpeg" @change="onLogoChange" class="block w-full text-sm text-muted file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand file:text-white hover:file:bg-brand-dark file:cursor-pointer" />
              </div>
              <button v-if="logoFile" type="button" @click="clearLogoSelection" class="btn-ghost btn-sm flex-shrink-0">
                {{ t('common.cancel') }}
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.email') }}</label>
            </div>
            <div><input v-model="form.email" type="email" class="input max-w-md" /></div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.phone') }}</label>
            </div>
            <div><input v-model="form.phone" class="input max-w-xs" /></div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.address') }}</label>
            </div>
            <div><textarea v-model="form.address" rows="3" class="textarea max-w-md"></textarea></div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.qr_size') }}</label>
              <p class="text-sm text-muted mt-0.5">{{ t('settings.qr_size_desc') }}</p>
            </div>
            <div><input v-model.number="form.qr_size" type="number" min="100" max="1000" class="input w-32" /></div>
          </div>
        </template>

        <!-- ── Appearance ──────────────────────────────────────────── -->
        <template v-if="activeTab === 'appearance'">
          <div class="mb-6">
            <h2 class="text-base font-semibold text-fg">{{ t('settings.appearance') }}</h2>
            <p class="text-sm text-muted mt-0.5">{{ t('settings.appearance_subtitle') }}</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.theme_color') }}</label>
              <p class="text-sm text-muted mt-0.5">{{ t('settings.theme_color_desc') }}</p>
            </div>
            <div class="flex items-center gap-2">
              <!-- The native picker is the swatch: clicking the colour opens it,
                   and the hex beside it can be pasted or typed instead. -->
              <input
                v-model="form.theme_color"
                @input="onThemeColorChange"
                type="color"
                class="w-10 h-10 flex-shrink-0 bg-surface-2 border border-line rounded-lg cursor-pointer p-1 transition focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15"
              />
              <input
                v-model="form.theme_color"
                @input="onThemeColorText"
                type="text"
                spellcheck="false"
                maxlength="7"
                class="input w-32 font-mono uppercase"
              />
            </div>
          </div>

          <!-- Light/dark holds no form field — it is a per-browser localStorage
               preference that applies the moment it is clicked, so it is not
               submitted with the rest of the settings. -->
          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.dark_mode') }}</label>
              <p class="text-sm text-muted mt-0.5">{{ t('settings.dark_mode_hint') }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4 max-w-md">
              <button type="button" @click="setLight()" class="text-left">
                <div
                  class="relative h-[120px] rounded-xl border-[1.5px] bg-surface-2 flex items-center justify-center overflow-hidden transition"
                  :class="!isDark ? 'border-brand ring-1 ring-brand' : 'border-line'"
                >
                  <span v-if="!isDark" class="absolute top-2 right-2 w-[18px] h-[18px] rounded-full bg-brand flex items-center justify-center">
                    <svg class="w-2.5 h-2.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  </span>
                  <!-- A miniature of the app in that mode, drawn from plain divs
                       in fixed neutrals: it has to keep looking light while the
                       app is dark (and the reverse), so it cannot use the
                       theme tokens that flip underneath it. -->
                  <div class="w-4/5 h-[70%] rounded-md border border-[#EAECF0] bg-white p-2 flex flex-col gap-1.5">
                    <div class="h-1.5 rounded-full bg-brand w-2/5"></div>
                    <div class="h-1.5 rounded-full bg-[#EAECF0]"></div>
                    <div class="h-1.5 rounded-full bg-[#EAECF0]"></div>
                  </div>
                </div>
                <div class="mt-2.5">
                  <p class="text-sm font-semibold text-fg">{{ t('settings.light') }}</p>
                  <p class="text-[13px] text-muted mt-0.5">{{ t('settings.theme_light_desc') }}</p>
                </div>
              </button>

              <button type="button" @click="setDark()" class="text-left">
                <div
                  class="relative h-[120px] rounded-xl border-[1.5px] bg-surface-2 flex items-center justify-center overflow-hidden transition"
                  :class="isDark ? 'border-brand ring-1 ring-brand' : 'border-line'"
                >
                  <span v-if="isDark" class="absolute top-2 right-2 w-[18px] h-[18px] rounded-full bg-brand flex items-center justify-center">
                    <svg class="w-2.5 h-2.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  </span>
                  <div class="w-4/5 h-[70%] rounded-md border border-[#344054] bg-[#101828] p-2 flex flex-col gap-1.5">
                    <div class="h-1.5 rounded-full bg-brand w-2/5"></div>
                    <div class="h-1.5 rounded-full bg-[#344054]"></div>
                    <div class="h-1.5 rounded-full bg-[#344054]"></div>
                  </div>
                </div>
                <div class="mt-2.5">
                  <p class="text-sm font-semibold text-fg">{{ t('settings.dark') }}</p>
                  <p class="text-[13px] text-muted mt-0.5">{{ t('settings.theme_dark_desc') }}</p>
                </div>
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.language') }}</label>
              <p class="text-sm text-muted mt-0.5">{{ t('settings.language_desc') }}</p>
            </div>
            <div>
              <select v-model="form.locale" @change="onLocaleChange" class="select w-60">
                <option value="en">English</option>
                <option value="km">ខ្មែរ</option>
              </select>
            </div>
          </div>
        </template>

        <!-- ── Reports ─────────────────────────────────────────────── -->
        <template v-if="activeTab === 'reports'">
          <div class="mb-6">
            <h2 class="text-base font-semibold text-fg">{{ t('settings.report_schedule') }}</h2>
            <p class="text-sm text-muted mt-0.5">{{ t('settings.report_schedule_subtitle') }}</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.report_interval') }}</label>
              <p class="text-sm text-muted mt-0.5">{{ t('settings.report_interval_desc') }}</p>
            </div>
            <div><input v-model.number="form.report_interval_months" type="number" min="1" max="24" class="input w-32" /></div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.report_recipient_email') }}</label>
              <p class="text-sm text-muted mt-0.5">{{ t('settings.report_recipient_email_hint') }}</p>
            </div>
            <div><input v-model="form.report_recipient_email" type="email" placeholder="reports@example.com" class="input max-w-md" /></div>
          </div>

          <!-- Derived from the last send + the interval above, so an admin can
               see when the next scheduled report actually goes out. -->
          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.next_report_due') }}</label>
            </div>
            <div>
              <div class="rounded-xl bg-surface-2 border border-line px-3.5 py-2.5 text-sm text-muted max-w-md">
                {{ nextReportDue || t('settings.next_report_due_none') }}
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.staff_role') }}</label>
              <p class="text-sm text-muted mt-0.5">{{ t('settings.include_staff_in_reports_hint') }}</p>
            </div>
            <div>
              <label class="flex items-start gap-2.5 text-sm text-muted select-none cursor-pointer">
                <input type="checkbox" v-model="form.include_staff_in_reports" class="mt-0.5 rounded border-line text-brand focus:ring-brand/30" />
                <span>{{ t('settings.include_staff_in_reports') }}</span>
              </label>
            </div>
          </div>
        </template>

        <!-- ── Outgoing email ──────────────────────────────────────── -->
        <template v-if="activeTab === 'mail'">
          <div class="mb-6">
            <h2 class="text-base font-semibold text-fg">{{ t('settings.mail_title') }}</h2>
            <p class="text-sm text-muted mt-0.5">{{ t('settings.mail_subtitle') }}</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.mail_mailer') }}</label>
              <p class="text-sm text-muted mt-0.5">{{ t('settings.mail_mailer_desc') }}</p>
            </div>
            <div>
              <select v-model="form.mail_mailer" class="select w-60">
                <option value="smtp">SMTP</option>
                <option value="log">{{ t('settings.mail_mailer_log') }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.mail_server') }}</label>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-md">
              <div class="sm:col-span-2 space-y-1.5">
                <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_host') }}</label>
                <input v-model="form.mail_host" placeholder="smtp.gmail.com" class="input" />
              </div>
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_port') }}</label>
                <input v-model.number="form.mail_port" type="number" min="1" max="65535" class="input" />
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.mail_encryption') }}</label>
            </div>
            <div>
              <select v-model="form.mail_encryption" class="select w-60">
                <option value="tls">TLS</option>
                <option value="ssl">SSL</option>
                <option value="none">{{ t('settings.mail_encryption_none') }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.mail_credentials') }}</label>
              <p class="text-sm text-muted mt-0.5">{{ t('settings.mail_credentials_desc') }}</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-md">
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
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.mail_from') }}</label>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-md">
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_from_address') }}</label>
                <input v-model="form.mail_from_address" type="email" class="input" />
              </div>
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-muted tracking-wide">{{ t('settings.mail_from_name') }}</label>
                <input v-model="form.mail_from_name" class="input" />
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
            <div class="md:pr-4">
              <label class="block text-sm font-semibold text-fg">{{ t('settings.mail_test') }}</label>
              <p class="text-sm text-muted mt-0.5">{{ t('settings.mail_test_hint') }}</p>
            </div>
            <div class="flex flex-wrap gap-2 max-w-md">
              <input v-model="testEmail" type="email" :placeholder="t('settings.mail_test_placeholder')" class="input flex-1 min-w-[180px]" />
              <button
                type="button"
                @click="sendTestEmail"
                :disabled="sendingTest || !testEmail"
                class="btn-ghost w-full sm:w-auto sm:flex-shrink-0 disabled:opacity-50"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13" /><polygon points="22 2 15 22 11 13 2 9 22 2" /></svg>
                {{ sendingTest ? t('settings.mail_test_sending') : t('settings.mail_test_send') }}
              </button>
            </div>
          </div>
        </template>

        <!-- One Save for every tab above — they post as a single request. -->
        <!-- Below sm the two buttons stack full width with Save on top, so the
             main action is the one under your thumb. -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 border-t border-line pt-4 mt-4">
          <p class="text-xs text-faint sm:mr-auto">{{ t('settings.save_hint') }}</p>
          <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3 w-full sm:w-auto">
            <button type="button" @click="discardChanges" class="btn-ghost w-full sm:w-auto">{{ t('common.cancel') }}</button>
            <button type="submit" class="btn-primary w-full sm:w-auto">{{ t('settings.save') }}</button>
          </div>
        </div>
      </form>

      <!-- ── Backup ────────────────────────────────────────────────
           Outside the <form> on purpose: these buttons act immediately, and a
           button inside a form submits it by default. -->
      <div v-if="activeTab === 'backup'">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
          <div>
            <h2 class="text-base font-semibold text-fg">{{ t('settings.backup_title') }}</h2>
            <p class="text-sm text-muted mt-0.5">{{ t('settings.backup_subtitle') }}</p>
          </div>
          <button type="button" @click="createBackup" :disabled="backingUp" class="btn-primary btn-sm flex-shrink-0 disabled:opacity-60">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" /><polyline points="17 21 17 13 7 13 7 21" /><polyline points="7 3 7 8 15 8" /></svg>
            {{ backingUp ? t('settings.backing_up') : t('settings.create_backup') }}
          </button>
        </div>

        <div v-if="databaseDriver" class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
          <div class="md:pr-4">
            <label class="block text-sm font-semibold text-fg">{{ t('settings.backup_format') }}</label>
          </div>
          <div class="text-sm text-muted">
            {{ t('settings.backup_engine', { engine: databaseDriver === 'sqlite' ? 'SQLite (.sqlite)' : 'MySQL (.sql)' }) }}
          </div>
        </div>

        <!-- Restoring a dump this server did not make is only possible if it can
             be uploaded first. -->
        <div class="grid grid-cols-1 md:grid-cols-[240px_minmax(0,1fr)] gap-x-8 gap-y-3 py-5 border-t border-line">
          <div class="md:pr-4">
            <label class="block text-sm font-semibold text-fg">{{ t('settings.upload_backup') }}</label>
            <p class="text-sm text-muted mt-0.5">{{ t('settings.upload_backup_hint') }}</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <input
              ref="uploadInput"
              type="file"
              accept=".sql,.sqlite"
              @change="onUploadChange"
              class="flex-1 min-w-[240px] sm:max-w-sm block text-sm text-muted file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand file:text-white hover:file:bg-brand-dark file:cursor-pointer"
            />
            <button type="button" @click="submitUpload" :disabled="!uploadFile || uploading" class="btn-primary btn-sm flex-shrink-0 disabled:opacity-50">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="17 8 12 3 7 8" /><line x1="12" y1="3" x2="12" y2="15" /></svg>
              {{ uploading ? t('settings.uploading') : t('settings.upload') }}
            </button>
            <button v-if="uploadFile" type="button" @click="clearUploadSelection" class="btn-ghost btn-sm flex-shrink-0">
              {{ t('common.cancel') }}
            </button>
          </div>
        </div>

        <!-- The saved-files list spans the full width rather than sitting in the
             control column: the row carries a filename, size, date and three
             actions, which a 1fr column cannot hold without scrolling. -->
        <div class="py-6 border-t border-line">
          <h3 class="text-sm font-semibold text-fg mb-3">{{ t('settings.saved_backups') }}</h3>

          <!-- overflow-x-auto is load-bearing: this row cannot shrink (mono
               filename plus three whitespace-nowrap cells), so without it the
               Restore and Delete buttons are clipped off-screen and
               unreachable on a phone rather than scrolled to. -->
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
                  <!-- Row actions match every other table in the app: square
                       icon buttons, destructive one in the danger variant. The
                       label moves to the tooltip/aria-label. -->
                  <td class="text-right pr-5 whitespace-nowrap">
                    <div class="flex items-center justify-end gap-1.5">
                      <button
                        type="button"
                        @click="downloadBackup(b.name)"
                        :title="t('settings.download')"
                        :aria-label="t('settings.download')"
                        class="btn-icon"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
                      </button>
                      <button
                        type="button"
                        @click="pendingRestore = b.name"
                        :disabled="b.restorable === false"
                        :title="b.restorable === false ? t('settings.wrong_format_hint') : t('settings.restore')"
                        :aria-label="t('settings.restore')"
                        class="btn-icon"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10" /><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10" /></svg>
                      </button>
                      <button
                        type="button"
                        @click="pendingDelete = b.name"
                        :title="t('settings.delete')"
                        :aria-label="t('settings.delete')"
                        class="btn-icon-danger"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="text-sm text-faint">{{ t('settings.no_backups') }}</p>
        </div>
      </div>
    </div>

    <ConfirmDialog
      v-if="pendingRestore"
      :title="t('settings.restore_title')"
      :message="t('settings.restore_message')"
      :confirm-label="t('settings.restore')"
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
