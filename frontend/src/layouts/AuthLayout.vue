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
    <div class="hidden lg:flex w-1/2 relative overflow-hidden bg-brand-800 text-white flex-col justify-center p-12">
      <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-brand-light/30 blur-3xl"></div>
      <div class="absolute -bottom-32 -left-16 w-96 h-96 rounded-full bg-accent/10 blur-3xl"></div>

      <!-- Confetti accents. Drawn from the accent token and white tints rather
           than the reference's primary/secondary rainbow, so the panel keeps
           the brand palette. -->
      <div class="absolute w-[18px] h-[18px] rounded-full bg-accent/70" style="top: 26%; left: 8%"></div>
      <div class="absolute w-2.5 h-[18px] rounded-full bg-white/25" style="top: 24%; left: 22%; transform: rotate(20deg)"></div>
      <div class="absolute w-3.5 h-6 rounded-full bg-accent-light/50" style="top: 30%; right: 35%; transform: rotate(-30deg)"></div>
      <div class="absolute w-4 h-4 rounded-full bg-white/20" style="top: 59%; left: 6%"></div>
      <div class="absolute w-3 h-3 rounded-full bg-accent/50" style="top: 54%; right: 22%"></div>
      <div class="absolute w-4 h-6 rounded-full bg-white/15" style="top: 40%; right: 8%; transform: rotate(45deg)"></div>

      <div class="relative text-center">
        <h2 class="font-display text-3xl font-bold leading-snug">{{ t('login.tagline') }}</h2>
        <p class="text-white/60 mt-4 max-w-md mx-auto">
          {{ t('login.tagline_subtitle') }}
        </p>

        <!-- Carousel indicators from the reference. Decorative only: there is
             one panel, so the lead dot is simply the active one. -->
        <div class="flex items-center justify-center gap-1.5 mt-8" aria-hidden="true">
          <span class="w-[18px] h-1.5 rounded bg-white"></span>
          <span class="w-1.5 h-1.5 rounded-full bg-white/40"></span>
          <span class="w-1.5 h-1.5 rounded-full bg-white/40"></span>
        </div>
      </div>

      <!-- Pinned to the bottom so the hero can sit centred in the panel. -->
      <p class="absolute bottom-12 left-0 right-0 text-center text-white/40 text-xs">{{ t('login.copyright', { year: new Date().getFullYear() }) }}</p>
    </div>

    <!-- Form panel -->
    <div class="flex-1 flex flex-col items-center justify-center px-4 py-12 relative">
      <div class="absolute top-4 right-4">
        <ThemeToggle />
      </div>

      <div class="w-full max-w-lg">
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
