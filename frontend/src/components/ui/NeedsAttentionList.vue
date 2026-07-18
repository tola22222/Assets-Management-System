<script setup>
defineProps({
  items: { type: Array, required: true }, // [{ label, count, type }]
})

const dotColor = {
  verification: 'bg-brand',
  return: 'bg-accent',
  transfer: 'bg-brand-light',
  disposal: 'bg-red-500',
}
</script>

<template>
  <ul class="divide-y divide-dashed divide-gray-200">
    <li v-for="item in items" :key="item.type" class="flex items-center justify-between py-2.5 first:pt-0 last:pb-0">
      <div class="flex items-center gap-2.5 min-w-0">
        <span class="w-2 h-2 rounded-full flex-shrink-0" :class="dotColor[item.type] || 'bg-gray-400'"></span>
        <span class="font-semibold text-ink text-sm truncate">{{ item.label }}</span>
      </div>
      <span class="text-sm text-gray-500 flex-shrink-0">{{ item.count }}</span>
    </li>
    <li v-if="!items.length" class="py-4 text-center text-sm text-gray-400">Nothing needs attention right now.</li>
  </ul>
</template>
