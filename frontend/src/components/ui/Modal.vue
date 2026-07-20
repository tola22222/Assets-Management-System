<script setup>
defineProps({
  title: { type: String, required: true },
  wide: { type: Boolean, default: false },
})
const emit = defineEmits(['close'])
</script>

<template>
  <div class="overlay items-center justify-center sm:justify-end" @click.self="emit('close')">
    <div class="modal-panel" :class="wide ? 'max-w-2xl' : 'max-w-lg'">
      <div class="px-6 py-5 border-b border-line flex items-center justify-between">
        <h3 class="text-lg font-bold text-fg">{{ title }}</h3>
        <button @click="emit('close')" class="text-faint hover:text-fg transition p-1.5 rounded-lg hover:bg-surface-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="flex-1 overflow-y-auto">
        <slot />
      </div>
    </div>
  </div>
</template>
