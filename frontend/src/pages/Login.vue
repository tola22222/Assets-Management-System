<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import AuthLayout from '../layouts/AuthLayout.vue'

const { t } = useI18n()
const toast = useToastStore()
const email = ref('')
const password = ref('')
const showPassword = ref(false)
const rememberMe = ref(true)
const error = ref('')
const loading = ref(false)
const form = ref(null)

const rules = {
  email: [(v) => !!v || t('login.email_required'), (v) => /.+@.+\..+/.test(v) || t('login.email_invalid')],
  password: [(v) => !!v || t('login.password_required')],
}

const auth = useAuthStore()
const router = useRouter()

async function handleSubmit() {
  const { valid } = await form.value.validate()
  if (!valid) return

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

function handleForgotPassword() {
  toast.info(t('login.forgot_password_notice'))
}
</script>

<template>
  <AuthLayout>
    <div class="mb-6">
      <h1 class="text-h5 font-weight-bold font-display">{{ t('login.welcome') }}</h1>
      <p class="text-body-2 text-medium-emphasis mt-1">{{ t('login.subtitle') }}</p>
    </div>

    <v-card rounded="lg" variant="flat" border class="pa-6">
      <v-form ref="form" @submit.prevent="handleSubmit">
        <div class="d-flex flex-column ga-4">
          <v-alert v-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>

          <v-text-field
            v-model="email"
            type="email"
            :label="t('login.email')"
            :rules="rules.email"
            autofocus
            placeholder="you@ams.com"
          />
          <v-text-field
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            :label="t('login.password')"
            :rules="rules.password"
            :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
            placeholder="••••••••"
            @click:append-inner="showPassword = !showPassword"
          />

          <div class="d-flex align-center justify-space-between">
            <v-checkbox v-model="rememberMe" :label="t('login.remember_me')" density="compact" hide-details />
            <v-btn variant="text" size="small" class="text-none" @click="handleForgotPassword">{{ t('login.forgot_password') }}</v-btn>
          </div>

          <v-btn type="submit" color="primary" variant="flat" block size="large" :loading="loading">{{ t('login.submit') }}</v-btn>
        </div>
      </v-form>
    </v-card>
  </AuthLayout>
</template>
