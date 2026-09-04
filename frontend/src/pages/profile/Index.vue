<script setup>
import { ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import { useAuthStore } from '../../stores/auth'
import AppLayout from '../../layouts/AppLayout.vue'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const auth = useAuthStore()
const toast = useToastStore()

const form = reactive({
  name: auth.user?.name || '',
  phone: auth.user?.phone || '',
})
const photoFile = ref(null)
const photoPreview = ref(auth.user?.photo_url || null)
const savingProfile = ref(false)

function pickPhoto(e) {
  const file = e.target.files[0]
  if (!file) return
  photoFile.value = file
  photoPreview.value = URL.createObjectURL(file)
}

async function saveProfile() {
  savingProfile.value = true
  try {
    const fd = new FormData()
    fd.append('name', form.name)
    fd.append('phone', form.phone || '')
    if (photoFile.value) fd.append('photo', photoFile.value)

    const { data } = await http.post('/profile', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    auth.setUser(data)
    photoFile.value = null
    toast.success(t('profile.profile_updated'))
  } catch (e) {
    toast.error(errorMessage(e, t('profile.update_failed')))
  } finally {
    savingProfile.value = false
  }
}

const receiveReports = ref(auth.user?.receive_reports ?? true)
const savingPreferences = ref(false)

async function savePreferences() {
  savingPreferences.value = true
  try {
    const fd = new FormData()
    fd.append('name', auth.user?.name || '')
    fd.append('phone', auth.user?.phone || '')
    fd.append('receive_reports', receiveReports.value ? '1' : '0')
    const { data } = await http.post('/profile', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    auth.setUser(data)
    toast.success(t('profile.preference_saved'))
  } catch (e) {
    toast.error(errorMessage(e, t('profile.preference_save_failed')))
  } finally {
    savingPreferences.value = false
  }
}

const passwordForm = reactive({ current_password: '', password: '', password_confirmation: '' })
const savingPassword = ref(false)

async function changePassword() {
  savingPassword.value = true
  try {
    await http.post('/profile/password', passwordForm)
    passwordForm.current_password = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
    toast.success(t('profile.password_changed'))
  } catch (e) {
    toast.error(e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || t('profile.password_change_failed'))
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 max-w-2xl mx-auto space-y-6">
      <div>
        <h1 class="font-display text-2xl font-bold text-fg">{{ t('profile.title') }}</h1>
        <p class="text-muted text-sm mt-1">{{ t('profile.subtitle') }}</p>
      </div>

      <form @submit.prevent="saveProfile" class="card p-6 space-y-5">
        <h2 class="font-bold text-fg">{{ t('profile.profile_information') }}</h2>

        <div class="flex items-center gap-4">
          <div class="w-16 h-16 rounded-full overflow-hidden bg-brand text-white flex items-center justify-center text-lg font-bold flex-shrink-0">
            <img v-if="photoPreview" :src="photoPreview" class="w-full h-full object-cover" alt="" />
            <span v-else>{{ auth.user?.name?.[0]?.toUpperCase() }}</span>
          </div>
          <label class="btn-ghost btn-sm cursor-pointer">
            {{ t('profile.change_photo') }}
            <input type="file" accept="image/*" class="hidden" @change="pickPhoto" />
          </label>
        </div>

        <div class="space-y-1.5">
          <label class="label">{{ t('common.name') }}</label>
          <input v-model="form.name" class="input" required />
        </div>
        <div class="space-y-1.5">
          <label class="label">{{ t('common.email') }}</label>
          <input :value="auth.user?.email" class="input" disabled />
        </div>
        <div class="space-y-1.5">
          <label class="label">{{ t('common.phone') }}</label>
          <input v-model="form.phone" class="input" />
        </div>
        <div class="space-y-1.5">
          <label class="label">{{ t('profile.role') }}</label>
          <input :value="auth.user?.role?.replace('_', ' ')" class="input capitalize" disabled />
        </div>

        <button type="submit" :disabled="savingProfile" class="btn-primary">
          {{ savingProfile ? t('profile.saving') : t('profile.save_changes') }}
        </button>
      </form>

      <!-- Theme and language pickers were removed from here; light/dark lives in
           the header toggle. The card now carries only the staff report opt-in,
           so it renders for staff alone rather than as an empty heading. -->
      <div v-if="auth.user?.role === 'staff'" class="card p-6 space-y-5">
        <h2 class="font-bold text-fg">{{ t('profile.preferences') }}</h2>

        <form @submit.prevent="savePreferences" class="space-y-3">
          <label class="flex items-start gap-2.5 text-sm text-muted select-none cursor-pointer">
            <input type="checkbox" v-model="receiveReports" class="mt-0.5 rounded border-line text-brand focus:ring-brand/30" />
            <span>
              {{ t('profile.receive_reports') }}
              <span class="block text-xs text-faint mt-0.5">{{ t('profile.receive_reports_hint') }}</span>
            </span>
          </label>
          <button type="submit" :disabled="savingPreferences" class="btn-primary btn-sm">
            {{ savingPreferences ? t('profile.saving') : t('profile.save_preference') }}
          </button>
        </form>
      </div>

      <form @submit.prevent="changePassword" class="card p-6 space-y-5">
        <h2 class="font-bold text-fg">{{ t('profile.change_password') }}</h2>
        <div class="space-y-1.5">
          <label class="label">{{ t('profile.current_password') }}</label>
          <input v-model="passwordForm.current_password" type="password" class="input" required />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="label">{{ t('profile.new_password') }}</label>
            <input v-model="passwordForm.password" type="password" class="input" required minlength="8" />
          </div>
          <div class="space-y-1.5">
            <label class="label">{{ t('profile.confirm_password') }}</label>
            <input v-model="passwordForm.password_confirmation" type="password" class="input" required minlength="8" />
          </div>
        </div>
        <button type="submit" :disabled="savingPassword" class="btn-primary">
          {{ savingPassword ? t('profile.updating') : t('profile.update_password') }}
        </button>
      </form>
    </div>
  </AppLayout>
</template>
