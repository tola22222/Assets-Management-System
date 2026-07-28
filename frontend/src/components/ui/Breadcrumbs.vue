<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
  section: { type: String, default: null },
  page: { type: String, default: null },
})
</script>

<template>
  <nav v-if="page" class="flex items-center gap-1.5 text-sm min-w-0" aria-label="Breadcrumb">
    <RouterLink to="/" class="text-faint hover:text-fg transition flex items-center gap-1.5 flex-shrink-0">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" /></svg>
      <span class="hidden sm:inline">{{ t('nav.dashboard') }}</span>
    </RouterLink>

    <template v-if="section">
      <svg class="w-3.5 h-3.5 text-faint flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
      <span class="text-faint truncate hidden sm:inline">{{ section }}</span>
    </template>

    <svg class="w-3.5 h-3.5 text-faint flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
    <span class="text-fg font-semibold truncate">{{ page }}</span>
  </nav>
</template>
