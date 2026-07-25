<script setup>
defineProps({
  active: { type: Boolean, default: false },
  direction: { type: String, default: 'asc' },
})
</script>

<template>
  <v-icon
    icon="mdi-chevron-down"
    size="14"
    class="ml-1"
    style="transition: transform 150ms, opacity 150ms"
    :style="{ opacity: active ? 1 : 0.3, transform: active && direction === 'desc' ? 'rotate(180deg)' : 'rotate(0deg)' }"
  />
</template>
