<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
defineProps({
  locations: { type: Array, required: true }, // [{ location, total }]
})
</script>

<template>
  <div v-if="locations.length" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
    <div v-for="loc in locations" :key="loc.location" class="bg-surface-2 border border-line rounded-xl px-3 py-3">
      <p class="text-[11px] font-semibold text-faint uppercase tracking-wide truncate" :title="loc.location">{{ loc.location }}</p>
      <p class="font-display text-2xl font-bold text-fg mt-0.5">{{ loc.total }}</p>
    </div>
  </div>
  <div v-else class="text-sm text-faint">{{ t('dashboard.no_stock') }}</div>
</template>
