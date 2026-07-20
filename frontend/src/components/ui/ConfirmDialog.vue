<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
defineProps({
  title: { type: String, default: null },
  message: { type: String, default: null },
})
const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
  <div class="overlay items-center justify-center z-[150]" @click.self="emit('cancel')">
    <div class="modal-panel max-w-sm p-6 text-center">
      <div class="w-14 h-14 mx-auto bg-red-100 dark:bg-red-500/15 rounded-full flex items-center justify-center">
        <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
      </div>
      <h3 class="text-lg font-bold text-fg mt-4">{{ title ?? t('confirm.delete_title') }}</h3>
      <p class="text-sm text-muted mt-2">{{ message ?? t('confirm.delete_message') }}</p>
      <div class="flex items-center justify-center gap-3 mt-6">
        <button @click="emit('cancel')" class="btn-ghost px-6">{{ t('common.cancel') }}</button>
        <button @click="emit('confirm')" class="btn-danger px-6">{{ t('common.delete') }}</button>
      </div>
    </div>
  </div>
</template>
