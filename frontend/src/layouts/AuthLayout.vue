<script setup>
import { useI18n } from 'vue-i18n'
import ThemeToggle from '../components/ui/ThemeToggle.vue'
import logoUrl from '../assets/logo/Official PEPY Logo_Green.png'

const { t } = useI18n()
</script>

<template>
  <v-container fluid class="fill-height pa-0">
    <v-row no-gutters class="fill-height" style="min-height: 100vh">
      <!-- Brand panel (desktop) -->
      <v-col
        cols="6"
        class="d-none d-lg-flex flex-column justify-space-between pa-12"
        style="background-color: rgb(var(--v-theme-primary-darken-1)); color: white; position: relative; overflow: hidden"
      >
        <div class="d-flex align-center ga-3" style="position: relative; z-index: 1">
          <v-avatar size="44" rounded="0">
            <v-img :src="logoUrl" alt="PEPY" />
          </v-avatar>
          <div>
            <p class="text-h6 font-weight-bold font-display mb-0" style="line-height: 1.2">{{ t('app_name') }}</p>
            <p class="text-caption mb-0" style="opacity: 0.5">{{ t('app_subtitle') }} · {{ t('app_location') }}</p>
          </div>
        </div>

        <div style="position: relative; z-index: 1">
          <h2 class="text-h4 font-weight-bold font-display" style="line-height: 1.25">{{ t('login.brand_headline') }}</h2>
          <p class="mt-4" style="opacity: 0.6; max-width: 28rem">{{ t('login.brand_tagline') }}</p>
        </div>

        <p class="text-caption mb-0" style="opacity: 0.4; position: relative; z-index: 1">
          © {{ new Date().getFullYear() }} PEPY · Asset Management System
        </p>
      </v-col>

      <!-- Form panel -->
      <v-col cols="12" lg="6" class="d-flex flex-column align-center justify-center px-4 py-10" style="position: relative">
        <div style="position: absolute; top: 16px; right: 16px">
          <ThemeToggle />
        </div>

        <div style="width: 100%; max-width: 24rem">
          <div class="d-flex d-lg-none align-center ga-3 mb-8">
            <v-avatar size="40" rounded="0">
              <v-img :src="logoUrl" alt="PEPY" />
            </v-avatar>
            <div>
              <p class="text-subtitle-1 font-weight-bold font-display text-primary mb-0" style="line-height: 1.2">{{ t('app_name') }}</p>
              <p class="text-caption text-medium-emphasis mb-0">{{ t('app_subtitle') }}</p>
            </div>
          </div>
          <slot />
        </div>
      </v-col>
    </v-row>
  </v-container>
</template>
