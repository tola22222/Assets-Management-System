<script setup>
import { ref, reactive, watch } from 'vue'
import http from '../../api/http'
import { useAuthStore } from '../../stores/auth'
import { useToastStore } from '../../stores/toast'

const auth = useAuthStore()
const toast = useToastStore()

const form = reactive({
  name: auth.user?.name || '',
  phone: auth.user?.phone || '',
})
const photoFile = ref(null)
const photoPreview = ref(auth.user?.photo_url || null)
const savingProfile = ref(false)

watch(photoFile, (file) => {
  if (file) photoPreview.value = URL.createObjectURL(file)
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
    toast.success('Profile updated successfully.')
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not update profile.')
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
    toast.success('Password changed successfully.')
  } catch (e) {
    toast.error(e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Could not change password.')
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <v-container fluid class="pa-0 d-flex flex-column ga-6" style="max-width: 640px">
      <div>
        <h1 class="text-h5 font-weight-bold">My Profile</h1>
        <p class="text-body-2 text-medium-emphasis mt-1">Update your personal information and password.</p>
      </div>

      <v-card rounded="lg" variant="flat" border class="pa-6">
        <v-form @submit.prevent="saveProfile">
          <div class="d-flex flex-column ga-5">
            <h2 class="text-subtitle-1 font-weight-bold">Profile Information</h2>

            <div class="d-flex align-center ga-4">
              <v-avatar :image="photoPreview" color="primary" size="64">
                <span v-if="!photoPreview" class="text-h6 font-weight-bold">{{ auth.user?.name?.[0]?.toUpperCase() }}</span>
              </v-avatar>
              <v-file-input
                v-model="photoFile"
                accept="image/*"
                variant="outlined"
                density="compact"
                hide-details
                prepend-icon=""
                prepend-inner-icon="mdi-camera-outline"
                label="Change photo"
                style="max-width: 260px"
              />
            </div>

            <v-text-field v-model="form.name" label="Name" required />
            <v-text-field :model-value="auth.user?.email" label="Email" disabled />
            <v-text-field v-model="form.phone" label="Phone" />
            <v-text-field :model-value="auth.user?.role?.replace('_', ' ')" label="Role" disabled class="text-capitalize" />

            <v-btn type="submit" color="primary" variant="flat" :loading="savingProfile" class="align-self-start">Save Changes</v-btn>
          </div>
        </v-form>
      </v-card>

      <v-card rounded="lg" variant="flat" border class="pa-6">
        <v-form @submit.prevent="changePassword">
          <div class="d-flex flex-column ga-5">
            <h2 class="text-subtitle-1 font-weight-bold">Change Password</h2>
            <v-text-field v-model="passwordForm.current_password" type="password" label="Current Password" required />
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-text-field v-model="passwordForm.password" type="password" label="New Password" required minlength="8" />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field v-model="passwordForm.password_confirmation" type="password" label="Confirm Password" required minlength="8" />
              </v-col>
            </v-row>
            <v-btn type="submit" color="primary" variant="flat" :loading="savingPassword" class="align-self-start">Update Password</v-btn>
          </div>
        </v-form>
      </v-card>
  </v-container>
</template>
