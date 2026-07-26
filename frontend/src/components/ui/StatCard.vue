<script setup>
defineProps({
  value: { type: [String, Number], required: true },
  label: { type: String, required: true },
  badge: { type: String, default: null },
})
</script>

<template>
  <v-card rounded="lg" variant="flat" border class="pa-5">
    <div class="d-flex align-start justify-space-between ga-2">
      <span class="text-h4 font-weight-bold font-display" style="line-height: 1">{{ value }}</span>
      <v-chip v-if="badge" size="small" color="secondary" variant="tonal" class="flex-shrink-0 text-no-wrap">
        {{ badge }}
      </v-chip>
    </div>
    <p class="text-body-2 text-medium-emphasis mt-2">{{ label }}</p>
  </v-card>
</template>
