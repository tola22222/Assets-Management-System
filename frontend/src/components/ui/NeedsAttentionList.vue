<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
defineProps({
  items: { type: Array, required: true }, // [{ name, code, reason, severity }]
})

const dot = {
  danger: 'error',
  warning: 'warning',
  info: 'primary',
}
</script>

<template>
  <ul class="d-flex flex-column pl-0" style="list-style: none">
    <li
      v-for="(item, i) in items"
      :key="i"
      class="d-flex align-center justify-space-between ga-3 py-2"
      :class="{ 'border-b border-dashed': i < items.length - 1 }"
    >
      <div class="d-flex align-center ga-2 flex-grow-1" style="min-width: 0">
        <span class="rounded-circle flex-shrink-0" :class="`bg-${dot[item.severity] || 'surface-variant'}`" style="width: 6px; height: 6px"></span>
        <span class="font-weight-semibold text-body-2 text-truncate">{{ item.name }}</span>
        <span v-if="item.code" class="font-mono text-caption text-medium-emphasis text-truncate flex-shrink-0">{{ item.code }}</span>
      </div>
      <span class="text-caption text-medium-emphasis flex-shrink-0 text-no-wrap">{{ item.reason }}</span>
    </li>
    <li v-if="!items.length" class="py-6 text-center text-body-2 text-medium-emphasis">{{ t('dashboard.no_attention') }}</li>
  </ul>
</template>
