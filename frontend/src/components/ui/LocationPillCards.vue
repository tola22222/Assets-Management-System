<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
defineProps({
  locations: { type: Array, required: true }, // [{ location, total }]
})
</script>

<template>
  <!-- At most five tiles per row: eight made each tile too narrow for a site
       name, cutting "Banteay Srei HS" to "BANTEAY S…". Names are in normal case
       and wrap instead of truncating. Each tile is a column with the count
       pushed to the bottom, and grid rows stretch tiles to equal height, so the
       counts in a row stay level when one name takes two lines. -->
  <div v-if="locations.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
    <div v-for="loc in locations" :key="loc.location" class="flex flex-col bg-surface-2 border border-line rounded-xl px-3.5 py-3">
      <p class="text-[13px] font-semibold text-muted leading-snug break-words">{{ loc.location }}</p>
      <p class="font-display text-2xl font-bold text-fg mt-auto pt-1">{{ loc.total }}</p>
    </div>
  </div>
  <div v-else class="text-sm text-faint">{{ t('dashboard.no_stock') }}</div>
</template>
