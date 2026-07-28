<script setup>
defineProps({
  active: { type: Boolean, default: false },
  direction: { type: String, default: 'asc' },
})
</script>

<template>
  <svg
    class="w-3 h-3 inline-block ml-1 -mb-px transition-transform duration-150"
    :class="[active ? 'opacity-100' : 'opacity-30', active && direction === 'desc' ? 'rotate-180' : '']"
    fill="none" stroke="currentColor" stroke-width="2.75" viewBox="0 0 24 24"
  >
    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
  </svg>
</template>
