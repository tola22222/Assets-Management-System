<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
// `tone` picks the icon and confirm-button colour: 'danger' (the default) for
// the destructive confirms this dialog was written for, 'primary' for a plain
// "are you sure?" — an apply/save step drawn in red with a Delete button reads
// as data loss. Always pass `confirmLabel` for a non-delete action: the button
// falls back to common.delete.
const props = defineProps({
  title: { type: String, default: null },
  message: { type: String, default: null },
  confirmLabel: { type: String, default: null },
  tone: { type: String, default: 'danger' },
})
const emit = defineEmits(['confirm', 'cancel'])

const isDanger = computed(() => props.tone !== 'primary')
</script>

<template>
  <div class="overlay items-center justify-center z-[150]" @click.self="emit('cancel')">
    <div class="modal-panel max-w-sm p-6 text-center">
      <div
        class="w-14 h-14 mx-auto rounded-full flex items-center justify-center"
        :class="isDanger ? 'bg-red-100 dark:bg-red-500/15' : 'bg-brand-100 dark:bg-brand-500/15'"
      >
        <svg v-if="isDanger" class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        <svg v-else class="w-7 h-7 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>
      </div>
      <h3 class="text-lg font-bold text-fg mt-4">{{ title ?? t('confirm.delete_title') }}</h3>
      <p class="text-sm text-muted mt-2">{{ message ?? t('confirm.delete_message') }}</p>
      <div class="flex items-center justify-center gap-3 mt-6">
        <button @click="emit('cancel')" class="btn-ghost px-6">{{ t('common.cancel') }}</button>
        <button @click="emit('confirm')" class="px-6" :class="isDanger ? 'btn-danger' : 'btn-primary'">
          {{ confirmLabel ?? t('common.delete') }}
        </button>
      </div>
    </div>
  </div>
</template>
