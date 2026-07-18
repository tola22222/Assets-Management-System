<script setup>
defineProps({
  title: { type: String, required: true },
  wide: { type: Boolean, default: false },
})
const emit = defineEmits(['close'])
</script>

<template>
  <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-[100] flex items-center justify-end p-4" @click.self="emit('close')">
    <div class="bg-white w-full rounded-2xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden"
      :class="wide ? 'max-w-2xl' : 'max-w-lg'">
      <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-lg font-bold text-ink">{{ title }}</h3>
        <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 transition p-1.5 rounded-lg hover:bg-gray-50">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="flex-1 overflow-y-auto">
        <slot />
      </div>
    </div>
  </div>
</template>
