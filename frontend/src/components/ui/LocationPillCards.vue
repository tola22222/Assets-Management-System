<script setup>
defineProps({
  locations: { type: Array, required: true }, // [{ location, total }]
})
</script>

<template>
  <div class="flex flex-wrap gap-3">
    <div v-for="loc in locations" :key="loc.location" class="bg-gray-100 rounded-xl px-4 py-3 min-w-[90px]">
      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide truncate">{{ loc.location }}</p>
      <p class="font-display text-xl font-bold text-ink">{{ loc.total }}</p>
    </div>
    <div v-if="!locations.length" class="text-sm text-gray-400">No stock recorded at any location yet.</div>
  </div>
</template>
