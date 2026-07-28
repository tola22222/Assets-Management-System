<script setup>
defineProps({
  value: { type: [String, Number], required: true },
  label: { type: String, required: true },
  badge: { type: String, default: null },
})
</script>

<template>
  <div class="card p-5">
    <div class="flex items-start justify-between gap-2">
      <span class="font-display text-3xl font-bold text-fg tracking-tight leading-none">{{ value }}</span>
      <span v-if="badge" class="text-[11px] font-semibold bg-accent-100 text-accent-dark dark:bg-accent/15 dark:text-accent-light px-2 py-1 rounded-full whitespace-nowrap flex-shrink-0">
        {{ badge }}
      </span>
    </div>
    <p class="text-sm text-muted mt-2">{{ label }}</p>
  </div>
</template>
