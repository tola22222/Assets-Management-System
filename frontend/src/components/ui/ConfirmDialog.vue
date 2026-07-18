<script setup>
defineProps({
  title: { type: String, default: 'Delete Confirmation' },
  message: { type: String, default: 'Are you sure? This action cannot be undone.' },
})
const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
  <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-[150] flex items-center justify-center p-4" @click.self="emit('cancel')">
    <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl p-6 text-center">
      <div class="w-14 h-14 mx-auto bg-red-100 rounded-full flex items-center justify-center">
        <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
      </div>
      <h3 class="text-lg font-bold text-ink mt-4">{{ title }}</h3>
      <p class="text-sm text-gray-500 mt-2">{{ message }}</p>
      <div class="flex items-center justify-center gap-3 mt-6">
        <button @click="emit('cancel')" class="border border-gray-200 text-gray-600 font-semibold text-sm px-6 py-2.5 rounded-xl hover:bg-gray-50 transition">Cancel</button>
        <button @click="emit('confirm')" class="bg-red-500 hover:bg-red-600 text-white font-semibold text-sm px-6 py-2.5 rounded-xl transition">Delete</button>
      </div>
    </div>
  </div>
</template>
