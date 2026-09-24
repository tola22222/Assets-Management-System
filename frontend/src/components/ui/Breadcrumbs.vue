<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const { t } = useI18n()
const route = useRoute()

defineProps({
  section: { type: String, default: null },
  page: { type: String, default: null },
})

// The home crumb already says "Dashboard", so on the dashboard itself it is the
// whole trail — otherwise it read "Dashboard › Dashboard".
const atHome = computed(() => route.path === '/')
</script>

<template>
  <!-- Text only, all in the muted greys: no home icon, no chevron icons, and
       the current page is not picked out in the dark foreground colour. -->
  <nav v-if="page" class="flex items-center gap-1.5 text-sm min-w-0" :aria-label="t('common.breadcrumb')">
    <span v-if="atHome" class="text-muted font-medium truncate" aria-current="page">{{ page }}</span>

    <template v-else>
      <RouterLink to="/" class="text-faint hover:text-muted transition flex-shrink-0">{{ t('nav.dashboard') }}</RouterLink>

      <!-- Section and its separator hide together on phones, so the trail
           never shows two separators in a row. -->
      <template v-if="section">
        <span class="text-faint flex-shrink-0 hidden sm:inline" aria-hidden="true">/</span>
        <span class="text-faint truncate hidden sm:inline">{{ section }}</span>
      </template>

      <span class="text-faint flex-shrink-0" aria-hidden="true">/</span>
      <span class="text-muted font-medium truncate" aria-current="page">{{ page }}</span>
    </template>
  </nav>
</template>
