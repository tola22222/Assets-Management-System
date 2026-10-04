<script setup>
import { ref, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '../../api/http'
import { useAuthStore } from '../../stores/auth'
import AppLayout from '../../layouts/AppLayout.vue'
import ImageField from '../../components/ui/ImageField.vue'
import { useToastStore } from '../../stores/toast'

const { t } = useI18n()
const auth = useAuthStore()
const toast = useToastStore()

const form = reactive({
  name: auth.user?.name || '',
  phone: auth.user?.phone || '',
})
// The same photo box as the other forms: drag & drop or browse, then crop
// (square, for the round avatar).
const photoFile = ref(null)
const savingProfile = ref(false)
// No photo uploaded yet: the account shows a generated letter avatar, which is
// not a "current photo" — leave the empty drop box instead.
const uploadedPhoto = computed(() => {
  const url = auth.user?.photo_url
  return url && !url.includes('ui-avatars.com') ? url : null
})

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

        <div class="form-group">
          <label class="label">{{ t('staff.photo') }}</label>
          <ImageField v-model="photoFile" :existing="uploadedPhoto" :aspect="1" :hint="t('image.field_hint')" />
        </div>

        <div class="form-group">
          <label class="label">{{ t('common.name') }}</label>
          <input v-model="form.name" class="input" required />
        </div>
        <div class="form-group">
          <label class="label">{{ t('common.email') }}</label>
          <input :value="auth.user?.email" class="input" disabled />
        </div>
        <div class="form-group">
          <label class="label">{{ t('common.phone') }}</label>
          <input v-model="form.phone" class="input" />
        </div>
        <div class="form-group">
          <label class="label">{{ t('profile.role') }}</label>
          <input :value="auth.user?.role?.replace('_', ' ')" class="input capitalize" disabled />
        </div>

        <button type="submit" :disabled="savingProfile" class="btn-primary">
          {{ savingProfile ? t('profile.saving') : t('profile.save_changes') }}
        </button>
      </form>

      <form @submit.prevent="changePassword" class="card p-6 space-y-5">
        <h2 class="font-bold text-fg">{{ t('profile.change_password') }}</h2>
        <div class="form-group">
          <label class="label">{{ t('profile.current_password') }}</label>
          <input v-model="passwordForm.current_password" type="password" class="input" required />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="form-group">
            <label class="label">{{ t('profile.new_password') }}</label>
            <input v-model="passwordForm.password" type="password" class="input" required minlength="8" />
          </div>
          <div class="form-group">
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
