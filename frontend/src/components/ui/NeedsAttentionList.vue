<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
defineProps({
  items: { type: Array, required: true }, // [{ name, code, reason, severity }]
})

const dot = {
  danger: 'bg-red-500',
  warning: 'bg-amber-500',
  info: 'bg-brand',
}
</script>

<template>
  <ul class="divide-y divide-dashed divide-line">
    <li v-for="(item, i) in items" :key="i" class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
      <div class="flex items-center gap-2.5 min-w-0">
        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :class="dot[item.severity] || 'bg-faint'"></span>
        <span class="font-semibold text-fg text-sm truncate">{{ item.name }}</span>
        <span v-if="item.code" class="font-mono text-[11px] text-faint truncate flex-shrink-0">{{ item.code }}</span>
      </div>
      <span class="text-xs text-muted flex-shrink-0 whitespace-nowrap">{{ item.reason }}</span>
    </li>
    <li v-if="!items.length" class="py-6 text-center text-sm text-faint">{{ t('dashboard.no_attention') }}</li>
  </ul>
</template>
