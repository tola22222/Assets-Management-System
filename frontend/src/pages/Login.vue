<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AuthLayout from '../layouts/AuthLayout.vue'

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
    error.value = e.response?.data?.message || 'The provided credentials do not match our records.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout>
    <form @submit.prevent="handleSubmit" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
      <h1 class="text-lg font-bold text-ink">Sign in</h1>

      <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2.5 rounded-xl">
        {{ error }}
      </div>

      <div class="space-y-1.5">
        <label class="text-xs font-semibold text-gray-600 tracking-wide">Email address</label>
        <input v-model="email" type="email" required
          class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition" />
      </div>

      <div class="space-y-1.5">
        <label class="text-xs font-semibold text-gray-600 tracking-wide">Password</label>
        <input v-model="password" type="password" required
          class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-brand transition" />
      </div>

      <button type="submit" :disabled="loading"
        class="w-full bg-brand hover:bg-brand-dark disabled:opacity-60 text-white font-semibold text-sm py-2.5 rounded-xl transition">
        {{ loading ? 'Signing in…' : 'Sign in' }}
      </button>
    </form>
  </AuthLayout>
</template>
