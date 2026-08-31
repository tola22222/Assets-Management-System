<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ThemeToggle from '../components/ui/ThemeToggle.vue'
import logoUrl from '../assets/logo/Official PEPY Logo_Green.png'
import { useBranding } from '../composables/useBranding'

const { t } = useI18n()
const { systemName, organizationName, logoUrl: brandLogoUrl } = useBranding()
// /branding is unauthenticated, so a custom logo shows on the login screen too.
const displayLogo = computed(() => brandLogoUrl.value || logoUrl)
</script>

<template>
  <div class="min-h-screen flex bg-canvas text-fg">
    <!-- Brand panel (desktop) -->
    <div class="hidden lg:flex w-1/2 relative overflow-hidden bg-brand-800 text-white flex-col justify-between p-12">
      <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-brand-light/30 blur-3xl"></div>
      <div class="absolute -bottom-32 -left-16 w-96 h-96 rounded-full bg-accent/10 blur-3xl"></div>

      <div class="relative flex items-center gap-3">
        <div class="w-11 h-11 flex items-center justify-center flex-shrink-0">
          <img :src="displayLogo" :alt="organizationName || 'PEPY'" class="w-full h-full object-contain" />
        </div>
        <div>
          <p class="font-display font-bold text-lg leading-tight">{{ systemName || t('app_name') }}</p>
          <p class="text-white/50 text-xs">{{ organizationName || `${t('app_subtitle')} · ${t('app_location')}` }}</p>
        </div>
      </div>

      <div class="relative">
        <h2 class="font-display text-3xl font-bold leading-snug">{{ t('login.tagline') }}</h2>
        <p class="text-white/60 mt-4 max-w-md">
          {{ t('login.tagline_subtitle') }}
        </p>
      </div>

      <p class="relative text-white/40 text-xs">{{ t('login.copyright', { year: new Date().getFullYear() }) }}</p>
    </div>

    <!-- Form panel -->
    <div class="flex-1 flex flex-col items-center justify-center px-4 py-10 relative">
      <div class="absolute top-4 right-4">
        <ThemeToggle />
      </div>

      <div class="w-full max-w-sm">
        <div class="flex items-center gap-3 mb-8 lg:hidden">
          <div class="w-10 h-10 flex items-center justify-center flex-shrink-0">
            <img :src="displayLogo" :alt="organizationName || 'PEPY'" class="w-full h-full object-contain" />
          </div>
          <div>
            <p class="font-display font-bold text-brand-600 dark:text-brand-300 text-lg leading-tight">{{ systemName || t('app_name') }}</p>
            <p class="text-faint text-xs">{{ organizationName || t('app_subtitle') }}</p>
          </div>
        </div>
        <slot />
      </div>
    </div>
  </div>
</template>
