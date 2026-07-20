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
      <h1 class="font-display text-2xl font-bold text-fg">{{ t('login.welcome') }}</h1>
      <p class="text-muted text-sm mt-1">{{ t('login.subtitle') }}</p>
    </div>

    <form @submit.prevent="handleSubmit" class="card p-6 space-y-4">
      <div v-if="error" class="flex items-start gap-2 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300 text-sm px-3 py-2.5 rounded-xl">
        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
        <span>{{ error }}</span>
      </div>

      <div>
        <label class="label">{{ t('login.email') }}</label>
        <input v-model="email" type="email" required autofocus placeholder="you@ams.com" class="input" />
      </div>

      <div>
        <label class="label">{{ t('login.password') }}</label>
        <input v-model="password" type="password" required placeholder="••••••••" class="input" />
      </div>

      <button type="submit" :disabled="loading" class="btn-primary w-full">
        <svg v-if="loading" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
        {{ loading ? t('login.signing_in') : t('login.submit') }}
      </button>
    </form>
  </AuthLayout>
</template>
