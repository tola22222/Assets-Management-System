<script setup>
import { ref, reactive } from 'vue'
import http from '../../api/http'
import { useAuthStore } from '../../stores/auth'
import AppLayout from '../../layouts/AppLayout.vue'
import { useToastStore } from '../../stores/toast'
import { useLocale } from '../../composables/useLocale'
import { useTheme } from '../../composables/useTheme'

const auth = useAuthStore()
const toast = useToastStore()
const { locale, setLocale } = useLocale()
const { isDark, setDark, setLight } = useTheme()

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
    toast.success('Profile updated successfully.')
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not update profile.')
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
    toast.success('Preference saved.')
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not save preference.')
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
    toast.success('Password changed successfully.')
  } catch (e) {
    toast.error(e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Could not change password.')
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <AppLayout>
    <div class="p-6 sm:p-8 max-w-2xl mx-auto space-y-6">
      <div>
        <h1 class="font-display text-2xl font-bold text-fg">My Profile</h1>
        <p class="text-muted text-sm mt-1">Update your personal information and password.</p>
      </div>

      <form @submit.prevent="saveProfile" class="card p-6 space-y-5">
        <h2 class="font-bold text-fg">Profile Information</h2>

        <div class="flex items-center gap-4">
          <div class="w-16 h-16 rounded-full overflow-hidden bg-brand text-white flex items-center justify-center text-lg font-bold flex-shrink-0">
            <img v-if="photoPreview" :src="photoPreview" class="w-full h-full object-cover" alt="" />
            <span v-else>{{ auth.user?.name?.[0]?.toUpperCase() }}</span>
          </div>
          <label class="btn-ghost btn-sm cursor-pointer">
            Change photo
            <input type="file" accept="image/*" class="hidden" @change="pickPhoto" />
          </label>
        </div>

        <div class="space-y-1.5">
          <label class="label">Name</label>
          <input v-model="form.name" class="input" required />
        </div>
        <div class="space-y-1.5">
          <label class="label">Email</label>
          <input :value="auth.user?.email" class="input" disabled />
        </div>
        <div class="space-y-1.5">
          <label class="label">Phone</label>
          <input v-model="form.phone" class="input" />
        </div>
        <div class="space-y-1.5">
          <label class="label">Role</label>
          <input :value="auth.user?.role?.replace('_', ' ')" class="input capitalize" disabled />
        </div>

        <button type="submit" :disabled="savingProfile" class="btn-primary">
          {{ savingProfile ? 'Saving…' : 'Save Changes' }}
        </button>
      </form>

      <div class="card p-6 space-y-5">
        <h2 class="font-bold text-fg">Preferences</h2>

        <div class="space-y-1.5">
          <label class="label">Color Theme</label>
          <div class="flex items-center gap-1 bg-surface-2 rounded-xl p-1 w-fit">
            <button type="button" @click="setLight()" class="px-4 py-1.5 rounded-lg text-xs font-semibold transition" :class="!isDark ? 'bg-brand text-white' : 'text-muted hover:text-fg'">Light</button>
            <button type="button" @click="setDark()" class="px-4 py-1.5 rounded-lg text-xs font-semibold transition" :class="isDark ? 'bg-brand text-white' : 'text-muted hover:text-fg'">Dark</button>
          </div>
        </div>

        <div class="space-y-1.5">
          <label class="label">Language</label>
          <select :value="locale" @change="setLocale($event.target.value)" class="select">
            <option value="en">English</option>
            <option value="km">ខ្មែរ</option>
          </select>
        </div>

        <form v-if="auth.user?.role === 'staff'" @submit.prevent="savePreferences" class="space-y-3 pt-2 border-t border-line">
          <label class="flex items-start gap-2.5 text-sm text-muted select-none cursor-pointer">
            <input type="checkbox" v-model="receiveReports" class="mt-0.5 rounded border-line text-brand focus:ring-brand/30" />
            <span>
              Receive scheduled report emails
              <span class="block text-xs text-faint mt-0.5">Only matters once an admin turns on "Include Staff in scheduled email reports" in Settings — this lets you personally opt out even when that's on.</span>
            </span>
          </label>
          <button type="submit" :disabled="savingPreferences" class="btn-primary btn-sm">
            {{ savingPreferences ? 'Saving…' : 'Save Preference' }}
          </button>
        </form>
      </div>

      <form @submit.prevent="changePassword" class="card p-6 space-y-5">
        <h2 class="font-bold text-fg">Change Password</h2>
        <div class="space-y-1.5">
          <label class="label">Current Password</label>
          <input v-model="passwordForm.current_password" type="password" class="input" required />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="label">New Password</label>
            <input v-model="passwordForm.password" type="password" class="input" required minlength="8" />
          </div>
          <div class="space-y-1.5">
            <label class="label">Confirm Password</label>
            <input v-model="passwordForm.password_confirmation" type="password" class="input" required minlength="8" />
          </div>
        </div>
        <button type="submit" :disabled="savingPassword" class="btn-primary">
          {{ savingPassword ? 'Updating…' : 'Update Password' }}
        </button>
      </form>
    </div>
  </AppLayout>
</template>
