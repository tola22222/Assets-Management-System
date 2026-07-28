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
      <h1 class="font-display text-2xl font-bold text-fg tracking-tight">{{ title }}</h1>
      <p v-if="subtitle" class="text-muted text-sm mt-1">{{ subtitle }}</p>
    </div>
    <button v-if="buttonText" @click="$emit('action')" class="btn-primary flex-shrink-0">
      <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
      </svg>
      {{ buttonText }}
    </button>
  </div>
</template>
