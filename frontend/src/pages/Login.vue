<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AuthLayout from '../layouts/AuthLayout.vue'

const { t } = useI18n()
const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

const auth = useAuthStore()
const router = useRouter()

async function handleSubmit() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    router.push({ name: 'dashboard' })
  } catch (e) {
    error.value = e.response?.data?.message || t('login.error')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout>
    <div class="mb-6">
      <h1 class="text-h5 font-weight-bold">{{ t('login.welcome') }}</h1>
      <p class="text-body-2 text-medium-emphasis mt-1">{{ t('login.subtitle') }}</p>
    </div>

    <v-card rounded="lg" variant="flat" border class="pa-6">
      <v-form @submit.prevent="handleSubmit">
        <div class="d-flex flex-column ga-4">
          <v-alert v-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>

          <v-text-field v-model="email" type="email" :label="t('login.email')" required autofocus placeholder="you@ams.com" />
          <v-text-field v-model="password" type="password" :label="t('login.password')" required placeholder="••••••••" />

          <v-btn type="submit" color="primary" variant="flat" block :loading="loading">{{ t('login.submit') }}</v-btn>
        </div>
      </v-form>
    </v-card>
  </AuthLayout>
</template>
