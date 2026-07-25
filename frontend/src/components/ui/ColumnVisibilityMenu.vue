<script setup>
import { useI18n } from 'vue-i18n'
import DropdownMenu from './DropdownMenu.vue'

const { t } = useI18n()

defineProps({
  columns: { type: Array, required: true }, // [{ key, label }]
  isVisible: { type: Function, required: true },
})
const emit = defineEmits(['toggle', 'reset'])
</script>

<template>
  <DropdownMenu align="right">
    <template #trigger>
      <button class="btn-ghost btn-sm" type="button">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18M6 9h12M9 13.5h6M11 18h2" /></svg>
        {{ t('common.columns') }}
      </button>
    </template>

    <div class="px-3 py-2 text-xs font-semibold text-faint uppercase tracking-wide">{{ t('common.toggle_columns') }}</div>
    <label
      v-for="col in columns"
      :key="col.key"
      class="flex items-center gap-2.5 px-3 py-1.5 text-sm text-fg hover:bg-surface-2 cursor-pointer"
      @click.stop
    >
      <input type="checkbox" :checked="isVisible(col.key)" @change="emit('toggle', col.key)" class="rounded border-line-strong text-brand focus:ring-brand/30" />
      {{ col.label }}
    </label>
    <div class="border-t border-line mt-1 pt-1">
      <button type="button" class="w-full text-left px-3 py-1.5 text-sm text-muted hover:bg-surface-2" @click="emit('reset')">
        {{ t('common.reset_columns') }}
      </button>
    </div>
  </DropdownMenu>
</template>
