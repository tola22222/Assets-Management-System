<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  buttonText: { type: String, default: null },
})
defineEmits(['action'])
</script>

<template>
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-xl font-bold text-ink tracking-tight">{{ title }}</h1>
      <p v-if="subtitle" class="text-gray-500 text-sm mt-0.5">{{ subtitle }}</p>
    </div>
    <button v-if="buttonText" @click="$emit('action')"
      class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-5 py-2.5 rounded-xl shadow-sm flex items-center gap-2 transition flex-shrink-0">
      <svg width="18" height="18" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M10.75 6.75V14.75M14.75 10.75H6.75M10.75 20.75C16.2728 20.75 20.75 16.2728 20.75 10.75C20.75 5.22715 16.2728 0.75 10.75 0.75C5.22715 0.75 0.75 5.22715 0.75 10.75C0.75 16.2728 5.22715 20.75 10.75 20.75Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      {{ buttonText }}
    </button>
  </div>
</template>
