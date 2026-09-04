<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AuthLayout from '../layouts/AuthLayout.vue'
import logoUrl from '../assets/logo/Official PEPY Logo_Green.png'
import { useBranding } from '../composables/useBranding'

const { t } = useI18n()
const email = ref('')
const password = ref('')
const remember = ref(false)
const error = ref('')
const loading = ref(false)
const showPassword = ref(false)

// /branding is unauthenticated, so a custom logo shows on the login screen too.
const { logoUrl: brandLogoUrl } = useBranding()
const displayLogo = computed(() => brandLogoUrl.value || logoUrl)

const auth = useAuthStore()
const router = useRouter()

async function handleSubmit() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value, remember.value)
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
    <div class="text-center">
      <!-- Brand mark above the form. Desktop only: below lg the layout already
           shows a logo and the org name in its own header, and two marks
           stacked reads as a mistake. -->
      <div class="hidden lg:flex w-[72px] h-[72px] rounded-full bg-brand/10 dark:bg-white/10 mx-auto mb-6 items-center justify-center overflow-hidden">
        <img :src="displayLogo" alt="" class="w-12 h-12 object-contain" />
      </div>

      <h1 class="font-display text-[26px] font-bold text-fg">{{ t('login.welcome') }}</h1>
      <p class="text-muted text-sm mt-2 mb-8">{{ t('login.subtitle') }}</p>
    </div>

    <form @submit.prevent="handleSubmit" class="text-left">
      <div v-if="error" class="flex items-start gap-2 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300 text-sm px-3.5 py-3 rounded-xl mb-4">
        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
        <span>{{ error }}</span>
      </div>

      <!-- Placeholder-as-label fields, with the hint icon inside on the right. -->
      <div class="relative mb-4">
        <input
          v-model="email"
          type="email"
          required
          autofocus
          :placeholder="t('login.email')"
          class="input h-12 pr-11"
        />
        <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-faint pointer-events-none">
          <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
        </span>
      </div>

      <div class="relative mb-4">
        <input
          v-model="password"
          :type="showPassword ? 'text' : 'password'"
          required
          :placeholder="t('login.password')"
          class="input h-12 pr-11"
        />
        <!-- type="button" matters: a bare button inside the form would submit
             it, so revealing the password would attempt a sign-in. -->
        <button
          type="button"
          @click="showPassword = !showPassword"
          :title="showPassword ? t('login.hide_password') : t('login.show_password')"
          :aria-label="showPassword ? t('login.hide_password') : t('login.show_password')"
          :aria-pressed="showPassword"
          class="absolute right-3.5 top-1/2 -translate-y-1/2 text-faint hover:text-muted transition focus:outline-none focus-visible:text-brand"
        >
          <svg v-if="showPassword" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
          <svg v-else class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
        </button>
      </div>

      <!-- Options row. The mockup pairs the checkbox with a recovery link, but
           this app has no self-service reset — only an admin can reset another
           account — so the slot carries that as plain text rather than a link
           that goes nowhere. -->
      <div class="flex flex-wrap items-center justify-between gap-2 text-xs mb-6">
        <label class="flex items-center gap-2 text-muted select-none cursor-pointer">
          <input v-model="remember" type="checkbox" class="w-4 h-4 rounded border-line text-brand focus:ring-brand/30" />
          {{ t('login.remember_me') }}
        </label>
        <span class="text-faint">{{ t('login.forgot_hint') }}</span>
      </div>

      <button type="submit" :disabled="loading" class="btn-primary w-full h-12">
        <svg v-if="loading" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
        {{ loading ? t('login.signing_in') : t('login.submit') }}
      </button>
    </form>
  </AuthLayout>
</template>
