<script setup>
defineProps({
  value: { type: [String, Number], required: true },
  label: { type: String, required: true },
  badge: { type: String, default: null },
})
</script>

<template>
  <div class="bg-white rounded-2xl border border-gray-200 p-5">
    <div class="flex items-start justify-between gap-2">
      <span class="font-display text-3xl font-bold text-ink tracking-tight">{{ value }}</span>
      <span v-if="badge" class="text-xs font-semibold bg-accent-light/40 text-brand-dark px-2 py-1 rounded-full whitespace-nowrap">
        {{ badge }}
      </span>
    </div>
    <p class="text-sm text-gray-500 mt-1">{{ label }}</p>
  </div>
</template>
